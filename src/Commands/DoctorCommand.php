<?php

namespace Ashraful19\LaravelMailbridge\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;

final class DoctorCommand extends Command
{
    protected $signature = 'mailbridge:doctor';

    protected $description = 'Check Mailbridge configuration and provider SDK readiness.';

    public function handle(): int
    {
        $failed = false;
        $providers = config('mailbridge.providers', []);

        foreach ($providers as $name => $provider) {
            $sdk = $provider['sdk'] ?? null;

            if ($sdk) {
                $installed = $this->installedVersion($sdk);

                if ($installed === null) {
                    $failed = true;
                    $this->components->error("{$name}: SDK missing. Run: php artisan mailbridge:install {$name}");
                } elseif ($installed !== ($provider['version'] ?? null)) {
                    $failed = true;
                    $this->components->warn("{$name}: SDK version [{$installed}] differs from tested [{$provider['version']}].");
                } else {
                    $this->components->info("{$name}: SDK {$installed} OK.");
                }
            }

            foreach (['api_key', 'server_token', 'domain'] as $key) {
                if (Arr::exists($provider, $key) && blank($provider[$key])) {
                    $failed = true;
                    $this->components->warn("{$name}: missing {$key}.");
                }
            }
        }

        foreach (['transactional', 'marketing'] as $lane) {
            $default = config("mailbridge.default.{$lane}");

            if (! isset($providers[$default])) {
                $failed = true;
                $this->components->error("Default {$lane} provider [{$default}] is not configured.");
            }

            foreach (config("mailbridge.fallbacks.{$lane}", []) as $fallback) {
                if (! isset($providers[$fallback])) {
                    $failed = true;
                    $this->components->error("Fallback {$lane} provider [{$fallback}] is not configured.");
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function installedVersion(string $package): ?string
    {
        $result = Process::run("composer show {$package} --format=json");

        if (! $result->successful()) {
            return null;
        }

        $json = json_decode($result->output(), true);

        return is_array($json) ? ($json['version'] ?? null) : null;
    }
}
