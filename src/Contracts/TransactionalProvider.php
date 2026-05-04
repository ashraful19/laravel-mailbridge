<?php

namespace Ashraful19\LaravelMailbridge\Contracts;

use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;

interface TransactionalProvider extends ProviderAdapter
{
    public function send(TransactionalMessage $message): SendResult;
}
