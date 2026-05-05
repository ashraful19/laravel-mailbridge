<?php

namespace Ashraful19\LaravelMailbridge\Data;

final class Campaign
{
    public function __construct(
        public string $name,
        public ?string $subject = null,
        public ?string $html = null,
        public ?string $fromEmail = null,
        public ?string $fromName = null,
        public array $lists = [],
        public array $options = [],
    ) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function html(string $html): self
    {
        $this->html = $html;

        return $this;
    }

    public function from(string $email, ?string $name = null): self
    {
        $this->fromEmail = $email;
        $this->fromName = $name;

        return $this;
    }

    public function list(string|int $list): self
    {
        $this->lists[] = $list;

        return $this;
    }

    public function option(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }
}
