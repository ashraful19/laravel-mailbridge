<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\TransactionalProvider;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeException;
use Ashraful19\LaravelMailbridge\Exceptions\ProviderTransientException;
use Ashraful19\LaravelMailbridge\Support\ProviderFailureHandler;
use SendGrid\Mail\Attachment;
use SendGrid\Mail\Mail;
use Throwable;

final class SendgridProvider extends AbstractProvider implements TransactionalProvider
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
        if ($this->client === null && ! class_exists(\SendGrid::class)) {
            throw $this->missingSdk();
        }

        $message = $this->normalizer()->normalize($message);
        $mail = $this->mail($message);

        try {
            $response = $this->sendgridClient()->send($mail);
            $status = (int) $response->statusCode();

            if ($status < 200 || $status >= 300) {
                $this->throwResponseFailure($status, (string) $response->body());
            }

            $headers = $response->headers(true);

            return new SendResult($this->name, $headers['X-Message-Id'] ?? null, ['status' => $status]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'transactional.send', $exception);
        }
    }

    public function payload(TransactionalMessage $message): array
    {
        return $this->mail($message)->jsonSerialize();
    }

    public function mail(TransactionalMessage $message): Mail
    {
        $mail = new Mail();
        $mail->setFrom($message->from->email, $message->from->name);

        foreach ($message->to as $address) {
            $mail->addTo($address->email, $address->name);
        }

        foreach ($message->cc as $address) {
            $mail->addCc($address->email, $address->name);
        }

        foreach ($message->bcc as $address) {
            $mail->addBcc($address->email, $address->name);
        }

        if ($message->replyTo !== null) {
            $mail->setReplyTo($message->replyTo->email, $message->replyTo->name);
        }

        if ($message->isTemplateSend()) {
            $mail->setTemplateId((string) $message->templateId);

            foreach ($message->data as $key => $value) {
                $mail->addDynamicTemplateData((string) $key, $value);
            }
        } else {
            $mail->setSubject((string) $message->subject);
            $mail->addContent('text/plain', $message->text ?? '');

            if ($message->html !== null) {
                $mail->addContent('text/html', $message->html);
            }
        }

        foreach ($message->tags as $tag) {
            $mail->addCategory($tag);
        }

        foreach ($message->metadata as $key => $value) {
            $mail->addCustomArg($key, (string) $value);
        }

        foreach ($message->attachments as $attachment) {
            $mail->addAttachment(new Attachment(
                base64_encode((string) $attachment['content']),
                $attachment['mime'] ?? 'application/octet-stream',
                $attachment['name'] ?? 'attachment',
            ));
        }

        return $mail;
    }

    private function sendgridClient(): mixed
    {
        return $this->client ?? new \SendGrid($this->requireConfig('api_key'));
    }

    private function throwResponseFailure(int $status, string $body): never
    {
        $context = ['provider' => $this->name, 'operation' => 'transactional.send', 'status' => $status];

        if ($status === 408 || $status === 409 || $status === 425 || $status === 429 || $status >= 500) {
            throw new ProviderTransientException("Provider [{$this->name}] failed transiently during [transactional.send].", $context);
        }

        throw new MailbridgeException("Provider [{$this->name}] failed during [transactional.send].", $context + ['error' => substr($body, 0, 500)]);
    }
}
