<?php

namespace Ashraful19\LaravelMailbridge\Commands;

use Ashraful19\LaravelMailbridge\Data\Campaign;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeException;
use Ashraful19\LaravelMailbridge\Facades\Mailbridge;
use Ashraful19\LaravelMailbridge\Support\ProviderCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Throwable;

final class VerifyCommand extends Command
{
    protected $signature = 'mailbridge:verify
        {--provider= : Test only this provider}
        {--email= : Recipient email for all test sends}
        {--auto : Skip interactive prompts; skip providers with missing config}
        {--cleanup : Remove test data after creating it (unsubscribe, delete campaigns)}
        {--dry-run : Show what would be tested without calling APIs}';

    protected $description = 'Smoke-test real provider APIs through Mailbridge (not a unit test — hits real endpoints).';

    /** @var array<string, array{status: string, message: string}> */
    private array $results = [];

    private ?string $testEmail = null;

    private bool $isAuto = false;

    private bool $shouldCleanup = false;

    private bool $isDryRun = false;

    /** @var array<string, mixed> */
    private array $resolvedValues = [];

    public function handle(): int
    {
        $this->isAuto = (bool) $this->option('auto');
        $this->shouldCleanup = (bool) $this->option('cleanup');
        $this->isDryRun = (bool) $this->option('dry-run');

        $this->testEmail = $this->resolveTestEmail();
        if ($this->testEmail === null) {
            $this->components->error('A test email is required. Use --email=test@example.com or run interactively.');
            return self::FAILURE;
        }

        $providers = $this->providersToTest();
        if ($providers === []) {
            $this->components->error('No providers matched. Use --provider=brevo or configure providers in mailbridge.php.');
            return self::FAILURE;
        }

        if ($this->isDryRun) {
            $this->components->info('DRY RUN — no APIs will be called.');
        }

        $this->components->info("Testing with email: {$this->testEmail}");
        $this->newLine();

        foreach ($providers as $name => $metadata) {
            $this->verifyProvider($name, $metadata);
        }

        $this->newLine();
        $this->printSummary();

        return $this->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string, array>
     */
    private function providersToTest(): array
    {
        $all = ProviderCatalog::all();
        $runtime = (array) config('mailbridge.providers', []);
        $requested = $this->option('provider');

        foreach ($all as $name => $meta) {
            $appConfig = (array) ($runtime[$name] ?? []);
            $all[$name] = array_replace($meta, array_diff_key($appConfig, $meta));
        }

        if ($requested !== null) {
            $key = (string) $requested;
            return isset($all[$key]) ? [$key => $all[$key]] : [];
        }

        // Skip log/array drivers in real verification
        unset($all['log'], $all['array']);

        return $all;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function verifyProvider(string $name, array $metadata): void
    {
        $this->newLine();
        $this->line("<fg=cyan;options=bold>{$name}</>");

        if (in_array($name, ['log', 'array'], true)) {
            $this->line("  <fg=yellow>⚠</> Not a real API provider — skipped.");
            return;
        }

        $this->runProviderVerification($name, $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function runProviderVerification(string $name, array $metadata): void
    {
        $capabilities = (array) ($metadata['capabilities'] ?? []);

        // 1. SDK check
        $sdkOk = $this->checkSdk($name, $metadata);
        if (! $sdkOk) {
            return;
        }

        // 2. Credentials check + interactive fill
        $credsOk = $this->checkAndFillCredentials($name, $metadata);
        if (! $credsOk) {
            $this->line("  <fg=red>✗</> Credentials missing — skipped.");
            return;
        }

        if ($this->isDryRun) {
            $this->line("  <fg=blue>↷</> Dry run — skipping API calls for {$name}.");
        }

        // 3. Transactional raw
        if (in_array('transactional.raw', $capabilities, true)) {
            $this->testTransactionalRaw($name);
        }

        // 4. Transactional template
        if (in_array('transactional.templates', $capabilities, true)) {
            $this->testTransactionalTemplate($name);
        }

        // 5. Marketing subscribe / lookup / unsubscribe
        if (in_array('marketing.subscribers', $capabilities, true) || in_array('marketing.contacts', $capabilities, true)) {
            $this->testMarketingSubscribe($name);
        }

        // 6. Marketing campaigns
        if (in_array('marketing.campaigns', $capabilities, true)) {
            $this->testMarketingCampaign($name);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function checkSdk(string $name, array $metadata): bool
    {
        $sdk = $metadata['sdk'] ?? null;
        if ($sdk === null) {
            $this->line("  <fg=yellow>⚠</> No SDK required.");
            return true;
        }

        $packages = is_array($metadata['sdk_packages'] ?? null)
            ? array_keys($metadata['sdk_packages'])
            : [(string) $sdk];

        foreach ($packages as $package) {
            $class = $this->guessSdkClass($name, $package);
            if (! class_exists($class)) {
                $this->line("  <fg=red>✗</> SDK [{$package}] not installed. Run: {$metadata['install']}");
                return false;
            }
        }

        $version = $metadata['version'] ?? '?';
        $this->line("  <fg=green>✓</> SDK installed ({$version}).");
        return true;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function checkAndFillCredentials(string $name, array &$metadata): bool
    {
        $required = $this->requiredCredentials($name);
        $missing = [];

        foreach ($required as $key) {
            if (blank($metadata[$key] ?? null)) {
                $missing[] = $key;
            }
        }

        if ($missing === []) {
            $this->line("  <fg=green>✓</> Credentials OK.");
            return true;
        }

        if ($this->isAuto) {
            $this->line("  <fg=red>✗</> Missing: " . implode(', ', $missing));
            return false;
        }

        // Interactive: prompt for each missing credential
        foreach ($missing as $key) {
            $label = ucfirst(str_replace('_', ' ', $key));
            $value = $key === 'secret' || $key === 'secret_key' || str_contains($key, 'key') || str_contains($key, 'token')
                ? $this->secret("  {$name} {$label}")
                : $this->ask("  {$name} {$label}");

            if (blank($value)) {
                $this->line("  <fg=red>✗</> {$key} still missing — skipping provider.");
                return false;
            }

            // Temporarily set so the provider can be instantiated
            config()->set("mailbridge.providers.{$name}.{$key}", $value);
            $metadata[$key] = $value;
        }

        $this->line("  <fg=green>✓</> Credentials provided interactively.");
        return true;
    }

    private function testTransactionalRaw(string $provider): void
    {
        try {
            if ($this->isDryRun) {
                $this->line("  <fg=blue>↷</> Would send transactional raw via {$provider}.");
                return;
            }

            $result = Mailbridge::transactional($provider)
                ->from($this->testEmail, 'Mailbridge Verify')
                ->to($this->testEmail)
                ->subject("Mailbridge verify — {$provider} raw")
                ->text("This is a smoke-test email from Mailbridge provider verification.\nProvider: {$provider}\nTime: " . now()->toDateTimeString())
                ->send();

            $detail = $result->messageId ?? 'ok';
            $this->line("  <fg=green>✓</> Transactional raw → messageId: {$detail}");
            if ($result->metadata !== []) {
                $this->line("    <fg=gray>  metadata: " . json_encode($result->metadata) . "</>");
            }
            $this->record($provider, 'transactional_raw', 'pass', $detail);
        } catch (Throwable $e) {
            $error = $this->formatError($e);
            $this->line("  <fg=red>✗</> Transactional raw → {$error}");
            $this->record($provider, 'transactional_raw', 'fail', $error);
        }
    }

    private function testTransactionalTemplate(string $provider): void
    {
        $templateId = $this->resolveTestValue($provider, 'template_id');
        if ($templateId === null) {
            $this->line("  <fg=yellow>⊘</> Transactional template skipped (no test template ID).");
            return;
        }

        try {
            if ($this->isDryRun) {
                $this->line("  <fg=blue>↷</> Would send transactional template [{$templateId}] via {$provider}.");
                return;
            }

            $result = Mailbridge::transactional($provider)
                ->from($this->testEmail, 'Mailbridge Verify')
                ->to($this->testEmail)
                ->templateId($templateId)
                ->data(['name' => 'Mailbridge Tester'])
                ->send();

            $detail = $result->messageId ?? 'ok';
            $this->line("  <fg=green>✓</> Transactional template [{$templateId}] → messageId: {$detail}");
            if ($result->metadata !== []) {
                $this->line("    <fg=gray>  metadata: " . json_encode($result->metadata) . "</>");
            }
            $this->record($provider, 'transactional_template', 'pass', $detail);
        } catch (Throwable $e) {
            $error = $this->formatError($e);
            $this->line("  <fg=red>✗</> Transactional template → {$error}");
            $this->record($provider, 'transactional_template', 'fail', $error);
        }
    }

    private function testMarketingSubscribe(string $provider): void
    {
        $listId = $this->resolveTestValue($provider, 'list_id');
        if ($listId === null) {
            $this->line("  <fg=yellow>⊘</> Marketing subscribe skipped (no test list ID).");
            return;
        }

        $subscriber = Subscriber::make($this->testEmail)->name('Mailbridge Verify');

        try {
            if ($this->isDryRun) {
                $this->line("  <fg=blue>↷</> Would subscribe [{$this->testEmail}] to list [{$listId}] via {$provider}.");
                return;
            }

            $subResult = Mailbridge::marketing($provider)
                ->list((string) $listId)
                ->subscribe($subscriber);

            $this->line("  <fg=green>✓</> Marketing subscribe → {$subResult->operation}");
            if ($subResult->metadata !== []) {
                $this->line("    <fg=gray>  metadata: " . json_encode($subResult->metadata) . "</>");
            }
            $this->record($provider, 'marketing_subscribe', 'pass');

            // Lookup
            try {
                $record = Mailbridge::marketing($provider)->getSubscriber($this->testEmail);
                if ($record !== null) {
                    $this->line("  <fg=green>✓</> Marketing subscriber lookup → found");
                    $this->record($provider, 'marketing_lookup', 'pass');
                } else {
                    $this->line("  <fg=yellow>⚠</> Marketing subscriber lookup → not found");
                    $this->record($provider, 'marketing_lookup', 'warn', 'not found');
                }
            } catch (Throwable $e) {
                $error = $this->formatError($e);
                $this->line("  <fg=yellow>⚠</> Marketing subscriber lookup → {$error}");
                $this->record($provider, 'marketing_lookup', 'warn', $error);
            }

            // Cleanup
            if ($this->shouldCleanup) {
                try {
                    $unsubResult = Mailbridge::marketing($provider)
                        ->list((string) $listId)
                        ->unsubscribe($this->testEmail);

                    $this->line("  <fg=green>✓</> Marketing unsubscribe (cleanup) → {$unsubResult->operation}");
                    if ($unsubResult->metadata !== []) {
                        $this->line("    <fg=gray>  metadata: " . json_encode($unsubResult->metadata) . "</>");
                    }
                    $this->record($provider, 'marketing_cleanup', 'pass');
                } catch (Throwable $e) {
                    $error = $this->formatError($e);
                    $this->line("  <fg=yellow>⚠</> Marketing unsubscribe cleanup → {$error}");
                    $this->record($provider, 'marketing_cleanup', 'warn', $error);
                }
            }
        } catch (Throwable $e) {
            $error = $this->formatError($e);
            $this->line("  <fg=red>✗</> Marketing subscribe → {$error}");
            $this->record($provider, 'marketing_subscribe', 'fail', $error);
        }
    }

    private function testMarketingCampaign(string $provider): void
    {
        $listId = $this->resolveTestValue($provider, 'list_id');
        if ($listId === null) {
            $this->line("  <fg=yellow>⊘</> Marketing campaign skipped (no test list ID).");
            return;
        }

        try {
            if ($this->isDryRun) {
                $this->line("  <fg=blue>↷</> Would create campaign via {$provider}.");
                return;
            }

            $from = config('mailbridge.from.address') ?: $this->testEmail;
            $fromName = config('mailbridge.from.name') ?: 'Mailbridge Verify';

            $campaign = Campaign::make("Mailbridge verify {$provider}")
                ->subject('Mailbridge provider verification')
                ->html('<h1>Test</h1><p>This is a verification campaign.</p>')
                ->from($from, $fromName)
                ->list((string) $listId);

            $createResult = Mailbridge::marketing($provider)->createCampaign($campaign);
            $campaignId = $createResult->metadata['campaign_id'] ?? null;

            $this->line("  <fg=green>✓</> Campaign create → id: {$campaignId}");
            if ($createResult->metadata !== []) {
                $this->line("    <fg=gray>  metadata: " . json_encode($createResult->metadata) . "</>");
            }
            $this->record($provider, 'campaign_create', 'pass', (string) $campaignId);

            // Cleanup
            if ($this->shouldCleanup && $campaignId !== null) {
                try {
                    $deleteResult = Mailbridge::marketing($provider)->deleteCampaign($campaignId);
                    $this->line("  <fg=green>✓</> Campaign delete (cleanup) → {$deleteResult->operation}");
                    if ($deleteResult->metadata !== []) {
                        $this->line("    <fg=gray>  metadata: " . json_encode($deleteResult->metadata) . "</>");
                    }
                    $this->record($provider, 'campaign_cleanup', 'pass');
                } catch (Throwable $e) {
                    $error = $this->formatError($e);
                    $this->line("  <fg=yellow>⚠</> Campaign delete cleanup → {$error}");
                    $this->record($provider, 'campaign_cleanup', 'warn', $error);
                }
            }
        } catch (Throwable $e) {
            $error = $this->formatError($e);
            $this->line("  <fg=red>✗</> Campaign create → {$error}");
            $this->record($provider, 'campaign_create', 'fail', $error);
        }
    }

    private function resolveTestEmail(): ?string
    {
        $email = $this->option('email');
        if ($email !== null) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) ?: null;
        }

        $configured = config('mailbridge.verify_email') ?? config('mailbridge.from.address');

        if ($this->isAuto) {
            return $configured ?: null;
        }

        $default = $configured ?: 'test@example.com';
        $answer = $this->ask('Test recipient email for all sends', $default);

        return filter_var($answer, FILTER_VALIDATE_EMAIL) ?: null;
    }

    private function resolveTestValue(string $provider, string $type): ?string
    {
        $cacheKey = "{$provider}.{$type}";

        if (array_key_exists($cacheKey, $this->resolvedValues)) {
            return $this->resolvedValues[$cacheKey];
        }

        $envKey = strtoupper($provider) . '_TEST_' . strtoupper($type);
        $value = config('mailbridge.verify_test_values.' . strtolower($provider) . '.' . $type)
            ?? config($envKey);

        if ($value !== null && $value !== '') {
            $this->resolvedValues[$cacheKey] = $value;
            return $value;
        }

        if ($this->isAuto) {
            $this->resolvedValues[$cacheKey] = null;
            return null;
        }

        $label = match ($type) {
            'template_id' => 'test template ID (optional)',
            'list_id' => 'test list/audience ID (optional)',
            default => $type,
        };

        $answer = $this->ask("  {$provider} {$label}");
        $resolved = blank($answer) ? null : $answer;
        $this->resolvedValues[$cacheKey] = $resolved;

        return $resolved;
    }

    /**
     * @return list<string>
     */
    private function requiredCredentials(string $provider): array
    {
        $base = match ($provider) {
            'sendgrid' => ['api_key'],
            'ses' => ['key', 'secret', 'region'],
            'brevo' => ['api_key'],
            'mailersend' => ['api_key'],
            'resend' => ['api_key'],
            'postmark' => ['server_token'],
            'mailerlite' => ['api_key'],
            'mailchimp' => ['api_key', 'server'],
            'kit' => ['api_key'],
            'mailgun' => ['api_key', 'domain'],
            'mailjet' => ['api_key', 'secret_key'],
            default => ['api_key'],
        };

        // Mailchimp transactional uses a separate key
        if ($provider === 'mailchimp') {
            $base[] = 'transactional_api_key';
        }

        // SendGrid marketing campaigns need sender id
        if ($provider === 'sendgrid') {
            $base[] = 'marketing_sender_id';
        }

        return $base;
    }

private function guessSdkClass(string $provider, ?string $package = null): string
    {
        if ($package !== null) {
            return match ($package) {
                'mailchimp/marketing' => \MailchimpMarketing\ApiClient::class,
                'mailchimp/transactional' => \MailchimpTransactional\Api\Client::class,
                default => $this->guessSdkClass($provider),
            };
        }

        return match ($provider) {
            'sendgrid' => \SendGrid::class,
            'ses' => \Aws\Ses\SesClient::class,
            'brevo' => \Brevo\Client\Api\TransactionalEmailsApi::class,
            'mailersend' => \MailerSend\LaravelDriver\MailerSendTransport::class,
            'resend' => \Resend::class,
            'postmark' => \Postmark\PostmarkClient::class,
            'mailerlite' => \MailerLite\MailerLite::class,
            'mailchimp' => \MailchimpMarketing\ApiClient::class,
            'kit' => \ConvertKit_API\ConvertKit_API::class,
            'mailgun' => \Mailgun\Mailgun::class,
            'mailjet' => \Mailjet\Client::class,
            default => 'Nonexistent\\Class',
        };
    }

    private function hasFailures(): bool
    {
        foreach ($this->results as $r) {
            if ($r['status'] === 'fail') {
                return true;
            }
        }
        return false;
    }

    private function printSummary(): void
    {
        $this->components->info('Summary');
        $this->newLine();

        if ($this->results === []) {
            $this->line('  No tests were run.');
            return;
        }

        // Group by provider
        $grouped = [];
        foreach ($this->results as $r) {
            $grouped[$r['provider']][] = $r;
        }

        foreach ($grouped as $provider => $tests) {
            $pass = count(array_filter($tests, fn ($t) => $t['status'] === 'pass'));
            $fail = count(array_filter($tests, fn ($t) => $t['status'] === 'fail'));
            $warn = count(array_filter($tests, fn ($t) => $t['status'] === 'warn'));

            $color = $fail > 0 ? 'red' : ($warn > 0 ? 'yellow' : 'green');
            $icon = $fail > 0 ? '✗' : ($warn > 0 ? '⚠' : '✓');

            $this->line("  <fg={$color}>{$icon}</> {$provider}: {$pass} passed, {$fail} failed, {$warn} warned");

            foreach ($tests as $test) {
                if ($test['status'] === 'fail') {
                    $this->line("    <fg=red>  ✗</> {$test['test']}: {$test['detail']}");
                }
            }
        }
    }
}
