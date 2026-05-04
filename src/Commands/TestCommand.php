<?php

namespace Ashraful19\LaravelMailbridge\Commands;

use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Facades\Mailbridge;
use Illuminate\Console\Command;

final class TestCommand extends Command
{
    protected $signature = 'mailbridge:test {lane : transactional|marketing} {email} {--list=signup}';

    protected $description = 'Send a test transactional email or marketing subscribe through Mailbridge.';

    public function handle(): int
    {
        $lane = (string) $this->argument('lane');
        $email = (string) $this->argument('email');

        if ($lane === 'transactional') {
            $result = Mailbridge::transactional()
                ->to($email)
                ->subject('Laravel Mailbridge test')
                ->text('Laravel Mailbridge test email.')
                ->send();

            $this->components->info("Sent via {$result->provider}: {$result->messageId}");

            return self::SUCCESS;
        }

        if ($lane === 'marketing') {
            $result = Mailbridge::marketing()
                ->list((string) $this->option('list'))
                ->subscribe(Subscriber::make($email));

            $this->components->info("Marketing {$result->operation} via {$result->provider}.");

            return self::SUCCESS;
        }

        $this->error('Lane must be [transactional] or [marketing].');

        return self::FAILURE;
    }
}
