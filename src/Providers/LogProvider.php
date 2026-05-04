<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\MarketingProvider;
use Ashraful19\LaravelMailbridge\Contracts\TransactionalProvider;
use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Support\Redactor;

final class LogProvider extends AbstractProvider implements TransactionalProvider, MarketingProvider
{
    public function send(TransactionalMessage $message): SendResult
    {
        $id = $this->messageId('log');
        $this->app['log']->info('Mailbridge transactional email accepted by log provider.', Redactor::redact([
            'provider' => $this->name,
            'message_id' => $id,
            'to_count' => count($message->to),
            'template' => $message->template,
            'template_id' => $message->templateId,
            'tags' => $message->tags,
            'metadata' => $message->metadata,
        ]));

        return new SendResult($this->name, $id);
    }

    public function subscribe(string $list, Subscriber $subscriber): MarketingResult
    {
        $this->app['log']->info('Mailbridge subscriber accepted by log provider.', Redactor::redact([
            'provider' => $this->name,
            'list' => $list,
            'subscriber' => $subscriber->toArray(),
        ]));

        return new MarketingResult($this->name, 'subscribe', ['list' => $list]);
    }
}
