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
use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeException;
use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeValidationException;
use Ashraful19\LaravelMailbridge\Exceptions\ProviderTransientException;
use Ashraful19\LaravelMailbridge\Support\ProviderFailureHandler;
use SendGrid\Mail\Attachment;
use SendGrid\Mail\Mail;
use Throwable;

final class SendgridProvider extends AbstractProvider implements TransactionalProvider, MarketingProvider
{
    public function __construct(
        string $name,
        array $config,
        \Illuminate\Contracts\Container\Container $app,
        private readonly mixed $client = null,
    ) {
        parent::__construct($name, $config, $app);
    }

    public function send(TransactionalMessage $message): SendResult
    {
        if ($this->client === null && ! class_exists(\SendGrid::class)) {
            throw $this->missingSdk();
        }

        $message = $this->normalizer()->normalize($message, $this->config);
        $mail = $this->mail($message);

        try {
            $response = $this->sendgridClient()->send($mail);
            $status = (int) $response->statusCode();

            if ($status < 200 || $status >= 300) {
                $this->throwResponseFailure('transactional.send', $status, (string) $response->body());
            }

            $headers = $response->headers(true);

            return new SendResult($this->name, $headers['X-Message-Id'] ?? null, ['status' => $status]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'transactional.send', $exception);
        }
    }

