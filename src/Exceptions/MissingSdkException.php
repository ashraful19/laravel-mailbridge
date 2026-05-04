<?php

namespace Ashraful19\LaravelMailbridge\Exceptions;

final class MissingSdkException extends MailbridgeException
{
    public static function forProvider(string $provider, ?string $installCommand): self
    {
        $suffix = $installCommand ? " Run: php artisan mailbridge:install {$provider}" : '';

        return new self(
            ucfirst($provider) . " SDK missing.{$suffix}",
            ['provider' => $provider, 'install_command' => $installCommand],
        );
    }
}
