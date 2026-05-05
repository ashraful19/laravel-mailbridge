<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\TransactionalProvider;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Support\AddressFormatter;
use Ashraful19\LaravelMailbridge\Support\ProviderFailureHandler;
use Postmark\Models\PostmarkAttachment;
use Postmark\PostmarkClient;
use Throwable;

final class PostmarkProvider extends AbstractProvider implements TransactionalProvider
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
        if ($this->client === null && ! class_exists(PostmarkClient::class)) {
            throw $this->missingSdk();
        }

        $message = $this->normalizer()->normalize($message);

        try {
            $response = $message->isTemplateSend()
                ? $this->postmarkClient()->sendEmailWithTemplate(...$this->templateArguments($message))
                : $this->postmarkClient()->sendEmail(...$this->rawArguments($message));

            return new SendResult($this->name, $response->getMessageID());
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'transactional.send', $exception);
        }
    }

    public function rawArguments(TransactionalMessage $message): array
    {
        return [
            AddressFormatter::string($message->from),
            AddressFormatter::strings($message->to),
            (string) $message->subject,
            $message->html,
            $message->text,
            $message->tags[0] ?? null,
            null,
            $message->replyTo ? AddressFormatter::string($message->replyTo) : null,
            AddressFormatter::strings($message->cc),
            AddressFormatter::strings($message->bcc),
            $this->headers($message),
            $this->attachments($message),
            null,
            $message->metadata,
            $message->providerOptions['message_stream'] ?? null,
        ];
    }

    public function templateArguments(TransactionalMessage $message): array
    {
        return [
            AddressFormatter::string($message->from),
            AddressFormatter::strings($message->to),
            $message->templateId,
            $message->data,
            true,
            $message->tags[0] ?? null,
            null,
            $message->replyTo ? AddressFormatter::string($message->replyTo) : null,
            AddressFormatter::strings($message->cc),
            AddressFormatter::strings($message->bcc),
            $this->headers($message),
            null,
            null,
            $message->metadata,
            $message->providerOptions['message_stream'] ?? null,
        ];
    }

    private function headers(TransactionalMessage $message): ?array
    {
        return $message->tags === [] ? null : array_map(fn (string $tag): array => ['Name' => 'X-Mailbridge-Tag', 'Value' => $tag], $message->tags);
    }

    private function attachments(TransactionalMessage $message): ?array
    {
        if ($message->attachments === []) {
            return null;
        }

        return array_map(fn (array $attachment): PostmarkAttachment => PostmarkAttachment::fromRawData(
            (string) $attachment['content'],
            $attachment['name'] ?? 'attachment',
            $attachment['mime'] ?? null,
        ), $message->attachments);
    }

    private function postmarkClient(): mixed
    {
        return $this->client ?? new PostmarkClient($this->requireConfig('server_token'));
    }
}
