<?php

namespace Ashraful19\LaravelMailbridge\Data;

final class Subscriber
{
    public function __construct(
        public string $email,
        public ?string $name = null,
        public array $fields = [],
        public array $tags = [],
    ) {}

    public static function make(string $email): self
    {
        return new self($email);
    }

    public function name(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function field(string $key, mixed $value): self
    {
        $this->fields[$key] = $value;

        return $this;
    }

    public function tag(string $tag): self
    {
        $this->tags[] = $tag;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'name' => $this->name,
            'fields' => $this->fields,
            'tags' => array_values(array_unique($this->tags)),
        ];
    }
}
