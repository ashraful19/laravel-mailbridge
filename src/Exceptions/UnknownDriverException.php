<?php

namespace Ashraful19\LaravelMailbridge\Exceptions;

final class UnknownDriverException extends MailbridgeValidationException
{
    public static function make(string $driver): self
    {
        return new self("Unknown Mailbridge driver [{$driver}].");
    }
}
