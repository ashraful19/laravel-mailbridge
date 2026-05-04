<?php

namespace Ashraful19\LaravelMailbridge\Data;

use Illuminate\Mail\Mailable;

final class TransactionalMessage
{
    /** @var list<Address> */
    public array $to = [];

    /** @var list<Address> */
    public array $cc = [];

    /** @var list<Address> */
    public array $bcc = [];

    public ?Address $from = null;

    public ?Address $replyTo = null;

    public ?string $subject = null;

    public ?string $html = null;

    public ?string $text = null;

    public ?Mailable $mailable = null;

    public ?string $template = null;

    public string|int|null $templateId = null;

    public array $data = [];

    /** @var array<string, array> */
    public array $providerData = [];

    public array $attachments = [];

    public array $tags = [];

    public array $metadata = [];

    public array $providerOptions = [];

    public function isTemplateSend(): bool
    {
        return $this->template !== null || $this->templateId !== null;
    }
}
