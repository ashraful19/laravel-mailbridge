<?php

namespace Ashraful19\LaravelMailbridge\Support;

use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeException;
use Ashraful19\LaravelMailbridge\Exceptions\ProviderTransientException;
use Throwable;

final class ProviderFailureHandler
{
    public static function throw(string $provider, string $operation, Throwable $exception): never
    {
        if ($exception instanceof ProviderTransientException || $exception instanceof MailbridgeException) {
            throw $exception;
        }

        $status = self::statusCode($exception);
        $context = [
            'provider' => $provider,
            'operation' => $operation,
            'status' => $status,
            'error' => self::safeMessage($exception),
        ];

        if (self::isTransient($exception, $status)) {
            throw new ProviderTransientException(
                "Provider [{$provider}] failed transiently during [{$operation}].",
                $context,
                0,
                $exception,
            );
        }

        throw new MailbridgeException(
            "Provider [{$provider}] failed during [{$operation}].",
            $context,
            0,
            $exception,
        );
    }

    public static function isTransientStatus(int $status): bool
    {
        return $status === 408 || $status === 425 || $status === 429 || $status >= 500;
    }

    private static function statusCode(Throwable $exception): ?int
    {
        if (method_exists($exception, 'getStatusCode')) {
            return (int) $exception->getStatusCode();
        }

        if (method_exists($exception, 'getErrorCode')) {
            return (int) $exception->getErrorCode();
        }

        if (method_exists($exception, 'getResponse')) {
            $response = $exception->getResponse();

            if (is_object($response) && method_exists($response, 'getStatusCode')) {
                return (int) $response->getStatusCode();
            }
        }

        $code = (int) $exception->getCode();

        return $code > 0 ? $code : null;
    }

    private static function isTransient(Throwable $exception, ?int $status): bool
    {
        if ($status !== null && self::isTransientStatus($status)) {
            return true;
        }

        return str_contains(strtolower($exception::class), 'transport')
            || str_contains(strtolower($exception::class), 'rate')
            || str_contains(strtolower($exception->getMessage()), 'timeout')
            || str_contains(strtolower($exception->getMessage()), 'timed out');
    }

    private static function safeMessage(Throwable $exception): string
    {
        return substr(Redactor::redactString($exception->getMessage()), 0, 500);
    }
}
