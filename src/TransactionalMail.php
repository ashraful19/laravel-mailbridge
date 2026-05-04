<?php

namespace Ashraful19\LaravelMailbridge;

use Ashraful19\LaravelMailbridge\Data\Address;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeValidationException;
use Illuminate\Mail\Mailable;

final class TransactionalMail
{
    private TransactionalMessage $message;

    public function __construct(
        private readonly MailbridgeManager $manager,
        private ?string $provider = null,
        private bool $fallback = false,
    ) {
        $this->message = new TransactionalMessage();
    }

    public function provider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function withFallback(bool $fallback = true): self
    {
        $this->fallback = $fallback;

        return $this;
    }

    public function withoutFallback(): self
    {
        $this->fallback = false;

        return $this;
    }

    public function to(string $email, ?string $name = null): self
    {
        $this->message->to[] = Address::make($email, $name);

        return $this;
    }

    public function cc(string $email, ?string $name = null): self
    {
        $this->message->cc[] = Address::make($email, $name);

        return $this;
    }

    public function bcc(string $email, ?string $name = null): self
    {
        $this->message->bcc[] = Address::make($email, $name);

        return $this;
    }

    public function from(string $email, ?string $name = null): self
    {
        $this->message->from = Address::make($email, $name);

        return $this;
    }

    public function replyTo(string $email, ?string $name = null): self
    {
        $this->message->replyTo = Address::make($email, $name);

        return $this;
    }

    public function subject(string $subject): self
    {
        $this->message->subject = $subject;

        return $this;
    }

    public function html(string $html): self
    {
        $this->message->html = $html;

        return $this;
    }

    public function text(string $text): self
    {
        $this->message->text = $text;

        return $this;
    }

    public function template(string $template): self
    {
        $this->message->template = $template;

        return $this;
    }

    public function templateId(string|int $templateId): self
    {
        $this->message->templateId = $templateId;

        return $this;
    }

    public function data(array $data): self
    {
        $this->message->data = array_replace_recursive($this->message->data, $data);

        return $this;
    }

    public function dataFor(string $provider, array $data): self
    {
        $this->message->providerData[$provider] = array_replace_recursive(
            $this->message->providerData[$provider] ?? [],
            $data,
        );

        return $this;
    }

    public function templateDataFor(string $provider, array $data): self
    {
        return $this->dataFor($provider, $data);
    }

    public function tag(string $tag): self
    {
        $this->message->tags[] = $tag;

        return $this;
    }

    public function metadata(string $key, mixed $value): self
    {
        $this->message->metadata[$key] = $value;

        return $this;
    }

    public function withProviderOptions(array $options): self
    {
        $this->message->providerOptions = array_replace_recursive($this->message->providerOptions, $options);

        return $this;
    }

    public function send(?Mailable $mailable = null): SendResult
    {
        if ($mailable !== null) {
            $this->message->mailable = $mailable;
        }

        $this->validate();

        return $this->manager->sendTransactional($this->message, $this->provider, $this->fallback);
    }

    private function validate(): void
    {
        if ($this->message->to === []) {
            throw new MailbridgeValidationException('Transactional email needs at least one recipient.');
        }

        if ($this->message->template !== null && $this->message->templateId !== null) {
            throw new MailbridgeValidationException('Use either template() or templateId(), not both.');
        }

        if (($this->message->template !== null || $this->message->templateId !== null) && $this->message->mailable !== null) {
            throw new MailbridgeValidationException('Template send cannot also send a Laravel Mailable.');
        }

        foreach (array_keys($this->message->providerData) as $provider) {
            $this->manager->providerConfig($provider);
        }
    }
}
