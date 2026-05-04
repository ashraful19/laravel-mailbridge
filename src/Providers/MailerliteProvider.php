<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\MarketingProvider;
use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Support\ProviderFailureHandler;
use MailerLite\MailerLite;
use Throwable;

final class MailerliteProvider extends AbstractProvider implements MarketingProvider
{
    public function __construct(
        string $name,
        array $config,
        \Illuminate\Contracts\Container\Container $app,
        private readonly mixed $client = null,
    ) {
        parent::__construct($name, $config, $app);
    }

    public function subscribe(string $list, Subscriber $subscriber): MarketingResult
    {
        if ($this->client === null && ! class_exists(MailerLite::class)) {
            throw $this->missingSdk();
        }

        try {
            $response = $this->mailerliteClient()->subscribers->create($this->payload($subscriber));
            $subscriberId = (string) ($response['data']['id'] ?? $subscriber->email);

            if ($list !== '') {
                $this->mailerliteClient()->groups->assignSubscriber($list, $subscriberId);
            }

            return new MarketingResult($this->name, 'subscribe', ['list' => $list, 'subscriber_id' => $subscriberId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscribe', $exception);
        }
    }

    public function payload(Subscriber $subscriber): array
    {
        return array_filter([
            'email' => $subscriber->email,
            'name' => $subscriber->name,
            'fields' => $subscriber->fields,
        ], fn ($value) => $value !== null && $value !== []);
    }

    private function mailerliteClient(): mixed
    {
        return $this->client ?? new MailerLite(['api_key' => $this->requireConfig('api_key')]);
    }
}
