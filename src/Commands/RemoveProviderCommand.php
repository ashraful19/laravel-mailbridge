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
        $config = config("mailbridge.providers.{$provider}", []);
        $sdks = array_keys((array) ($config['sdk_packages'] ?? []));
        $sdks = $sdks !== [] ? $sdks : array_filter([(string) ($config['sdk'] ?? '')]);

        if ($sdks === []) {
            $this->error("Provider [{$provider}] has no removable SDK configured.");

            return self::FAILURE;
        }

        $command = 'composer remove ' . implode(' ', $sdks);
        $this->components->info("Running: {$command}");
        $pending = Process::tty(false);
        $pending = method_exists($pending, 'forever')
            ? $pending->forever()
            : $pending->timeout(PHP_INT_MAX);
        $result = $pending->run($command);

        $this->output->write($result->output());
        $this->output->write($result->errorOutput());

        return $result->successful() ? self::SUCCESS : self::FAILURE;
    }
}
