<?php

namespace Ashraful19\LaravelMailbridge\Contracts;

use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\Subscriber;

interface MarketingProvider extends ProviderAdapter
{
    public function subscribe(string $list, Subscriber $subscriber): MarketingResult;
}
