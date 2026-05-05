<?php

namespace Ashraful19\LaravelMailbridge\Contracts;

use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\Campaign;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Data\SubscriberRecord;

interface MarketingEmailSender
{
    public function marketing(?string $provider = null): mixed;

    public function subscribe(string $list, Subscriber $subscriber, ?string $provider = null, bool $fallback = false): MarketingResult;

    public function unsubscribe(string $list, string $email, ?string $provider = null, bool $fallback = false): MarketingResult;

    public function getSubscriber(string $email, ?string $provider = null, bool $fallback = false): ?SubscriberRecord;

    public function deleteSubscriber(string $email, ?string $provider = null, bool $fallback = false): MarketingResult;

    public function createCampaign(Campaign $campaign, ?string $provider = null, bool $fallback = false): MarketingResult;

    public function sendCampaign(string|int $campaignId, ?string $provider = null, bool $fallback = false): MarketingResult;

    public function scheduleCampaign(string|int $campaignId, \DateTimeInterface|string $when, ?string $provider = null, bool $fallback = false): MarketingResult;

    public function getCampaign(string|int $campaignId, ?string $provider = null, bool $fallback = false): MarketingResult;

    public function deleteCampaign(string|int $campaignId, ?string $provider = null, bool $fallback = false): MarketingResult;
}
