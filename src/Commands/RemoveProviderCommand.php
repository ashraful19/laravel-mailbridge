<?php

namespace Ashraful19\LaravelMailbridge\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

final class RemoveProviderCommand extends Command
{
    protected $signature = 'mailbridge:remove {provider : Provider name}';

    protected $description = 'Remove a Mailbridge provider SDK dependency.';

    public function handle(): int
    {
        $provider = (string) $this->argument('provider');
        $sdk = config("mailbridge.providers.{$provider}.sdk");

        if (! $sdk) {
            $this->error("Provider [{$provider}] has no removable SDK configured.");

            return self::FAILURE;
        }

        $command = "composer remove {$sdk}";
        $this->components->info("Running: {$command}");
        $result = Process::timeout(null)->tty(false)->run($command);

        $this->output->write($result->output());
        $this->output->write($result->errorOutput());

        return $result->successful() ? self::SUCCESS : self::FAILURE;
    }
}
