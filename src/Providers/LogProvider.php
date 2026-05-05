<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\MarketingProvider;
use Ashraful19\LaravelMailbridge\Contracts\TransactionalProvider;
use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\Campaign;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Data\SubscriberRecord;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Support\Redactor;

final class LogProvider extends AbstractProvider implements TransactionalProvider, MarketingProvider
{
    public function send(TransactionalMessage $message): SendResult
    {
        $id = $this->messageId('log');
        $this->app['log']->info('Mailbridge transactional email accepted by log provider.', Redactor::redact([
            'provider' => $this->name,
            'message_id' => $id,
            'to_count' => count($message->to),
            'template' => $message->template,
            'template_id' => $message->templateId,
            'tags' => $message->tags,
            'metadata' => $message->metadata,
        ]));

        return new SendResult($this->name, $id);
    }

    public function subscribe(string $list, Subscriber $subscriber): MarketingResult
    {
        $this->app['log']->info('Mailbridge subscriber accepted by log provider.', Redactor::redact([
            'provider' => $this->name,
            'list' => $list,
            'subscriber' => $subscriber->toArray(),
        ]));

        return new MarketingResult($this->name, 'subscribe', ['list' => $list]);
    }

    public function unsubscribe(string $list, string $email): MarketingResult
    {
        $this->logMarketing('unsubscribe', ['list' => $list, 'email' => $email]);

        return new MarketingResult($this->name, 'unsubscribe', ['list' => $list]);
    }

    public function getSubscriber(string $email): ?SubscriberRecord
    {
        $this->logMarketing('subscriber lookup', ['email' => $email]);

        return null;
    }

    public function deleteSubscriber(string $email): MarketingResult
    {
        $this->logMarketing('subscriber delete', ['email' => $email]);

        return new MarketingResult($this->name, 'delete_subscriber');
    }

    public function createCampaign(Campaign $campaign): MarketingResult
    {
        $id = $this->messageId('campaign');
        $this->logMarketing('campaign create', ['campaign_id' => $id, 'name' => $campaign->name]);

        return new MarketingResult($this->name, 'campaign_create', ['campaign_id' => $id]);
    }

    public function sendCampaign(string|int $campaignId): MarketingResult
    {
        $this->logMarketing('campaign send', ['campaign_id' => $campaignId]);

        return new MarketingResult($this->name, 'campaign_send', ['campaign_id' => $campaignId]);
    }

    public function scheduleCampaign(string|int $campaignId, \DateTimeInterface|string $when): MarketingResult
    {
        $scheduledAt = $when instanceof \DateTimeInterface ? $when->format(DATE_ATOM) : $when;
        $this->logMarketing('campaign schedule', ['campaign_id' => $campaignId, 'scheduled_at' => $scheduledAt]);

        return new MarketingResult($this->name, 'campaign_schedule', ['campaign_id' => $campaignId, 'scheduled_at' => $scheduledAt]);
    }

    public function getCampaign(string|int $campaignId): MarketingResult
    {
        $this->logMarketing('campaign get', ['campaign_id' => $campaignId]);

        return new MarketingResult($this->name, 'campaign_get', ['campaign_id' => $campaignId]);
    }

    public function deleteCampaign(string|int $campaignId): MarketingResult
    {
        $this->logMarketing('campaign delete', ['campaign_id' => $campaignId]);

        return new MarketingResult($this->name, 'campaign_delete', ['campaign_id' => $campaignId]);
    }

    private function logMarketing(string $operation, array $context): void
    {
        $this->app['log']->info("Mailbridge marketing {$operation} accepted by log provider.", Redactor::redact(['provider' => $this->name, ...$context]));
    }
}