    public function subscribe(string $list, Subscriber $subscriber): MarketingResult
    {
        try {
            $listId = $this->numericId($list, 'SendGrid list id');
            $response = $this->sendgridApiClient()->contactdb()->recipients()->post([$this->subscriberPayload($subscriber)]);
            $this->ensureSuccess($response, 'marketing.subscribe.recipient');

            $recipientId = $this->responseData($response)['persisted_recipients'][0] ?? $this->recipientId($subscriber->email);
            $response = $this->sendgridApiClient()->contactdb()->lists()->_($listId)->recipients()->post([$recipientId]);
            $this->ensureSuccess($response, 'marketing.subscribe.list');

            return new MarketingResult($this->name, 'subscribe', ['list' => (string) $listId, 'subscriber_id' => $recipientId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscribe', $exception);
        }
    }

    public function unsubscribe(string $list, string $email): MarketingResult
    {
        try {
            $listId = $this->numericId($list, 'SendGrid list id');
            $recipientId = $this->recipientId($email);
            $response = $this->sendgridApiClient()->contactdb()->lists()->_($listId)->recipients()->_($recipientId)->delete();
            $this->ensureSuccess($response, 'marketing.unsubscribe');

            return new MarketingResult($this->name, 'unsubscribe', ['list' => (string) $listId, 'subscriber_id' => $recipientId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.unsubscribe', $exception);
        }
    }

    public function getSubscriber(string $email): ?SubscriberRecord
    {
        try {
            $response = $this->sendgridApiClient()->contactdb()->recipients()->search()->get(null, ['email' => $email]);
            $this->ensureSuccess($response, 'marketing.subscriber.lookup');
            $recipient = $this->responseData($response)['recipients'][0] ?? null;

            return $recipient === null ? null : new SubscriberRecord($this->name, $email, $recipient);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscriber.lookup', $exception);
        }
    }

    public function deleteSubscriber(string $email): MarketingResult
    {
        try {
            $recipientId = $this->recipientId($email);
            $response = $this->sendgridApiClient()->contactdb()->recipients()->_($recipientId)->delete();
            $this->ensureSuccess($response, 'marketing.subscriber.delete');

            return new MarketingResult($this->name, 'delete_subscriber', ['subscriber_id' => $recipientId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscriber.delete', $exception);
        }
    }

    public function createCampaign(Campaign $campaign): MarketingResult
    {
        try {
            $response = $this->sendgridApiClient()->campaigns()->post($this->campaignPayload($campaign));
            $this->ensureSuccess($response, 'marketing.campaign.create');

            return new MarketingResult($this->name, 'campaign_create', ['campaign_id' => $this->responseData($response)['id'] ?? null]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.create', $exception);
        }
    }

    public function sendCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $response = $this->sendgridApiClient()->campaigns()->_($campaignId)->schedules()->now()->post();
            $this->ensureSuccess($response, 'marketing.campaign.send');

            return new MarketingResult($this->name, 'campaign_send', ['campaign_id' => $campaignId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.send', $exception);
        }
    }

    public function scheduleCampaign(string|int $campaignId, \DateTimeInterface|string $when): MarketingResult
    {
        try {
            $scheduledAt = $when instanceof \DateTimeInterface ? $when : new \DateTimeImmutable($when);
            $response = $this->sendgridApiClient()->campaigns()->_($campaignId)->schedules()->post(['send_at' => $scheduledAt->getTimestamp()]);
            $this->ensureSuccess($response, 'marketing.campaign.schedule');

            return new MarketingResult($this->name, 'campaign_schedule', ['campaign_id' => $campaignId, 'scheduled_at' => $scheduledAt->format(DATE_ATOM)]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.schedule', $exception);
        }
    }

    public function getCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $response = $this->sendgridApiClient()->campaigns()->_($campaignId)->get();
            $this->ensureSuccess($response, 'marketing.campaign.get');

            return new MarketingResult($this->name, 'campaign_get', ['campaign_id' => $campaignId, 'campaign' => $this->responseData($response)]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.get', $exception);
        }
    }

    public function deleteCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $response = $this->sendgridApiClient()->campaigns()->_($campaignId)->delete();
            $this->ensureSuccess($response, 'marketing.campaign.delete');

            return new MarketingResult($this->name, 'campaign_delete', ['campaign_id' => $campaignId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.delete', $exception);
        }
    }

    public function payload(TransactionalMessage $message): array
    {
        return $this->mail($message)->jsonSerialize();
    }

    public function subscriberPayload(Subscriber $subscriber): array
    {
        [$firstName, $lastName] = $this->splitName($subscriber->name);

        return array_filter([
            'email' => $subscriber->email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            ...$subscriber->fields,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function campaignPayload(Campaign $campaign): array
    {
        $listIds = array_map(fn (string|int $list): int => $this->numericId($list, 'SendGrid campaign list id'), $campaign->lists);
        $payload = array_filter([
            'title' => $campaign->name,
            'subject' => $campaign->subject,
            'html_content' => $campaign->html,
            'plain_content' => $campaign->options['plain_content'] ?? null,
            'sender_id' => $campaign->options['sender_id'] ?? $this->requireConfig('marketing_sender_id'),
            'list_ids' => $listIds,
            'categories' => $campaign->options['categories'] ?? [],
        ], fn (mixed $value): bool => $value !== null && $value !== []);

        return array_replace_recursive($payload, $campaign->options['sendgrid'] ?? []);
    }

    public function mail(TransactionalMessage $message): Mail
    {
        $mail = new Mail();
        $mail->setFrom($message->from->email, $message->from->name);

        foreach ($message->to as $address) {
            $mail->addTo($address->email, $address->name);
        }

        foreach ($message->cc as $address) {
            $mail->addCc($address->email, $address->name);
        }

        foreach ($message->bcc as $address) {
            $mail->addBcc($address->email, $address->name);
        }

        if ($message->replyTo !== null) {
            $mail->setReplyTo($message->replyTo->email, $message->replyTo->name);
        }

        if ($message->isTemplateSend()) {
            $mail->setTemplateId((string) $message->templateId);

            foreach ($message->data as $key => $value) {
                $mail->addDynamicTemplateData((string) $key, $value);
            }
        } else {
            $mail->setSubject((string) $message->subject);
            $mail->addContent('text/plain', $message->text ?? '');

            if ($message->html !== null) {
                $mail->addContent('text/html', $message->html);
            }
        }

        foreach ($message->tags as $tag) {
            $mail->addCategory($tag);
        }

        foreach ($message->metadata as $key => $value) {
            $mail->addCustomArg($key, is_scalar($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR));
        }

        foreach ($message->attachments as $attachment) {
            $mail->addAttachment(new Attachment(
                base64_encode((string) $attachment['content']),
                $attachment['mime'] ?? 'application/octet-stream',
                $attachment['name'] ?? 'attachment',
            ));
        }

        return $mail;
    }

    private function sendgridClient(): mixed
    {
        return $this->client ?? new \SendGrid($this->requireConfig('api_key'));
    }

    private function sendgridApiClient(): mixed
    {
        if ($this->client === null && ! class_exists(\SendGrid::class)) {
            throw $this->missingSdk();
        }

        return $this->sendgridClient()->client;
    }

    private function recipientId(string $email): string|int
    {
        $record = $this->getSubscriber($email);

        if ($record === null) {
            throw new MailbridgeException("SendGrid recipient [{$email}] was not found.", ['provider' => $this->name]);
        }

        return $record->data['id'] ?? $record->data['recipient_id'] ?? $email;
    }

    private function ensureSuccess(mixed $response, string $operation): void
    {
        $status = (int) $response->statusCode();

        if ($status >= 200 && $status < 300) {
            return;
        }

        $this->throwResponseFailure($operation, $status, (string) $response->body());
    }

    private function responseData(mixed $response): array
    {
        $body = trim((string) $response->body());

        if ($body === '') {
            return [];
        }

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    private function splitName(?string $name): array
    {
        if ($name === null || trim($name) === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? null, $parts[1] ?? null];
    }

    private function numericId(string|int $value, string $label): int
    {
        if (! is_numeric((string) $value)) {
            throw new MailbridgeValidationException("{$label} must be numeric.");
        }

        return (int) $value;
    }

    private function throwResponseFailure(string $operation, int $status, string $body): never
    {
        $context = ['provider' => $this->name, 'operation' => $operation, 'status' => $status];

        if (ProviderFailureHandler::isTransientStatus($status)) {
            throw new ProviderTransientException("Provider [{$this->name}] failed transiently during [{$operation}].", $context);
        }

        throw new MailbridgeException("Provider [{$this->name}] failed during [{$operation}].", $context + ['error' => substr($body, 0, 500)]);
    }
}
