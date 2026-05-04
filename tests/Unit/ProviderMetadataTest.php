<?php

namespace Ashraful19\LaravelMailbridge\Tests\Unit;

use Ashraful19\LaravelMailbridge\MailbridgeManager;
use Ashraful19\LaravelMailbridge\Tests\TestCase;

final class ProviderMetadataTest extends TestCase
{
    public function test_provider_install_commands_are_exact_pins(): void
    {
        $providers = app(MailbridgeManager::class)->providerMetadata();

        $this->assertSame('composer require getbrevo/brevo-php:2.0.14', $providers['brevo']['install']);
        $this->assertSame('composer require mailersend/laravel-driver:3.1.0', $providers['mailersend']['install']);
        $this->assertSame('composer require resend/resend-php:1.1.0', $providers['resend']['install']);
        $this->assertSame('composer require wildbit/postmark-php:7.0.0', $providers['postmark']['install']);
        $this->assertSame('composer require mailgun/mailgun-php:4.4.0 symfony/http-client nyholm/psr7', $providers['mailgun']['install']);
        $this->assertSame('composer require mailerlite/mailerlite-php:1.0.5', $providers['mailerlite']['install']);
    }
}
