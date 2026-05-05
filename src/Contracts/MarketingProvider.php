<?php

namespace Ashraful19\LaravelMailbridge\Contracts;

use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\Campaign;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Data\SubscriberRecord;

interface MarketingProvider extends ProviderAdapter
{
    public function subscribe(string $list, Subscriber $subscriber): MarketingResult;

    public function unsubscribe(string $list, string $email): MarketingResult;

    public function getSubscriber(string $email): ?SubscriberRecord;

    public function deleteSubscriber(string $email): MarketingResult;

    public function createCampaign(Campaign $campaign): MarketingResult;

    public function sendCampaign(string|int $campaignId): MarketingResult;

    public function scheduleCampaign(string|int $campaignId, \DateTimeInterface|string $when): MarketingResult;

    public function getCampaign(string|int $campaignId): MarketingResult;

    public function deleteCampaign(string|int $campaignId): MarketingResult;
}
