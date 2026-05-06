<?php

namespace Ashraful19\LaravelMailbridge\Tests\Unit;

use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Exceptions\MissingFromAddressException;
use Ashraful19\LaravelMailbridge\Exceptions\UnknownProviderException;
use Ashraful19\LaravelMailbridge\MailbridgeManager;
use Ashraful19\LaravelMailbridge\Support\TransactionalMessageNormalizer;
use Ashraful19\LaravelMailbridge\Tests\TestCase;

final class ExceptionSpecializationTest extends TestCase
{
    public function test_unknown_provider_throws_specific_exception(): void
    {
        $this->expectException(UnknownProviderException::class);

        app(MailbridgeManager::class)->providerConfig('missing-provider');
    }

    public function test_runtime_provider_config_cannot_override_fixed_driver(): void
    {
        config()->set('mailbridge.providers.sendgrid.driver', 'broken-driver');

        $config = app(MailbridgeManager::class)->providerConfig('sendgrid');

        $this->assertSame('sendgrid', $config['driver']);
    }

    public function test_missing_from_address_throws_specific_exception(): void
    {
        config()->set('mailbridge.from.address', null);

        $message = new TransactionalMessage();
        $message->to[] = \Ashraful19\LaravelMailbridge\Data\Address::make('a@example.com', 'A');
        $message->subject = 'Hello';
        $message->text = 'Hi';

        $this->expectException(MissingFromAddressException::class);
        (new TransactionalMessageNormalizer($this->app))->normalize($message);
    }
}
