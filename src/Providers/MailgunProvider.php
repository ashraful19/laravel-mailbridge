<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\TransactionalProvider;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Support\AddressFormatter;
use Ashraful19\LaravelMailbridge\Support\ProviderFailureHandler;
use Mailgun\Mailgun;
use Throwable;

final class MailgunProvider extends AbstractProvider implements TransactionalProvider
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
        if ($this->client === null && ! class_exists(Mailgun::class)) {
            throw $this->missingSdk();
        }

        $message = $this->normalizer()->normalize($message);
        $payload = $this->payload($message);

        try {
            $response = $this->mailgunClient()->messages()->send($this->requireConfig('domain'), $payload);

            return new SendResult($this->name, $response->getId());
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'transactional.send', $exception);
        }
    }

    public function payload(TransactionalMessage $message): array
    {
        $payload = [
            'from' => AddressFormatter::string($message->from),
            'to' => AddressFormatter::strings($message->to),
            'cc' => AddressFormatter::strings($message->cc),
            'bcc' => AddressFormatter::strings($message->bcc),
            'h:Reply-To' => $message->replyTo ? AddressFormatter::string($message->replyTo) : null,
            'subject' => $message->subject,
            'html' => $message->html,
            'text' => $message->text,
            'o:tag' => $message->tags,
            'h:X-Mailgun-Variables' => $message->metadata === [] ? null : json_encode($message->metadata, JSON_THROW_ON_ERROR),
        ];

        if ($message->isTemplateSend()) {
            $payload['template'] = $message->templateId;
            $payload['h:X-Mailgun-Variables'] = json_encode($message->data + $message->metadata, JSON_THROW_ON_ERROR);
        }

        if ($message->attachments !== []) {
            $payload['attachment'] = array_map(fn (array $attachment): array => [
                'fileContent' => (string) $attachment['content'],
                'filename' => $attachment['name'] ?? 'attachment',
            ], $message->attachments);
        }

        return array_filter($payload, fn ($value) => $value !== null && $value !== []);
    }

    private function mailgunClient(): mixed
    {
        return $this->client ?? Mailgun::create($this->requireConfig('api_key'), $this->config['endpoint'] ?? 'https://api.mailgun.net');
    }
}
