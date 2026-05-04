<?php

namespace Ashraful19\LaravelMailbridge\Support;

use Ashraful19\LaravelMailbridge\Data\Address;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeValidationException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Str;

final readonly class TransactionalMessageNormalizer
{
    public function __construct(private Container $app) {}

    public function normalize(TransactionalMessage $message): TransactionalMessage
    {
        $normalized = clone $message;

        if ($normalized->mailable !== null) {
            $this->hydrateFromMailable($normalized, clone $normalized->mailable);
        }

        if ($normalized->from === null) {
            $from = (array) $this->app['config']->get('mailbridge.from', []);
            $address = $from['address'] ?? null;

            if ($address !== null && $address !== '') {
                $normalized->from = Address::make($address, $from['name'] ?? null);
            }
        }

        if ($normalized->from === null) {
            throw new MailbridgeValidationException('Transactional email needs a from address. Configure MAIL_FROM_ADDRESS or call from().');
        }

        if (! $normalized->isTemplateSend() && $normalized->html === null && $normalized->text === null) {
            throw new MailbridgeValidationException('Transactional email needs html(), text(), a Laravel Mailable, template(), or templateId().');
        }

        if (! $normalized->isTemplateSend() && $normalized->subject === null) {
            throw new MailbridgeValidationException('Transactional raw email needs a subject.');
        }

        return $normalized;
    }

    private function hydrateFromMailable(TransactionalMessage $message, Mailable $mailable): void
    {
        $message->html ??= $mailable->render();
        $message->subject ??= $mailable->subject ?: Str::title(Str::snake(class_basename($mailable), ' '));

        $this->appendRecipients($message->to, $mailable->to);
        $this->appendRecipients($message->cc, $mailable->cc);
        $this->appendRecipients($message->bcc, $mailable->bcc);

        if ($message->from === null && isset($mailable->from[0]['address'])) {
            $message->from = Address::make($mailable->from[0]['address'], $mailable->from[0]['name'] ?? null);
        }

        if ($message->replyTo === null && isset($mailable->replyTo[0]['address'])) {
            $message->replyTo = Address::make($mailable->replyTo[0]['address'], $mailable->replyTo[0]['name'] ?? null);
        }

        $message->attachments = array_merge(
            $message->attachments,
            $this->normalizeFileAttachments($mailable->attachments),
            $this->normalizeRawAttachments($mailable->rawAttachments),
        );
    }

    private function appendRecipients(array &$target, array $recipients): void
    {
        foreach ($recipients as $recipient) {
            if (! isset($recipient['address'])) {
                continue;
            }

            $address = Address::make($recipient['address'], $recipient['name'] ?? null);

            if (! $this->hasAddress($target, $address->email)) {
                $target[] = $address;
            }
        }
    }

    /**
     * @param list<Address> $addresses
     */
    private function hasAddress(array $addresses, string $email): bool
    {
        foreach ($addresses as $address) {
            if (strcasecmp($address->email, $email) === 0) {
                return true;
            }
        }

        return false;
    }

    private function normalizeFileAttachments(array $attachments): array
    {
        $normalized = [];

        foreach ($attachments as $attachment) {
            $file = $attachment['file'] ?? null;

            if (! is_string($file) || ! is_file($file)) {
                continue;
            }

            $normalized[] = [
                'content' => file_get_contents($file),
                'name' => $attachment['options']['as'] ?? basename($file),
                'mime' => $attachment['options']['mime'] ?? null,
                'path' => $file,
            ];
        }

        return $normalized;
    }

    private function normalizeRawAttachments(array $attachments): array
    {
        return array_map(fn (array $attachment): array => [
            'content' => $attachment['data'],
            'name' => $attachment['name'],
            'mime' => $attachment['options']['mime'] ?? null,
        ], $attachments);
    }
}
