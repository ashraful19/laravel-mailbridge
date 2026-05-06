<?php

namespace Ashraful19\LaravelMailbridge\Exceptions;

final class MissingTransactionalContentException extends MailbridgeValidationException
{
    public static function make(): self
    {
        return new self('Transactional email needs html(), text(), a Laravel Mailable, template(), or templateId().');
    }
}
