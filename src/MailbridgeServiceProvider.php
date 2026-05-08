<?php

namespace Ashraful19\LaravelMailbridge;

use Ashraful19\LaravelMailbridge\Commands\DoctorCommand;
use Ashraful19\LaravelMailbridge\Commands\InstallProviderCommand;
use Ashraful19\LaravelMailbridge\Commands\ListProvidersCommand;
use Ashraful19\LaravelMailbridge\Commands\RemoveProviderCommand;
use Ashraful19\LaravelMailbridge\Commands\TestCommand;
use Ashraful19\LaravelMailbridge\Commands\VerifyCommand;
use Ashraful19\LaravelMailbridge\Contracts\MarketingEmailSender;
use Ashraful19\LaravelMailbridge\Contracts\TransactionalEmailSender;
use Illuminate\Support\ServiceProvider;

class MailbridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mailbridge.php', 'mailbridge');

        $this->app->singleton(MailbridgeManager::class, fn ($app) => new MailbridgeManager($app));
        $this->app->alias(MailbridgeManager::class, 'mailbridge');
        $this->app->alias(MailbridgeManager::class, TransactionalEmailSender::class);
        $this->app->alias(MailbridgeManager::class, MarketingEmailSender::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/mailbridge.php' => config_path('mailbridge.php'),
        ], 'mailbridge-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                DoctorCommand::class,
                InstallProviderCommand::class,
                ListProvidersCommand::class,
                RemoveProviderCommand::class,
                TestCommand::class,
                VerifyCommand::class,
            ]);
        }
    }
}
