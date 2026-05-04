<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\TransactionalProvider;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Exceptions\UnsupportedMailbridgeFeature;

final class MailgunProvider extends AbstractProvider implements TransactionalProvider
{
    public function send(TransactionalMessage $message): SendResult
    {
        if (! class_exists(\Mailgun\Mailgun::class)) {
            throw $this->missingSdk();
        }

        throw UnsupportedMailbridgeFeature::make($this->name, 'provider adapter send implementation pending official SDK wiring');
    }
}
