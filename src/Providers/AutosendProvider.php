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
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

final class AutosendProvider extends AbstractProvider implements TransactionalProvider, MarketingProvider
{
    private const BASE_URL = 'https://api.autosend.com/v1';

    public function __construct(
        string $name,
        array $config,
        Container $app,
        private readonly mixed $client = null,
    ) {
        parent::__construct($name, $config, $app);
    }

    public function send(TransactionalMessage $message): SendResult
    {
        $message = $this->normalizer()->normalize($message, $this->config);
        $payload = $this->payload($message);

        try {
            $response = $this->http()->post('/mails/send', $payload);
            $data = $response->json();

            if (! ($data['success'] ?? false)) {
                throw new \RuntimeException($data['message'] ?? 'AutoSend API returned failure.');
            }

            return new SendResult($this->name, $data['data']['emailId'] ?? null, $data['data'] ?? []);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'transactional.send', $exception);
        }
    }

    public function subscribe(string $list, Subscriber $subscriber): MarketingResult
    {
        $payload = $this->subscriberPayload($subscriber);
        $payload['listIds'] = [$list];

        try {
            $response = $this->http()->post('/contacts/email', $payload);
            $data = $response->json();

            if (! ($data['success'] ?? false)) {
                throw new \RuntimeException($data['message'] ?? 'AutoSend API returned failure.');
            }

            return new MarketingResult($this->name, 'subscribe', ['list' => $list]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscribe', $exception);
        }
    }

    public function unsubscribe(string $list, string $email): MarketingResult
    {
        try {
            $this->http()->post("/contact-lists/{$list}/contacts/remove", [
                'emails' => [$email],
            ]);

            return new MarketingResult($this->name, 'unsubscribe', ['list' => $list]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.unsubscribe', $exception);
        }
    }

    public function getSubscriber(string $email): ?SubscriberRecord
    {
        try {
            $response = $this->http()->post('/contacts/search/emails', [
                'emails' => [$email],
            ]);
            $data = $response->json();

            if (! ($data['success'] ?? false)) {
                throw new \RuntimeException($data['message'] ?? 'AutoSend API returned failure.');
            }

            $contacts = $data['data']['contacts'] ?? [];

            if ($contacts === []) {
                return null;
            }

            return new SubscriberRecord($this->name, $email, $contacts[0]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscriber.lookup', $exception);
        }
    }

    public function deleteSubscriber(string $email): MarketingResult
    {
        try {
            $lookup = $this->http()->post('/contacts/search/emails', [
                'emails' => [$email],
            ]);
            $lookupData = $lookup->json();
            $contacts = $lookupData['data']['contacts'] ?? [];

            if ($contacts === []) {
                return new MarketingResult($this->name, 'delete_subscriber', ['email' => $email, 'deleted' => false]);
            }

            $contactId = $contacts[0]['id'];
            $this->http()->delete("/contacts/{$contactId}");

            return new MarketingResult($this->name, 'delete_subscriber', ['email' => $email, 'deleted' => true]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscriber.delete', $exception);
        }
    }

    public function createCampaign(Campaign $campaign): MarketingResult
    {
        try {
            $response = $this->http()->post('/campaigns', $this->campaignPayload($campaign));
            $data = $response->json();

            if (! ($data['success'] ?? false)) {
                throw new \RuntimeException($data['message'] ?? 'AutoSend API returned failure.');
            }

            return new MarketingResult($this->name, 'campaign_create', [
                'campaign_id' => $data['data']['id'] ?? null,
            ]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.create', $exception);
        }
    }

    public function sendCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $this->http()->patch("/campaigns/{$campaignId}", [
                'sendNow' => true,
            ]);

            return new MarketingResult($this->name, 'campaign_send', ['campaign_id' => $campaignId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.send', $exception);
        }
    }

    public function scheduleCampaign(string|int $campaignId, \DateTimeInterface|string $when): MarketingResult
    {
        $scheduledAt = $when instanceof \DateTimeInterface
            ? $when->format(DATE_ATOM)
            : $when;

        try {
            $this->http()->patch("/campaigns/{$campaignId}", [
                'scheduledAt' => $scheduledAt,
                'sendMode' => 'scheduled',
            ]);

            return new MarketingResult($this->name, 'campaign_schedule', ['campaign_id' => $campaignId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.schedule', $exception);
        }
    }

    public function getCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $response = $this->http()->get("/campaigns/{$campaignId}");
            $data = $response->json();

            if (! ($data['success'] ?? false)) {
                throw new \RuntimeException($data['message'] ?? 'AutoSend API returned failure.');
            }

            return new MarketingResult($this->name, 'campaign_get', [
                'campaign_id' => $campaignId,
                'campaign' => $data['data'] ?? [],
            ]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.get', $exception);
        }
    }

    public function deleteCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $this->http()->delete("/campaigns/{$campaignId}");

            return new MarketingResult($this->name, 'campaign_delete', ['campaign_id' => $campaignId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.delete', $exception);
        }
    }

    public function payload(TransactionalMessage $message): array
    {
        $payload = [
            'from' => $message->from ? [
                'email' => $message->from->email,
                'name' => $message->from->name ?? '',
            ] : null,
            'to' => $message->to !== [] ? [
                'email' => $message->to[0]->email,
                'name' => $message->to[0]->name ?? '',
            ] : null,
            'cc' => $message->cc !== [] ? array_map(fn ($addr) => [
                'email' => $addr->email,
                'name' => $addr->name ?? '',
            ], $message->cc) : null,
            'bcc' => $message->bcc !== [] ? array_map(fn ($addr) => [
                'email' => $addr->email,
                'name' => $addr->name ?? '',
            ], $message->bcc) : null,
            'replyTo' => $message->replyTo ? [
                'email' => $message->replyTo->email,
                'name' => $message->replyTo->name ?? '',
            ] : null,
            'subject' => $message->subject,
            'html' => $message->html,
            'text' => $message->text,
        ];

        if ($message->isTemplateSend()) {
            $payload['templateId'] = $message->templateId;
            $payload['dynamicData'] = $message->data !== [] ? $message->data : null;
            unset($payload['subject'], $payload['html'], $payload['text']);
        }

        $headers = [];

        if ($message->tags !== []) {
            $headers['X-Mailbridge-Tags'] = implode(', ', $message->tags);
        }

        if ($message->metadata !== []) {
            $headers = array_merge($headers, $message->metadata);
        }

        if ($headers !== []) {
            $payload['headers'] = $headers;
        }

        if ($message->attachments !== []) {
            $payload['attachments'] = array_map(fn (array $attachment): array => array_filter([
                'fileName' => $attachment['name'] ?? 'attachment',
                'content' => base64_encode((string) $attachment['content']),
                'contentType' => $attachment['mime'] ?? null,
            ], fn ($value) => $value !== null), $message->attachments);
        }

        return array_filter($payload, fn ($value) => $value !== null && $value !== []);
    }

    public function campaignPayload(Campaign $campaign): array
    {
        $from = $this->campaignFrom($campaign->fromEmail, $campaign->fromName);

        return array_filter([
            'name' => $campaign->name,
            'subject' => $campaign->subject,
            'htmlTemplate' => $campaign->html,
            'from' => $from,
            'toLists' => $campaign->lists !== [] ? $campaign->lists : null,
            'publish' => true,
            ...$campaign->options,
        ], fn ($value) => $value !== null && $value !== []);
    }

    public function subscriberPayload(Subscriber $subscriber): array
    {
        [$firstName, $lastName] = $this->splitName($subscriber->name);

        return array_filter([
            'email' => $subscriber->email,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'customFields' => $subscriber->fields !== [] ? $subscriber->fields : null,
        ], fn ($value) => $value !== null && $value !== []);
    }

    private function splitName(?string $name): array
    {
        if ($name === null || trim($name) === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? null, $parts[1] ?? null];
    }

    private function http(): PendingRequest
    {
        if ($this->client !== null) {
            return $this->client;
        }

        return Http::baseUrl(self::BASE_URL)
            ->withToken($this->requireConfig('api_key'))
            ->acceptJson()
            ->asJson()
            ->throw()
            ->timeout(30);
    }
}
