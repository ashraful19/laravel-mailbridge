<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\MarketingProvider;
use Ashraful19\LaravelMailbridge\Contracts\TransactionalProvider;
use Ashraful19\LaravelMailbridge\Data\Campaign;
use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Data\SubscriberRecord;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Support\ProviderFailureHandler;
use MailchimpMarketing\ApiClient as MarketingClient;
use MailchimpTransactional\ApiClient as TransactionalClient;
use Throwable;

final class MailchimpProvider extends AbstractProvider implements TransactionalProvider, MarketingProvider
{
    public function __construct(
        string $name,
        array $config,
        \Illuminate\Contracts\Container\Container $app,
        private readonly mixed $marketingClient = null,
        private readonly mixed $transactionalClient = null,
    ) {
        parent::__construct($name, $config, $app);
    }

    public function send(TransactionalMessage $message): SendResult
    {
        if ($this->transactionalClient === null && ! class_exists(TransactionalClient::class)) {
            throw $this->missingSdk();
        }

        $message = $this->normalizer()->normalize($message);

        try {
            $response = $message->isTemplateSend()
                ? $this->transactional()->messages->sendTemplate($this->transactionalTemplatePayload($message))
                : $this->transactional()->messages->send($this->transactionalPayload($message));

            $first = is_array($response) ? ($response[0] ?? []) : [];

            return new SendResult($this->name, $first['_id'] ?? null, ['status' => $first['status'] ?? null]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'transactional.send', $exception);
        }
    }

