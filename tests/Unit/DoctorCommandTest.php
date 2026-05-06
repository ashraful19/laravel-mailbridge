<?php

namespace Ashraful19\LaravelMailbridge\Tests\Unit;

use Ashraful19\LaravelMailbridge\Tests\TestCase;

final class DoctorCommandTest extends TestCase
{
    public function test_doctor_flags_missing_sendgrid_marketing_sender_id(): void
    {
        config()->set('mailbridge.providers', [
            'sendgrid' => [
                'api_key' => 'key',
                'marketing_sender_id' => null,
            ],
        ]);
        config()->set('mailbridge.default.transactional', 'sendgrid');
        config()->set('mailbridge.default.marketing', 'sendgrid');

        $this->artisan('mailbridge:doctor')
            ->assertExitCode(1);
    }

    public function test_doctor_flags_missing_ses_runtime_keys(): void
    {
        config()->set('mailbridge.providers', [
            'ses' => [
                'key' => null,
                'secret' => null,
                'region' => null,
            ],
        ]);
        config()->set('mailbridge.default.transactional', 'ses');
        config()->set('mailbridge.default.marketing', 'ses');

        $this->artisan('mailbridge:doctor')
            ->assertExitCode(1);
    }

    public function test_doctor_flags_missing_mailjet_secret_key(): void
    {
        config()->set('mailbridge.providers', [
            'mailjet' => [
                'api_key' => 'key',
                'secret_key' => null,
            ],
        ]);
        config()->set('mailbridge.default.transactional', 'mailjet');
        config()->set('mailbridge.default.marketing', 'mailjet');

        $this->artisan('mailbridge:doctor')
            ->assertExitCode(1);
    }
}
