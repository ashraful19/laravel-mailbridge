<?php

namespace Ashraful19\LaravelMailbridge;

use Ashraful19\LaravelMailbridge\Contracts\MarketingEmailSender;
use Ashraful19\LaravelMailbridge\Contracts\MarketingProvider;
use Ashraful19\LaravelMailbridge\Contracts\ProviderAdapter;
use Ashraful19\LaravelMailbridge\Contracts\TransactionalEmailSender;
use Ashraful19\LaravelMailbridge\Contracts\TransactionalProvider;
use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\Campaign;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Data\SubscriberRecord;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeException;
use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeValidationException;
use Ashraful19\LaravelMailbridge\Exceptions\ProviderTransientException;
use Ashraful19\LaravelMailbridge\Exceptions\UnsupportedMailbridgeFeature;
use Ashraful19\LaravelMailbridge\Providers\ArrayProvider;
use Ashraful19\LaravelMailbridge\Providers\BrevoProvider;
use Ashraful19\LaravelMailbridge\Providers\LogProvider;
use Ashraful19\LaravelMailbridge\Providers\MailerliteProvider;
use Ashraful19\LaravelMailbridge\Providers\MailersendProvider;
use Ashraful19\LaravelMailbridge\Providers\MailchimpProvider;
use Ashraful19\LaravelMailbridge\Providers\MailgunProvider;
use Ashraful19\LaravelMailbridge\Providers\MailjetProvider;
use Ashraful19\LaravelMailbridge\Providers\PostmarkProvider;
use Ashraful19\LaravelMailbridge\Providers\ResendProvider;
use Ashraful19\LaravelMailbridge\Providers\SendgridProvider;
use Ashraful19\LaravelMailbridge\Providers\SesProvider;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Assert;

final class MailbridgeManager implements TransactionalEmailSender, MarketingEmailSender
{
    /** @var array<string, ProviderAdapter> */
    private array $resolved = [];

    private bool $fake = false;

    public function __construct(private readonly Container $app) {}

    public function transactional(?string $provider = null): TransactionalMail
    {
        return new TransactionalMail($this, $provider);
    }

    public function marketing(?string $provider = null): MarketingMail
    {
        return new MarketingMail($this, $provider);
    }

    public function fake(): void
    {
        $this->fake = true;
        $this->resolved['array'] = new ArrayProvider('array', $this->providerConfig('array'), $this->app);
    }

    public function assertTransactionalSent(?string $template = null): void
    {
        $provider = $this->fakeProvider();
        $sent = $provider->transactional;

        if ($template !== null) {
            $sent = array_filter($sent, function (array $record) use ($template): bool {
                $message = $record['message'];

                return $message->template === $template || $message->templateId === $template;
            });
        }

        Assert::assertNotEmpty($sent, 'Expected a matching transactional email to be sent.');
    }

    public function assertSubscribed(string $list, string $email): void
    {
        $provider = $this->fakeProvider();

        Assert::assertArrayHasKey($list, $provider->subscribers, "Expected list [{$list}] to have subscribers.");
        Assert::assertArrayHasKey($email, $provider->subscribers[$list], "Expected [{$email}] to be subscribed to [{$list}].");
    }

    public function provider(string $provider): ProviderAdapter
    {
        return $this->resolved[$provider] ??= $this->makeProvider($provider);
    }

    public function supports(string $provider, string $feature): bool
    {
        return $this->provider($provider)->supports($feature);
    }

    public function sendTransactional(TransactionalMessage $message, ?string $provider = null, bool $fallback = false): SendResult
    {
        $providers = $this->providersFor('transactional', $provider, $fallback);
        $last = null;

        foreach ($providers as $name) {
            $adapter = $this->provider($name);

            if (! $adapter instanceof TransactionalProvider) {
                $last = UnsupportedMailbridgeFeature::make($name, 'transactional');
                continue;
            }

            $providerMessage = clone $message;
            $this->resolveTemplateAlias($providerMessage, $name);
            $this->resolveProviderTemplateData($providerMessage, $name);

            try {
                return $adapter->send($providerMessage);
            } catch (ProviderTransientException $exception) {
                $last = $exception;
            }
        }

        throw $last ?? new MailbridgeException('No transactional provider could send message.');
    }

    public function subscribe(string $list, Subscriber $subscriber, ?string $provider = null, bool $fallback = false): MarketingResult
    {
        return $this->runMarketing('subscribe', $provider, $fallback, fn (MarketingProvider $adapter, string $name) => $adapter->subscribe($this->resolveListAlias($list, $name), $subscriber));
    }

    public function unsubscribe(string $list, string $email, ?string $provider = null, bool $fallback = false): MarketingResult
    {
        return $this->runMarketing('unsubscribe', $provider, $fallback, fn (MarketingProvider $adapter, string $name) => $adapter->unsubscribe($this->resolveListAlias($list, $name), $email));
    }

    public function getSubscriber(string $email, ?string $provider = null, bool $fallback = false): ?SubscriberRecord
    {
        return $this->runMarketing('subscriber lookup', $provider, $fallback, fn (MarketingProvider $adapter) => $adapter->getSubscriber($email));
    }

