<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\MarketingProvider;
use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\Campaign;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Data\SubscriberRecord;
use Ashraful19\LaravelMailbridge\Exceptions\UnsupportedMailbridgeFeature;
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

    public function unsubscribe(string $list, string $email): MarketingResult
    {
        try {
            $this->mailerliteClient()->groups->unAssignSubscriber($list, $email);

            return new MarketingResult($this->name, 'unsubscribe', ['list' => $list]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.unsubscribe', $exception);
        }
    }

    public function getSubscriber(string $email): ?SubscriberRecord
    {
        try {
            $response = $this->mailerliteClient()->subscribers->find($email);

            return new SubscriberRecord($this->name, $email, $response);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscriber.lookup', $exception);
        }
    }

    public function deleteSubscriber(string $email): MarketingResult
    {
        try {
            $this->mailerliteClient()->subscribers->delete($email);

            return new MarketingResult($this->name, 'delete_subscriber');
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscriber.delete', $exception);
        }
    }

    public function createCampaign(Campaign $campaign): MarketingResult
    {
        try {
            $response = $this->mailerliteClient()->campaigns->create($this->campaignPayload($campaign));

            return new MarketingResult($this->name, 'campaign_create', ['campaign_id' => $response['data']['id'] ?? null]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.create', $exception);
        }
    }

    public function sendCampaign(string|int $campaignId): MarketingResult
    {
        throw UnsupportedMailbridgeFeature::make($this->name, 'marketing.campaigns.send');
    }

    public function scheduleCampaign(string|int $campaignId, \DateTimeInterface|string $when): MarketingResult
    {
        try {
            $response = $this->mailerliteClient()->campaigns->schedule((string) $campaignId, [
                'delivery' => 'scheduled',
                'schedule' => ['date' => $when instanceof \DateTimeInterface ? $when->format('Y-m-d H:i:s') : $when],
            ]);

            return new MarketingResult($this->name, 'campaign_schedule', ['campaign_id' => $campaignId, 'response' => $response]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.schedule', $exception);
        }
    }

    public function getCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $response = $this->mailerliteClient()->campaigns->find((string) $campaignId);

            return new MarketingResult($this->name, 'campaign_get', ['campaign_id' => $campaignId, 'campaign' => $response]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.get', $exception);
        }
    }

    public function deleteCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $this->mailerliteClient()->campaigns->delete((string) $campaignId);

            return new MarketingResult($this->name, 'campaign_delete', ['campaign_id' => $campaignId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.delete', $exception);
        }
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

    public function campaignPayload(Campaign $campaign): array
    {
        $from = $this->campaignFrom($campaign->fromEmail, $campaign->fromName);

        return array_filter([
            'name' => $campaign->name,
            'type' => $campaign->options['type'] ?? 'regular',
            'emails' => [[
                'subject' => $campaign->subject,
                'from' => $from['email'],
                'from_name' => $from['name'],
                'content' => $campaign->html,
            ]],
            'groups' => array_values($campaign->lists),
            ...$campaign->options,
        ], fn ($value) => $value !== null && $value !== []);
    }
}
