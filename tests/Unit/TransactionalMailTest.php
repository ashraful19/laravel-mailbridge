<?php

namespace Ashraful19\LaravelMailbridge\Tests\Unit;

use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeValidationException;
use Ashraful19\LaravelMailbridge\Facades\Mailbridge;
use Ashraful19\LaravelMailbridge\MailbridgeManager;
use Ashraful19\LaravelMailbridge\Tests\TestCase;

final class TransactionalMailTest extends TestCase
{
    public function test_it_sends_raw_email_through_array_provider(): void
    {
        Mailbridge::fake();

        Mailbridge::transactional()
            ->to('a@example.com')
            ->subject('Hello')
            ->text('Hello')
            ->send();

        Mailbridge::assertTransactionalSent();
    }

    public function test_template_and_template_id_are_mutually_exclusive(): void
    {
        $this->expectException(MailbridgeValidationException::class);

        Mailbridge::transactional()
            ->to('a@example.com')
            ->template('welcome')
            ->templateId('direct-id')
            ->send();
    }

    public function test_data_for_merges_with_common_template_data(): void
    {
        Mailbridge::fake();
        config()->set('mailbridge.templates.welcome.array', 'array-welcome');

        Mailbridge::transactional()
            ->template('welcome')
            ->to('a@example.com')
            ->data(['name' => 'Ash', 'button' => 'Start'])
            ->dataFor('array', ['name' => 'Ashraful'])
            ->send();

        $message = $this->lastArrayMessage();

        $this->assertSame([
            'name' => 'Ashraful',
            'button' => 'Start',
        ], $message->data);
        $this->assertSame('array-welcome', $message->templateId);
    }

    public function test_template_data_for_is_alias_for_data_for(): void
    {
        Mailbridge::fake();

        Mailbridge::transactional()
            ->templateId('direct-id')
            ->to('a@example.com')
            ->data(['name' => 'Ash'])
            ->templateDataFor('array', ['name' => 'Ashraful'])
            ->send();

        $this->assertSame(['name' => 'Ashraful'], $this->lastArrayMessage()->data);
    }

    public function test_unused_provider_specific_data_is_ignored(): void
    {
        Mailbridge::fake();

        Mailbridge::transactional()
            ->templateId('direct-id')
            ->to('a@example.com')
            ->data(['name' => 'Ash'])
            ->dataFor('log', ['name' => 'Log Name'])
            ->send();

        $this->assertSame(['name' => 'Ash'], $this->lastArrayMessage()->data);
    }

    public function test_unknown_provider_specific_data_throws(): void
    {
        Mailbridge::fake();

        $this->expectException(MailbridgeValidationException::class);

        Mailbridge::transactional()
            ->templateId('direct-id')
            ->to('a@example.com')
            ->dataFor('missing-provider', ['name' => 'Ash'])
            ->send();
    }

    private function lastArrayMessage(): object
    {
        $provider = app(MailbridgeManager::class)->provider('array');
        $record = $provider->transactional[array_key_last($provider->transactional)];

        return $record['message'];
    }
}
