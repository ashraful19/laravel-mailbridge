<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\TransactionalProvider;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Support\AddressFormatter;
use Ashraful19\LaravelMailbridge\Support\ProviderFailureHandler;
use Throwable;

final class ResendProvider extends AbstractProvider implements TransactionalProvider
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
        if ($this->client === null && ! class_exists(\Resend\Client::class)) {
            throw $this->missingSdk();
        }

        $message = $this->normalizer()->normalize($message);
        $payload = $this->payload($message);

        try {
            $response = $this->resendClient()->emails->send($payload);

            return new SendResult($this->name, $response->id ?? $response->getAttribute('id'));
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'transactional.send', $exception);
        }
    }

    public function payload(TransactionalMessage $message): array
    {
        $payload = [
            'from' => $message->from ? AddressFormatter::string($message->from) : null,
            'to' => AddressFormatter::strings($message->to),
            'cc' => AddressFormatter::strings($message->cc),
            'bcc' => AddressFormatter::strings($message->bcc),
            'reply_to' => $message->replyTo ? AddressFormatter::string($message->replyTo) : null,
            'subject' => $message->subject,
            'html' => $message->html,
            'text' => $message->text,
            'tags' => array_map(fn (string $tag): array => ['name' => 'tag', 'value' => $tag], $message->tags),
            'headers' => $message->metadata,
        ];

        if ($message->isTemplateSend()) {
            $payload['template'] = $message->templateId;
            $payload['data'] = $message->data;
        }

        if ($message->attachments !== []) {
            $payload['attachments'] = array_map(fn (array $attachment): array => [
                'filename' => $attachment['name'] ?? 'attachment',
                'content' => base64_encode((string) $attachment['content']),
            ], $message->attachments);
        }

        return array_filter($payload, fn ($value) => $value !== null && $value !== []);
    }

    private function resendClient(): mixed
    {
        return $this->client ?? \Resend::client($this->requireConfig('api_key'));
    }
}
