<?php

namespace Ashraful19\LaravelMailbridge\Tests\Unit;

use Ashraful19\LaravelMailbridge\Tests\TestCase;

final class VerifyCommandTest extends TestCase
{
    public function test_verify_skips_providers_when_auto_and_credentials_missing(): void
    {
        config()->set('mailbridge.providers', [
            'brevo' => ['api_key' => null],
        ]);

        $this->artisan('mailbridge:verify', [
            '--provider' => 'brevo',
            '--email' => 'test@example.com',
            '--auto' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Credentials missing');
    }

    public function test_verify_dry_run_does_not_call_apis(): void
    {
        config()->set('mailbridge.providers', [
            'brevo' => ['api_key' => 'test-key'],
        ]);

        $this->artisan('mailbridge:verify', [
            '--provider' => 'brevo',
            '--email' => 'test@example.com',
            '--auto' => true,
            '--dry-run' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Dry run');
    }

    public function test_verify_requires_valid_email(): void
    {
        $this->artisan('mailbridge:verify', [
            '--provider' => 'brevo',
            '--email' => 'not-an-email',
            '--auto' => true,
        ])
            ->assertFailed()
            ->expectsOutputToContain('test email is required');
    }

    public function test_verify_shows_summary_for_passing_checks(): void
    {
        config()->set('mailbridge.providers', [
            'brevo' => ['api_key' => 'test-key'],
        ]);

        $this->artisan('mailbridge:verify', [
            '--provider' => 'brevo',
            '--email' => 'test@example.com',
            '--auto' => true,
            '--dry-run' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Summary');
    }

    public function test_verify_dry_run_lists_capabilities_without_calls(): void
    {
        config()->set('mailbridge.providers', [
            'sendgrid' => ['api_key' => 'test-key', 'marketing_sender_id' => '123'],
        ]);

        $this->artisan('mailbridge:verify', [
            '--provider' => 'sendgrid',
            '--email' => 'test@example.com',
            '--auto' => true,
            '--dry-run' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Dry run');
    }
}
