<?php

namespace Ashraful19\LaravelMailbridge\Contracts;

use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\Subscriber;

interface MarketingEmailSender
{
    public function marketing(?string $provider = null): mixed;

    public function subscribe(string $list, Subscriber $subscriber, ?string $provider = null, bool $fallback = false): MarketingResult;
}
