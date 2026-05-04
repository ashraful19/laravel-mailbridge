<?php

namespace Ashraful19\LaravelMailbridge\Tests;

use Ashraful19\LaravelMailbridge\MailbridgeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [MailbridgeServiceProvider::class];
    }
}
