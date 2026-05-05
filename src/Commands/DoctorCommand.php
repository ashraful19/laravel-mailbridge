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
            $sdkPackages = $provider['sdk_packages'] ?? null;
            $sdkPackages = is_array($sdkPackages)
                ? $sdkPackages
                : array_filter([(string) ($provider['sdk'] ?? '') => $provider['version'] ?? null]);

            foreach ($sdkPackages as $sdk => $version) {
                if ($sdk === '') {
                    continue;
                }

                $installed = $this->installedVersion((string) $sdk);

                if ($installed === null) {
                    $failed = true;
                    $this->components->error("{$name}: SDK [{$sdk}] missing. Run: php artisan mailbridge:install {$name}");
                } elseif ($installed !== $version) {
                    $failed = true;
                    $this->components->warn("{$name}: SDK [{$sdk}] version [{$installed}] differs from tested [{$version}].");
                } else {
                    $this->components->info("{$name}: SDK [{$sdk}] {$installed} OK.");
                }
            }

            foreach (['api_key', 'server_token', 'domain', 'server', 'audience_id', 'transactional_api_key'] as $key) {
                if (Arr::exists($provider, $key) && blank($provider[$key])) {
                    $failed = true;
                    $this->components->warn("{$name}: missing {$key}.");
                }
            }

            if ($name === 'sendgrid' && in_array('marketing.campaigns', (array) ($provider['capabilities'] ?? []), true) && blank($provider['marketing_sender_id'] ?? null)) {
                $failed = true;
                $this->components->warn("{$name}: missing marketing_sender_id.");
            }

            if ($name === 'ses') {
                foreach (['key', 'secret', 'region'] as $key) {
                    if (blank($provider[$key] ?? null)) {
                        $failed = true;
                        $this->components->warn("{$name}: missing {$key}.");
                    }
                }
            }

            if ($name === 'mailjet' && blank($provider['secret_key'] ?? null)) {
                $failed = true;
                $this->components->warn("{$name}: missing secret_key.");
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
