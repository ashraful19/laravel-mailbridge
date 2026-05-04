<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\MarketingProvider;
use Ashraful19\LaravelMailbridge\Contracts\TransactionalProvider;
use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Exceptions\UnsupportedMailbridgeFeature;

final class BrevoProvider extends AbstractProvider implements TransactionalProvider, MarketingProvider
{
    public function send(TransactionalMessage $message): SendResult
    {
        if (! class_exists(\Brevo\Client\Api\TransactionalEmailsApi::class)) {
            throw $this->missingSdk();
        }

        throw UnsupportedMailbridgeFeature::make($this->name, 'provider adapter send implementation pending official SDK wiring');
    }

    public function subscribe(string $list, Subscriber $subscriber): MarketingResult
    {
        if (! class_exists(\Brevo\Client\Api\ContactsApi::class)) {
            throw $this->missingSdk();
        }

        throw UnsupportedMailbridgeFeature::make($this->name, 'provider adapter subscribe implementation pending official SDK wiring');
    }
}
