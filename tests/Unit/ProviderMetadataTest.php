<?php

namespace Ashraful19\LaravelMailbridge\Tests\Unit;

use Ashraful19\LaravelMailbridge\MailbridgeManager;
use Ashraful19\LaravelMailbridge\Tests\TestCase;

final class ProviderMetadataTest extends TestCase
{
    public function test_provider_install_commands_are_exact_pins(): void
    {
        $providers = app(MailbridgeManager::class)->providerMetadata();

        $this->assertSame('composer require sendgrid/sendgrid:8.1.11', $providers['sendgrid']['install']);
        $this->assertSame('composer require aws/aws-sdk-php:3.379.0', $providers['ses']['install']);
        $this->assertSame('composer require getbrevo/brevo-php:2.0.14', $providers['brevo']['install']);
        $this->assertSame('composer require mailersend/laravel-driver:3.1.0', $providers['mailersend']['install']);
        $this->assertSame('composer require resend/resend-php:1.1.0', $providers['resend']['install']);
        $this->assertSame('composer require wildbit/postmark-php:7.0.0', $providers['postmark']['install']);
        $this->assertSame('composer require mailerlite/mailerlite-php:1.0.5', $providers['mailerlite']['install']);
        $this->assertSame('composer require mailchimp/marketing:3.0.80 mailchimp/transactional:1.4.1', $providers['mailchimp']['install']);
        $this->assertSame('composer require mailgun/mailgun-php:4.4.0 symfony/http-client:7.4.8 nyholm/psr7:1.8.2', $providers['mailgun']['install']);
        $this->assertSame('composer require mailjet/mailjet-apiv3-php:1.6.6', $providers['mailjet']['install']);
    }
}
