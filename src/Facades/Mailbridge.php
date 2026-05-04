<?php

namespace Ashraful19\LaravelMailbridge\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Ashraful19\LaravelMailbridge\TransactionalMail transactional(?string $provider = null)
 * @method static \Ashraful19\LaravelMailbridge\MarketingMail marketing(?string $provider = null)
 * @method static \Ashraful19\LaravelMailbridge\Contracts\ProviderAdapter provider(string $provider)
 * @method static bool supports(string $provider, string $feature)
 * @method static void fake()
 * @method static void assertTransactionalSent(?string $template = null)
 * @method static void assertSubscribed(string $list, string $email)
 */
class Mailbridge extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mailbridge';
    }
}
