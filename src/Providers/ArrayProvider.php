<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\MarketingProvider;
use Ashraful19\LaravelMailbridge\Contracts\TransactionalProvider;
use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;

final class ArrayProvider extends AbstractProvider implements TransactionalProvider, MarketingProvider
{
    public array $transactional = [];

    public array $subscribers = [];

    public function send(TransactionalMessage $message): SendResult
    {
        $id = $this->messageId('array');
        $this->transactional[] = ['id' => $id, 'message' => clone $message];

        return new SendResult($this->name, $id);
    }

    public function subscribe(string $list, Subscriber $subscriber): MarketingResult
    {
        $this->subscribers[$list][$subscriber->email] = $subscriber->toArray();

        return new MarketingResult($this->name, 'subscribe', ['list' => $list]);
    }
}
