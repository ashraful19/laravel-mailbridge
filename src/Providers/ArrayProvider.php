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

final class ArrayProvider extends AbstractProvider implements TransactionalProvider, MarketingProvider
{
    public array $transactional = [];

    public array $subscribers = [];

    public array $campaigns = [];

    public function send(TransactionalMessage $message): SendResult
    {
        $id = $this->messageId('array');
        $this->transactional[] = ['id' => $id, 'message' => clone $message];

        return new SendResult($this->name, $id);
    }

    public function subscribe(string $list, Subscriber $subscriber): MarketingResult
    {
        $this->subscribers[$list][$subscriber->email] = $subscriber->toArray();

        return new MarketingResult($this->name, 'subscribe', ['list' => $list]);
    }

    public function unsubscribe(string $list, string $email): MarketingResult
    {
        unset($this->subscribers[$list][$email]);

        return new MarketingResult($this->name, 'unsubscribe', ['list' => $list]);
    }

    public function getSubscriber(string $email): ?SubscriberRecord
    {
        foreach ($this->subscribers as $list => $subscribers) {
            if (isset($subscribers[$email])) {
                return new SubscriberRecord($this->name, $email, ['list' => $list, ...$subscribers[$email]]);
            }
        }

        return null;
    }

    public function deleteSubscriber(string $email): MarketingResult
    {
        foreach (array_keys($this->subscribers) as $list) {
            unset($this->subscribers[$list][$email]);
        }

        return new MarketingResult($this->name, 'delete_subscriber');
    }

    public function createCampaign(Campaign $campaign): MarketingResult
    {
        $id = $this->messageId('campaign');
        $this->campaigns[$id] = ['campaign' => $campaign, 'status' => 'draft'];

        return new MarketingResult($this->name, 'campaign_create', ['campaign_id' => $id]);
    }

    public function sendCampaign(string|int $campaignId): MarketingResult
    {
        $this->campaigns[$campaignId]['status'] = 'sent';

        return new MarketingResult($this->name, 'campaign_send', ['campaign_id' => $campaignId]);
    }

    public function scheduleCampaign(string|int $campaignId, \DateTimeInterface|string $when): MarketingResult
    {
        $this->campaigns[$campaignId]['status'] = 'scheduled';
        $this->campaigns[$campaignId]['scheduled_at'] = $when instanceof \DateTimeInterface ? $when->format(DATE_ATOM) : $when;

        return new MarketingResult($this->name, 'campaign_schedule', ['campaign_id' => $campaignId, 'scheduled_at' => $this->campaigns[$campaignId]['scheduled_at']]);
    }

    public function getCampaign(string|int $campaignId): MarketingResult
    {
        return new MarketingResult($this->name, 'campaign_get', ['campaign_id' => $campaignId, 'campaign' => $this->campaigns[$campaignId] ?? null]);
    }

    public function deleteCampaign(string|int $campaignId): MarketingResult
    {
        unset($this->campaigns[$campaignId]);

        return new MarketingResult($this->name, 'campaign_delete', ['campaign_id' => $campaignId]);
    }
}
