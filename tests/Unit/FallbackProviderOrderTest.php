<?php

namespace Ashraful19\LaravelMailbridge\Tests\Unit;

use Ashraful19\LaravelMailbridge\MailbridgeManager;
use Ashraful19\LaravelMailbridge\Tests\TestCase;
use ReflectionMethod;

final class FallbackProviderOrderTest extends TestCase
{
    public function test_explicit_provider_does_not_use_fallback_by_default(): void
    {
        config()->set('mailbridge.fallbacks.transactional', ['postmark', 'resend']);

        $this->assertSame(['postmark'], $this->providersFor('transactional', 'postmark', false));
    }

    public function test_explicit_provider_with_fallback_tries_provider_once_then_next_fallbacks(): void
    {
        config()->set('mailbridge.fallbacks.transactional', ['postmark', 'resend']);

        $this->assertSame(['postmark', 'resend'], $this->providersFor('transactional', 'postmark', true));
    }

    private function providersFor(string $lane, ?string $provider, bool $fallback): array
    {
        $method = new ReflectionMethod(MailbridgeManager::class, 'providersFor');
        $method->setAccessible(true);

        return $method->invoke(app(MailbridgeManager::class), $lane, $provider, $fallback);
    }
}
