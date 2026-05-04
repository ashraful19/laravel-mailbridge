<?php

namespace Ashraful19\LaravelMailbridge;

use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
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
}
