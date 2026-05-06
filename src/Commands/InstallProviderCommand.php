<?php

namespace Ashraful19\LaravelMailbridge\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use function Laravel\Prompts\multiselect;

final class InstallProviderCommand extends Command
{
    protected $signature = 'mailbridge:install {provider? : Provider name}';

    protected $description = 'Install the exact tested SDK version for a Mailbridge provider.';

    public function handle(): int
    {
        $providers = $this->selectedProviders();
        $failed = false;

        foreach ($providers as $provider) {
            if (! $this->installProvider($provider)) {
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function installProvider(string $provider): bool
    {
        $config = config("mailbridge.providers.{$provider}");

        if (! is_array($config)) {
            $this->error("Unknown provider [{$provider}].");

            return false;
        }

        if (empty($config['install'])) {
            $this->info("Provider [{$provider}] does not need an external SDK.");

            return true;
        }

        $this->components->info("Running: {$config['install']}");
        $pending = Process::tty(false);
        $pending = method_exists($pending, 'forever')
            ? $pending->forever()
            : $pending->timeout(PHP_INT_MAX);
        $result = $pending->run($config['install']);

        $this->output->write($result->output());
        $this->output->write($result->errorOutput());

        return $result->successful();
    }

    /**
     * @return list<string>
     */
    private function selectedProviders(): array
    {
        $provider = $this->argument('provider');

        if (is_string($provider) && $provider !== '') {
            return [$provider];
        }

        $providers = collect(config('mailbridge.providers', []))
            ->filter(fn (array $provider): bool => filled($provider['install'] ?? null))
            ->mapWithKeys(fn (array $provider, string $name): array => [
                $name => "{$name} ({$provider['sdk']}:{$provider['version']})",
            ])
            ->all();

        return multiselect(
            label: 'Select Mailbridge provider SDKs to install',
            options: $providers,
            required: true,
        );
    }
}
