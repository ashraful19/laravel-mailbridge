<?php

namespace Ashraful19\LaravelMailbridge\Exceptions;

final class MissingTemplateMappingException extends MailbridgeValidationException
{
    public static function forProvider(string $template, string $provider): self
    {
        return new self("Missing template mapping [{$template}] for provider [{$provider}].");
    }
}