    public function deleteSubscriber(string $email, ?string $provider = null, bool $fallback = false): MarketingResult
    {
        return $this->runMarketing('subscriber delete', $provider, $fallback, fn (MarketingProvider $adapter) => $adapter->deleteSubscriber($email));
    }

    public function createCampaign(Campaign $campaign, ?string $provider = null, bool $fallback = false): MarketingResult
    {
        return $this->runMarketing('campaign create', $provider, $fallback, fn (MarketingProvider $adapter) => $adapter->createCampaign($campaign));
    }

    public function sendCampaign(string|int $campaignId, ?string $provider = null, bool $fallback = false): MarketingResult
    {
        return $this->runMarketing('campaign send', $provider, $fallback, fn (MarketingProvider $adapter) => $adapter->sendCampaign($campaignId));
    }

    public function scheduleCampaign(string|int $campaignId, \DateTimeInterface|string $when, ?string $provider = null, bool $fallback = false): MarketingResult
    {
        return $this->runMarketing('campaign schedule', $provider, $fallback, fn (MarketingProvider $adapter) => $adapter->scheduleCampaign($campaignId, $when));
    }

    public function getCampaign(string|int $campaignId, ?string $provider = null, bool $fallback = false): MarketingResult
    {
        return $this->runMarketing('campaign get', $provider, $fallback, fn (MarketingProvider $adapter) => $adapter->getCampaign($campaignId));
    }

    public function deleteCampaign(string|int $campaignId, ?string $provider = null, bool $fallback = false): MarketingResult
    {
        return $this->runMarketing('campaign delete', $provider, $fallback, fn (MarketingProvider $adapter) => $adapter->deleteCampaign($campaignId));
    }

    public function providerMetadata(): array
    {
        return (array) $this->app['config']->get('mailbridge.providers', []);
    }

    public function providerConfig(string $provider): array
    {
        $config = $this->providerMetadata()[$provider] ?? null;

        if ($config === null) {
            throw new MailbridgeValidationException("Unknown Mailbridge provider [{$provider}].");
        }

        return $config;
    }

    private function providersFor(string $lane, ?string $provider, bool $fallback): array
    {
        if ($this->fake) {
            return ['array'];
        }

        $fallbacks = (array) $this->app['config']->get("mailbridge.fallbacks.{$lane}", []);

        if ($provider !== null && ! $fallback) {
            return [$provider];
        }

        $first = $provider ?: (string) $this->app['config']->get("mailbridge.default.{$lane}");

        return array_values(array_unique(array_filter([$first, ...$fallbacks])));
    }

    private function resolveTemplateAlias(TransactionalMessage $message, string $provider): void
    {
        if ($message->template === null) {
            return;
        }

        $templateId = $this->app['config']->get("mailbridge.templates.{$message->template}.{$provider}");

        if ($templateId === null) {
            throw new MailbridgeValidationException("Missing template mapping [{$message->template}] for provider [{$provider}].");
        }

        $message->templateId = $templateId;
    }

    private function resolveProviderTemplateData(TransactionalMessage $message, string $provider): void
    {
        $message->data = array_replace_recursive(
            $message->data,
            $message->providerData[$provider] ?? [],
        );
    }

    private function resolveListAlias(string $list, string $provider): string
    {
        return (string) ($this->app['config']->get("mailbridge.lists.{$list}.{$provider}") ?? $list);
    }

    private function runMarketing(string $operation, ?string $provider, bool $fallback, callable $callback): mixed
    {
        $providers = $this->providersFor('marketing', $provider, $fallback);
        $last = null;

        foreach ($providers as $name) {
            $adapter = $this->provider($name);

            if (! $adapter instanceof MarketingProvider) {
                $last = UnsupportedMailbridgeFeature::make($name, 'marketing');
                continue;
            }

            try {
                return $callback($adapter, $name);
            } catch (ProviderTransientException $exception) {
                $last = $exception;
            }
        }

        throw $last ?? new MailbridgeException("No marketing provider could complete {$operation}.");
    }

    private function makeProvider(string $provider): ProviderAdapter
    {
        $config = $this->providerConfig($provider);

        return match ($config['driver'] ?? $provider) {
            'array' => new ArrayProvider($provider, $config, $this->app),
            'log' => new LogProvider($provider, $config, $this->app),
            'sendgrid' => new SendgridProvider($provider, $config, $this->app),
            'ses' => new SesProvider($provider, $config, $this->app),
            'brevo' => new BrevoProvider($provider, $config, $this->app),
            'mailersend' => new MailersendProvider($provider, $config, $this->app),
            'resend' => new ResendProvider($provider, $config, $this->app),
            'postmark' => new PostmarkProvider($provider, $config, $this->app),
            'mailchimp' => new MailchimpProvider($provider, $config, $this->app),
            'mailgun' => new MailgunProvider($provider, $config, $this->app),
            'mailjet' => new MailjetProvider($provider, $config, $this->app),
            'mailerlite' => new MailerliteProvider($provider, $config, $this->app),
            default => throw new MailbridgeValidationException("Unknown Mailbridge driver [{$config['driver']}]."),
        };
    }

    private function fakeProvider(): ArrayProvider
    {
        if (! isset($this->resolved['array']) || ! $this->resolved['array'] instanceof ArrayProvider) {
            $this->fake();
        }

        return $this->resolved['array'];
    }
}
