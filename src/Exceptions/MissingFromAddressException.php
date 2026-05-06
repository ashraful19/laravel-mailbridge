<?php

namespace Ashraful19\LaravelMailbridge\Exceptions;

final class MissingFromAddressException extends MailbridgeValidationException
{
    public static function make(): self
    {
        return new self('Transactional email needs a from address. Configure MAIL_FROM_ADDRESS or call from().');
    }
}
