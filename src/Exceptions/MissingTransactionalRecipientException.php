<?php

namespace Ashraful19\LaravelMailbridge\Exceptions;

final class MissingTransactionalRecipientException extends MailbridgeValidationException
{
    public static function make(): self
    {
        return new self('Transactional email needs at least one recipient.');
    }
}
