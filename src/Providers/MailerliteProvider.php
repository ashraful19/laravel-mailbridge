<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\MarketingProvider;
use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Exceptions\UnsupportedMailbridgeFeature;

final class MailerliteProvider extends AbstractProvider implements MarketingProvider
{
    public function subscribe(string $list, Subscriber $subscriber): MarketingResult
    {
        if (! class_exists(\MailerLite\MailerLite::class)) {
            throw $this->missingSdk();
        }

        throw UnsupportedMailbridgeFeature::make($this->name, 'provider adapter subscribe implementation pending official SDK wiring');
    }
}
