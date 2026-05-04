<?php

namespace Ashraful19\LaravelMailbridge\Data;

final readonly class SendResult
{
    public function __construct(
        public string $provider,
        public ?string $messageId = null,
        public array $metadata = [],
    ) {}
}
