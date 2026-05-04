<?php

namespace Ashraful19\LaravelMailbridge\Data;

final readonly class Address
{
    public function __construct(
        public string $email,
        public ?string $name = null,
    ) {}

    public static function make(string $email, ?string $name = null): self
    {
        return new self($email, $name);
    }

    public function toArray(): array
    {
        return array_filter([
            'email' => $this->email,
            'name' => $this->name,
        ], fn ($value) => $value !== null);
    }
}
