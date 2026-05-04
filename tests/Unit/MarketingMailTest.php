<?php

namespace Ashraful19\LaravelMailbridge\Tests\Unit;

use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Facades\Mailbridge;
use Ashraful19\LaravelMailbridge\Tests\TestCase;

final class MarketingMailTest extends TestCase
{
    public function test_it_subscribes_through_array_provider(): void
    {
        Mailbridge::fake();

        Mailbridge::marketing()
            ->list('signup')
            ->subscribe(Subscriber::make('a@example.com')->name('A'));

        Mailbridge::assertSubscribed('signup', 'a@example.com');
    }
}
