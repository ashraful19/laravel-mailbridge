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

    public static function redactString(string $value): string
    {
        $value = preg_replace('/(api[-_ ]?key|token|authorization|bearer)\s*[:=]\s*["\']?[^"\'\s,;]+/i', '$1=[redacted]', $value) ?? $value;

        return preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted-email]', $value) ?? $value;
    }
}
