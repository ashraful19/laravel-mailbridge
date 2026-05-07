<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\TransactionalProvider;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Support\ProviderFailureHandler;
use MailerSend\Helpers\Builder\Attachment;
use MailerSend\Helpers\Builder\EmailParams;
use MailerSend\Helpers\Builder\Personalization;
use MailerSend\Helpers\Builder\Recipient;
use MailerSend\MailerSend;
use Throwable;

final class MailersendProvider extends AbstractProvider implements TransactionalProvider
{
    public function __construct(
        string $name,
        array $config,
        \Illuminate\Contracts\Container\Container $app,
        private readonly mixed $client = null,
    ) {
        parent::__construct($name, $config, $app);
    }

    public function send(TransactionalMessage $message): SendResult
    {
        if ($this->client === null && ! class_exists(MailerSend::class)) {
            throw $this->missingSdk();
        }

        $message = $this->normalizer()->normalize($message, $this->config);
        $params = $this->emailParams($message);

        try {
            $response = $this->mailersendClient()->email->send($params);

            return new SendResult($this->name, $response['message_id'] ?? $response['id'] ?? null, $response);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'transactional.send', $exception);
        }
    }

    public function emailParams(TransactionalMessage $message): EmailParams
    {
        $params = (new EmailParams())
            ->setFrom($message->from->email)
            ->setRecipients($this->recipients($message->to));

        if ($message->from->name !== null) {
            $params->setFromName($message->from->name);
        }

        if ($message->replyTo !== null) {
            $params->setReplyTo($message->replyTo->email);

            if ($message->replyTo->name !== null) {
                $params->setReplyToName($message->replyTo->name);
            }
        }

        if ($message->cc !== []) {
            $params->setCc($this->recipients($message->cc));
        }

        if ($message->bcc !== []) {
            $params->setBcc($this->recipients($message->bcc));
        }

        if ($message->isTemplateSend()) {
            $params->setTemplateId((string) $message->templateId);

            // MailerSend may still require subject for template sends depending on template setup.
            // Respect caller-provided subject instead of dropping it in template mode.
            if ($message->subject !== null && $message->subject !== '') {
                $params->setSubject($message->subject);
            }

            $params->setPersonalization(array_map(
                fn ($recipient): Personalization => new Personalization($recipient->email, $message->data),
                $message->to,
            ));
        } else {
            $params->setSubject((string) $message->subject)
                ->setHtml($message->html)
                ->setText($message->text);
        }

        if ($message->tags !== []) {
            $params->setTags($message->tags);
        }

        if ($message->attachments !== []) {
            $params->setAttachments(array_map(fn (array $attachment): Attachment => new Attachment(
                (string) $attachment['content'],
                $attachment['name'] ?? 'attachment',
            ), $message->attachments));
        }

        return $params;
    }

    private function recipients(array $addresses): array
    {
        return array_map(fn ($address): Recipient => new Recipient($address->email, $address->name), $addresses);
    }

    private function mailersendClient(): mixed
    {
        return $this->client ?? new MailerSend(['api_key' => $this->requireConfig('api_key')]);
    }
}