    public function subscribe(string $list, Subscriber $subscriber): MarketingResult
    {
        try {
            $response = $this->marketing()->lists->setListMember($list, $this->subscriberHash($subscriber->email), $this->subscriberPayload($subscriber));
            $this->applyTags($list, $subscriber->email, $subscriber->tags);

            return new MarketingResult($this->name, 'subscribe', ['list' => $list, 'subscriber_id' => $response->id ?? null]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscribe', $exception);
        }
    }

    public function unsubscribe(string $list, string $email): MarketingResult
    {
        try {
            $this->marketing()->lists->setListMember($list, $this->subscriberHash($email), [
                'email_address' => $email,
                'status' => 'unsubscribed',
            ]);

            return new MarketingResult($this->name, 'unsubscribe', ['list' => $list]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.unsubscribe', $exception);
        }
    }

    public function getSubscriber(string $email): ?SubscriberRecord
    {
        try {
            $record = $this->marketing()->lists->getListMember($this->requireConfig('audience_id'), $this->subscriberHash($email));

            return new SubscriberRecord($this->name, $email, (array) $record);
        } catch (\MailchimpMarketing\ApiException $exception) {
            if ((int) $exception->getCode() === 404) {
                return null;
            }

            ProviderFailureHandler::throw($this->name, 'marketing.subscriber.lookup', $exception);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscriber.lookup', $exception);
        }
    }

    public function deleteSubscriber(string $email): MarketingResult
    {
        try {
            $this->marketing()->lists->deleteListMemberPermanent($this->requireConfig('audience_id'), $this->subscriberHash($email));

            return new MarketingResult($this->name, 'delete_subscriber');
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscriber.delete', $exception);
        }
    }

    public function createCampaign(Campaign $campaign): MarketingResult
    {
        try {
            $response = $this->marketing()->campaigns->create($this->campaignPayload($campaign));
            $campaignId = $response->id ?? null;

            if ($campaignId !== null && $campaign->html !== null) {
                $this->marketing()->campaigns->setContent($campaignId, ['html' => $campaign->html]);
            }

            return new MarketingResult($this->name, 'campaign_create', ['campaign_id' => $campaignId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.create', $exception);
        }
    }

    public function sendCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $this->marketing()->campaigns->send((string) $campaignId);

            return new MarketingResult($this->name, 'campaign_send', ['campaign_id' => $campaignId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.send', $exception);
        }
    }

    public function scheduleCampaign(string|int $campaignId, \DateTimeInterface|string $when): MarketingResult
    {
        try {
            $scheduledAt = $when instanceof \DateTimeInterface ? $when->format(DATE_ATOM) : $when;
            $this->marketing()->campaigns->schedule((string) $campaignId, ['schedule_time' => $scheduledAt]);

            return new MarketingResult($this->name, 'campaign_schedule', ['campaign_id' => $campaignId, 'scheduled_at' => $scheduledAt]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.schedule', $exception);
        }
    }

    public function getCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $campaign = $this->marketing()->campaigns->get((string) $campaignId);

            return new MarketingResult($this->name, 'campaign_get', ['campaign_id' => $campaignId, 'campaign' => (array) $campaign]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.get', $exception);
        }
    }

    public function deleteCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $this->marketing()->campaigns->remove((string) $campaignId);

            return new MarketingResult($this->name, 'campaign_delete', ['campaign_id' => $campaignId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.delete', $exception);
        }
    }

    public function transactionalPayload(TransactionalMessage $message): array
    {
        return ['message' => $this->messagePayload($message)];
    }

    public function transactionalTemplatePayload(TransactionalMessage $message): array
    {
        return [
            'template_name' => (string) $message->templateId,
            'template_content' => [],
            'message' => $this->messagePayload($message),
        ];
    }

    public function subscriberPayload(Subscriber $subscriber): array
    {
        return array_filter([
            'email_address' => $subscriber->email,
            'status_if_new' => 'subscribed',
            'status' => 'subscribed',
            'merge_fields' => array_filter([
                'FNAME' => $subscriber->name,
                ...$subscriber->fields,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    public function campaignPayload(Campaign $campaign): array
    {
        return array_replace_recursive([
            'type' => $campaign->options['type'] ?? 'regular',
            'recipients' => [
                'list_id' => (string) ($campaign->lists[0] ?? $this->requireConfig('audience_id')),
            ],
            'settings' => array_filter([
                'subject_line' => $campaign->subject,
                'title' => $campaign->name,
                'from_name' => $campaign->fromName,
                'reply_to' => $campaign->fromEmail,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ], $campaign->options['mailchimp'] ?? []);
    }

    private function messagePayload(TransactionalMessage $message): array
    {
        return array_filter([
            'from_email' => $message->from->email,
            'from_name' => $message->from->name,
            'to' => [
                ...$this->recipients($message->to, 'to'),
                ...$this->recipients($message->cc, 'cc'),
                ...$this->recipients($message->bcc, 'bcc'),
            ],
            'headers' => $message->replyTo ? ['Reply-To' => $message->replyTo->email] : null,
            'subject' => $message->subject,
            'html' => $message->html,
            'text' => $message->text,
            'global_merge_vars' => array_map(fn (string $key, mixed $value): array => ['name' => $key, 'content' => $value], array_keys($message->data), $message->data),
            'tags' => $message->tags,
            'metadata' => $message->metadata,
            'attachments' => $this->attachments($message),
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    private function recipients(array $addresses, string $type): array
    {
        return array_map(fn ($address): array => [
            'email' => $address->email,
            'name' => $address->name,
            'type' => $type,
        ], $addresses);
    }

    private function attachments(TransactionalMessage $message): array
    {
        return array_map(fn (array $attachment): array => [
            'type' => $attachment['mime'] ?? 'application/octet-stream',
            'name' => $attachment['name'] ?? 'attachment',
            'content' => base64_encode((string) $attachment['content']),
        ], $message->attachments);
    }

    private function applyTags(string $list, string $email, array $tags): void
    {
        if ($tags === []) {
            return;
        }

        $this->marketing()->lists->updateListMemberTags($list, $this->subscriberHash($email), [
            'tags' => array_map(fn (string $tag): array => ['name' => $tag, 'status' => 'active'], array_values(array_unique($tags))),
        ]);
    }

    private function subscriberHash(string $email): string
    {
        return md5(strtolower($email));
    }

    private function marketing(): mixed
    {
        if ($this->marketingClient !== null) {
            return $this->marketingClient;
        }

        if (! class_exists(MarketingClient::class)) {
            throw $this->missingSdk();
        }

        $client = new MarketingClient();
        $client->setConfig([
            'apiKey' => $this->requireConfig('api_key'),
            'server' => $this->requireConfig('server'),
        ]);

        return $client;
    }

    private function transactional(): mixed
    {
        if ($this->transactionalClient !== null) {
            return $this->transactionalClient;
        }

        if (! class_exists(TransactionalClient::class)) {
            throw $this->missingSdk();
        }

        $client = new TransactionalClient();
        $client->setApiKey($this->requireConfig('transactional_api_key'));

        return $client;
    }
}
