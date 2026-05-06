<?php

namespace Ashraful19\LaravelMailbridge\Exceptions;

final class TemplatePayloadConflictException extends MailbridgeValidationException
{
    public static function templateAndTemplateId(): self
    {
        return new self('Use either template() or templateId(), not both.');
    }

    public static function templateWithMailable(): self
    {
        return new self('Template send cannot also send a Laravel Mailable.');
    }
}
