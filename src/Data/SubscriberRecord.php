<?php

namespace Ashraful19\LaravelMailbridge\Data;

final readonly class SubscriberRecord
{
    public function __construct(
        public string $provider,
        public string $email,
        public array $data = [],
    ) {}
}
