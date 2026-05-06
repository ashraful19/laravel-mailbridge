<?php

namespace Ashraful19\LaravelMailbridge\Exceptions;

final class UnknownProviderException extends MailbridgeValidationException
{
    public static function make(string $provider): self
    {
        return new self("Unknown Mailbridge provider [{$provider}].");
    }
}
