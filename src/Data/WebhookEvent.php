<?php

namespace Ashraful19\LaravelMailbridge\Data;

final readonly class WebhookEvent
{
    public function __construct(
        public string $provider,
        public string $type,
        public ?string $messageId = null,
        public ?string $email = null,
        public array $metadata = [],
    ) {}
}
