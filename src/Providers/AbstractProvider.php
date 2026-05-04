<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Exceptions\MissingSdkException;
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
}
