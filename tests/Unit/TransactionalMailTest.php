<?php

namespace Ashraful19\LaravelMailbridge\Tests\Unit;

use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeValidationException;
use Ashraful19\LaravelMailbridge\Exceptions\MissingTemplateMappingException;
use Ashraful19\LaravelMailbridge\Exceptions\MissingTransactionalRecipientException;
use Ashraful19\LaravelMailbridge\Exceptions\TemplatePayloadConflictException;
use Illuminate\Mail\Mailable;
use Ashraful19\LaravelMailbridge\Facades\Mailbridge;
use Ashraful19\LaravelMailbridge\MailbridgeManager;
use Ashraful19\LaravelMailbridge\Tests\TestCase;

final class TransactionalMailTest extends TestCase
{
    public function test_it_sends_raw_email_through_array_provider(): void
    {
        Mailbridge::fake();

        Mailbridge::transactional()
            ->from('sender@example.com', 'Sender')
            ->to('a@example.com')
            ->subject('Hello')
            ->text('Hello')
            ->send();

        Mailbridge::assertTransactionalSent();
    }

    public function test_template_and_template_id_are_mutually_exclusive(): void
    {
        $this->expectException(TemplatePayloadConflictException::class);

        Mailbridge::transactional()
            ->to('a@example.com')
            ->template('welcome')
            ->templateId('direct-id')
            ->send();
    }

    public function test_missing_recipient_throws_specific_exception(): void
    {
        $this->expectException(MissingTransactionalRecipientException::class);

        Mailbridge::transactional()
            ->subject('Hello')
            ->text('Hi')
            ->send();
    }

    public function test_template_and_mailable_conflict_throws_specific_exception(): void
    {
        $this->expectException(TemplatePayloadConflictException::class);

        Mailbridge::transactional()
            ->to('a@example.com')
            ->template('welcome')
            ->send(new class extends Mailable {});
    }

    public function test_missing_template_mapping_throws_specific_exception(): void
    {
        Mailbridge::fake();
        $this->expectException(MissingTemplateMappingException::class);

        Mailbridge::transactional()
            ->to('a@example.com')
            ->template('welcome')
            ->send();
    }

    public function test_empty_template_mapping_is_treated_as_missing(): void
    {
        Mailbridge::fake();
        config()->set('mailbridge.templates.welcome.array', '');
        $this->expectException(MissingTemplateMappingException::class);

        Mailbridge::transactional()
            ->to('a@example.com')
            ->template('welcome')
            ->send();
    }

    public function test_data_for_merges_with_common_template_data(): void
    {
        Mailbridge::fake();
        config()->set('mailbridge.templates.welcome.array', 'array-welcome');

        Mailbridge::transactional()
            ->from('sender@example.com')
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
            ->from('sender@example.com')
            ->templateId('direct-id')
            ->to('a@example.com')
            ->data(['name' => 'Ash'])
            ->templateDataFor('array', ['name' => 'Ashraful'])
            ->send();

        $this->assertSame(['name' => 'Ashraful'], $this->lastArrayMessage()->data);
    }

    public function test_it_accepts_raw_data_attachments(): void
    {
        Mailbridge::fake();

        Mailbridge::transactional()
            ->from('sender@example.com')
            ->to('a@example.com')
            ->subject('Report')
            ->text('Attached')
            ->attachData('report-body', 'report.txt', 'text/plain')
            ->send();

        $this->assertSame([
            'content' => 'report-body',
            'name' => 'report.txt',
            'mime' => 'text/plain',
        ], $this->lastArrayMessage()->attachments[0]);
    }

    public function test_unused_provider_specific_data_is_ignored(): void
    {
        Mailbridge::fake();

        Mailbridge::transactional()
            ->from('sender@example.com')
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
