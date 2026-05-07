<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\MarketingProvider;
use Ashraful19\LaravelMailbridge\Data\Campaign;
use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Data\SubscriberRecord;
use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeValidationException;
use Ashraful19\LaravelMailbridge\Exceptions\UnsupportedMailbridgeFeature;
use Ashraful19\LaravelMailbridge\Support\ProviderFailureHandler;
use ConvertKit_API\ConvertKit_API;
use Throwable;

final class KitProvider extends AbstractProvider implements MarketingProvider
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
        try {
            $this->kit()->create_subscriber($subscriber->email, $subscriber->name ?? '', 'active', $subscriber->fields);
            $this->attachToList($list, $subscriber->email);

            foreach ($subscriber->tags as $tag) {
                if (is_numeric($tag)) {
                    $this->kit()->tag_subscriber_by_email((int) $tag, $subscriber->email);
                }
            }

            return new MarketingResult($this->name, 'subscribe', ['list' => $list]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscribe', $exception);
        }
    }

    public function unsubscribe(string $list, string $email): MarketingResult
    {
        try {
            $target = $this->listTarget($list);

            if ($target['type'] === 'tag') {
                $this->kit()->remove_tag_from_subscriber_by_email($target['id'], $email);

                return new MarketingResult($this->name, 'unsubscribe', ['list' => $list, 'mode' => 'tag_removed']);
            }

            $this->kit()->unsubscribe_by_email($email);

            return new MarketingResult($this->name, 'unsubscribe', ['list' => $list, 'mode' => 'global_unsubscribe']);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.unsubscribe', $exception);
        }
    }

    public function getSubscriber(string $email): ?SubscriberRecord
    {
        try {
            $id = $this->kit()->get_subscriber_id($email);

            if ($id === false) {
                return null;
            }

            return new SubscriberRecord($this->name, $email, (array) $this->kit()->get_subscriber($id));
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscriber.lookup', $exception);
        }
    }

    public function deleteSubscriber(string $email): MarketingResult
    {
        throw UnsupportedMailbridgeFeature::make($this->name, 'marketing.subscribers.delete');
    }

    public function createCampaign(Campaign $campaign): MarketingResult
    {
        $from = $this->campaignFrom($campaign->fromEmail, $campaign->fromName);

        try {
            $broadcast = $this->kit()->create_broadcast(
                subject: (string) $campaign->subject,
                content: (string) $campaign->html,
                description: $campaign->name,
                public: (bool) ($campaign->options['public'] ?? false),
                send_at: $this->dateOption($campaign->options['send_at'] ?? null),
                email_address: (string) ($from['email'] ?? ''),
                email_template_id: (string) ($campaign->options['email_template_id'] ?? ''),
                preview_text: (string) ($campaign->options['preview_text'] ?? ''),
                subscriber_filter: $this->subscriberFilter($campaign),
            );

            return new MarketingResult($this->name, 'campaign_create', ['campaign_id' => $this->broadcastId($broadcast), 'broadcast' => (array) $broadcast]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.create', $exception);
        }
    }

    public function sendCampaign(string|int $campaignId): MarketingResult
    {
        $result = $this->scheduleCampaign($campaignId, new \DateTimeImmutable('+1 minute'));

        return new MarketingResult($this->name, 'campaign_send', $result->metadata + ['mode' => 'scheduled_now']);
    }

    public function scheduleCampaign(string|int $campaignId, \DateTimeInterface|string $when): MarketingResult
    {
        try {
            $scheduledAt = $when instanceof \DateTimeInterface ? $when : new \DateTimeImmutable($when);
            $this->kit()->update_broadcast((int) $campaignId, public: true, send_at: $scheduledAt);

            return new MarketingResult($this->name, 'campaign_schedule', ['campaign_id' => $campaignId, 'scheduled_at' => $scheduledAt->format(DATE_ATOM)]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.schedule', $exception);
        }
    }

    public function getCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $broadcast = $this->kit()->get_broadcast((int) $campaignId);

            return new MarketingResult($this->name, 'campaign_get', ['campaign_id' => $campaignId, 'broadcast' => (array) $broadcast]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.get', $exception);
        }
    }

    public function deleteCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $this->kit()->delete_broadcast((int) $campaignId);

            return new MarketingResult($this->name, 'campaign_delete', ['campaign_id' => $campaignId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.delete', $exception);
        }
    }

    public function listTarget(string $list): array
    {
        if (str_contains($list, ':')) {
            [$type, $id] = explode(':', $list, 2);

            if (! in_array($type, ['tag', 'form', 'sequence'], true) || ! is_numeric($id)) {
                throw new MailbridgeValidationException('Kit list mappings must be tag:<id>, form:<id>, sequence:<id>, or a numeric tag id.');
            }

            return ['type' => $type, 'id' => (int) $id];
        }

        if (! is_numeric($list)) {
            throw new MailbridgeValidationException('Kit list mappings must be tag:<id>, form:<id>, sequence:<id>, or a numeric tag id.');
        }

        return ['type' => 'tag', 'id' => (int) $list];
    }

    public function campaignPayload(Campaign $campaign): array
    {
        $from = $this->campaignFrom($campaign->fromEmail, $campaign->fromName);

        return [
            'subject' => $campaign->subject,
            'content' => $campaign->html,
            'description' => $campaign->name,
            'email_address' => $from['email'],
            'subscriber_filter' => $this->subscriberFilter($campaign),
        ];
    }

    private function attachToList(string $list, string $email): void
    {
        $target = $this->listTarget($list);

        match ($target['type']) {
            'tag' => $this->kit()->tag_subscriber_by_email($target['id'], $email),
            'form' => $this->kit()->add_subscriber_to_form_by_email($target['id'], $email),
            'sequence' => $this->kit()->add_subscriber_to_sequence_by_email($target['id'], $email),
            default => throw UnsupportedMailbridgeFeature::make($this->name, "marketing.list.{$target['type']}"),
        };
    }

    private function subscriberFilter(Campaign $campaign): array
    {
        if (isset($campaign->options['subscriber_filter'])) {
            return $campaign->options['subscriber_filter'];
        }

        if ($campaign->lists === []) {
            return [];
        }

        $target = $this->listTarget((string) $campaign->lists[0]);
        $type = $target['type'] === 'tag' ? 'tag' : 'segment';

        return [['all' => [['type' => $type, 'ids' => [$target['id']]]]]];
    }

    private function dateOption(mixed $value): ?\DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface || $value === null || $value === '') {
            return $value ?: null;
        }

        return new \DateTimeImmutable((string) $value);
    }

    private function broadcastId(mixed $broadcast): int|string|null
    {
        return $broadcast->broadcast->id ?? $broadcast->id ?? null;
    }

    private function kit(): mixed
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if (! class_exists(ConvertKit_API::class)) {
            throw $this->missingSdk();
        }

        return new ConvertKit_API(apiKey: $this->requireConfig('api_key'));
    }
}
