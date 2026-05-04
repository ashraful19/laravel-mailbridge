<?php

namespace Ashraful19\LaravelMailbridge\Data;

final readonly class MarketingResult
{
    public function __construct(
        public string $provider,
        public string $operation,
        public array $metadata = [],
    ) {}
}
