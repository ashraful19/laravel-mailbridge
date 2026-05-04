<?php

namespace Ashraful19\LaravelMailbridge\Contracts;

interface ProviderAdapter
{
    public function name(): string;

    public function supports(string $feature): bool;
}
