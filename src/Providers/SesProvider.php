<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\TransactionalProvider;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Support\AddressFormatter;
use Ashraful19\LaravelMailbridge\Support\ProviderFailureHandler;
use Aws\Ses\SesClient;
use Symfony\Component\Mime\Email;
use Throwable;

final class SesProvider extends AbstractProvider implements TransactionalProvider
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
        if ($this->client === null && ! class_exists(SesClient::class)) {
            throw $this->missingSdk();
        }

        $message = $this->normalizer()->normalize($message, $this->config);

        try {
            $response = $message->attachments !== []
                ? $this->sesClient()->sendRawEmail($this->rawPayload($message))
                : ($message->isTemplateSend()
                    ? $this->sesClient()->sendTemplatedEmail($this->templatePayload($message))
                    : $this->sesClient()->sendEmail($this->payload($message)));

            return new SendResult($this->name, $response['MessageId'] ?? null);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'transactional.send', $exception);
        }
    }

    public function payload(TransactionalMessage $message): array
    {
        return array_filter([
            'Source' => AddressFormatter::string($message->from),
            'Destination' => array_filter([
                'ToAddresses' => AddressFormatter::strings($message->to),
                'CcAddresses' => AddressFormatter::strings($message->cc),
                'BccAddresses' => AddressFormatter::strings($message->bcc),
            ]),
            'ReplyToAddresses' => $message->replyTo ? [AddressFormatter::string($message->replyTo)] : null,
            'Message' => [
                'Subject' => ['Data' => (string) $message->subject, 'Charset' => 'UTF-8'],
                'Body' => array_filter([
                    'Html' => $message->html === null ? null : ['Data' => $message->html, 'Charset' => 'UTF-8'],
                    'Text' => $message->text === null ? null : ['Data' => $message->text, 'Charset' => 'UTF-8'],
                ]),
            ],
            'Tags' => $this->tags($message),
        ], fn ($value) => $value !== null && $value !== []);
    }

    public function templatePayload(TransactionalMessage $message): array
    {
        return array_filter([
            'Source' => AddressFormatter::string($message->from),
            'Destination' => array_filter([
                'ToAddresses' => AddressFormatter::strings($message->to),
                'CcAddresses' => AddressFormatter::strings($message->cc),
                'BccAddresses' => AddressFormatter::strings($message->bcc),
            ]),
            'ReplyToAddresses' => $message->replyTo ? [AddressFormatter::string($message->replyTo)] : null,
            'Template' => (string) $message->templateId,
            'TemplateData' => json_encode($message->data, JSON_THROW_ON_ERROR),
            'Tags' => $this->tags($message),
        ], fn ($value) => $value !== null && $value !== []);
    }

    public function rawPayload(TransactionalMessage $message): array
    {
        $email = (new Email())
            ->from(AddressFormatter::string($message->from))
            ->subject((string) $message->subject);

        foreach (AddressFormatter::strings($message->to) as $address) {
            $email->addTo($address);
        }

        foreach (AddressFormatter::strings($message->cc) as $address) {
            $email->addCc($address);
        }

        foreach (AddressFormatter::strings($message->bcc) as $address) {
            $email->addBcc($address);
        }

        if ($message->replyTo !== null) {
            $email->replyTo(AddressFormatter::string($message->replyTo));
        }

        if ($message->text !== null) {
            $email->text($message->text);
        }

        if ($message->html !== null) {
            $email->html($message->html);
        }

        foreach ($message->attachments as $attachment) {
            $email->attach((string) $attachment['content'], $attachment['name'] ?? 'attachment', $attachment['mime'] ?? 'application/octet-stream');
        }

        return ['RawMessage' => ['Data' => $email->toString()]];
    }

    private function tags(TransactionalMessage $message): array
    {
        $tags = array_map(fn (string $tag): array => ['Name' => 'tag', 'Value' => $tag], $message->tags);

        foreach ($message->metadata as $key => $value) {
            $tags[] = ['Name' => (string) $key, 'Value' => (string) $value];
        }

        return $tags;
    }

    private function sesClient(): mixed
    {
        return $this->client ?? new SesClient([
            'version' => '2010-12-01',
            'region' => $this->requireConfig('region'),
            'credentials' => [
                'key' => $this->requireConfig('key'),
                'secret' => $this->requireConfig('secret'),
            ],
        ]);
    }
}
