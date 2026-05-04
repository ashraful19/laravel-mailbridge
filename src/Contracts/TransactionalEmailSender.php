<?php

namespace Ashraful19\LaravelMailbridge\Contracts;

use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;

interface TransactionalEmailSender
{
    public function transactional(?string $provider = null): mixed;

    public function sendTransactional(TransactionalMessage $message, ?string $provider = null, bool $fallback = false): SendResult;
}
