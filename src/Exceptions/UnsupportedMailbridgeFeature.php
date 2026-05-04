<?php

namespace Ashraful19\LaravelMailbridge\Exceptions;

final class UnsupportedMailbridgeFeature extends MailbridgeException
{
    public static function make(string $provider, string $feature): self
    {
        return new self(
            "Provider [{$provider}] does not support [{$feature}].",
            ['provider' => $provider, 'feature' => $feature],
        );
    }
}
