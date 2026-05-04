<?php

namespace Ashraful19\LaravelMailbridge\Support;

final class Redactor
{
    private const SENSITIVE_KEYS = [
        'api_key',
        'apikey',
        'authorization',
        'body',
        'email',
        'html',
        'message',
        'name',
        'password',
        'payload',
        'subscriber',
        'template_data',
        'text',
        'token',
    ];

    public static function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $context[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $context[$key] = self::redact($value);
            }
        }

        return $context;
    }
}
