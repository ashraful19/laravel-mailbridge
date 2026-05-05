<?php

namespace Ashraful19\LaravelMailbridge;

use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\Campaign;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Data\SubscriberRecord;
use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeValidationException;

final class MarketingMail
{
    private ?string $list = null;

    public function __construct(
        private readonly MailbridgeManager $manager,
        private ?string $provider = null,
        private bool $fallback = false,
    ) {}

    public function provider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function withFallback(bool $fallback = true): self
    {
        $this->fallback = $fallback;

        return $this;
    }

    public function withoutFallback(): self
    {
        $this->fallback = false;

        return $this;
    }

    public function list(string $list): self
    {
        $this->list = $list;

        return $this;
    }

    public function subscribe(Subscriber $subscriber): MarketingResult
    {
        if ($this->list === null) {
            throw new MailbridgeValidationException('Marketing subscribe needs list().');
        }

        return $this->manager->subscribe($this->list, $subscriber, $this->provider, $this->fallback);
    }

    public function unsubscribe(string $email): MarketingResult
    {
        if ($this->list === null) {
            throw new MailbridgeValidationException('Marketing unsubscribe needs list().');
        }

        return $this->manager->unsubscribe($this->list, $email, $this->provider, $this->fallback);
    }

    public function getSubscriber(string $email): ?SubscriberRecord
    {
        return $this->manager->getSubscriber($email, $this->provider, $this->fallback);
    }

    public function deleteSubscriber(string $email): MarketingResult
    {
        return $this->manager->deleteSubscriber($email, $this->provider, $this->fallback);
    }

    public function createCampaign(Campaign $campaign): MarketingResult
    {
        return $this->manager->createCampaign($campaign, $this->provider, $this->fallback);
    }

    public function sendCampaign(string|int $campaignId): MarketingResult
    {
        return $this->manager->sendCampaign($campaignId, $this->provider, $this->fallback);
    }

    public function scheduleCampaign(string|int $campaignId, \DateTimeInterface|string $when): MarketingResult
    {
        return $this->manager->scheduleCampaign($campaignId, $when, $this->provider, $this->fallback);
    }

    public function getCampaign(string|int $campaignId): MarketingResult
    {
        return $this->manager->getCampaign($campaignId, $this->provider, $this->fallback);
    }

    public function deleteCampaign(string|int $campaignId): MarketingResult
    {
        return $this->manager->deleteCampaign($campaignId, $this->provider, $this->fallback);
    }
}
