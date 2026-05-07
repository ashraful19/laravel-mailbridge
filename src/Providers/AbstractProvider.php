<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Exceptions\MissingSdkException;
use Ashraful19\LaravelMailbridge\Support\TransactionalMessageNormalizer;
use Illuminate\Contracts\Container\Container;

abstract class AbstractProvider
{
    public function __construct(
        protected readonly string $name,
        protected readonly array $config,
        protected readonly Container $app,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->config['capabilities'] ?? [], true);
    }

    protected function missingSdk(): MissingSdkException
    {
        return MissingSdkException::forProvider($this->name, $this->config['install'] ?? null);
    }

    protected function messageId(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(8));
    }

    protected function normalizer(): TransactionalMessageNormalizer
    {
        return new TransactionalMessageNormalizer($this->app);
    }

    protected function campaignFrom(?string $email, ?string $name): array
    {
        return [
            'email' => $email ?? $this->config['from']['address'] ?? $this->app['config']->get('mailbridge.from.address'),
            'name' => $name ?? $this->config['from']['name'] ?? $this->app['config']->get('mailbridge.from.name'),
        ];
    }

    protected function requireConfig(string $key): string
    {
        $value = $this->config[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new \Ashraful19\LaravelMailbridge\Exceptions\MailbridgeValidationException("Provider [{$this->name}] is missing required config [{$key}].");
        }

        return $value;
    }
}
