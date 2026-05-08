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
use Mailjet\Client;
use Mailjet\Resources;
use Throwable;

final class MailjetProvider extends AbstractProvider implements TransactionalProvider, MarketingProvider
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
        if ($this->client === null && ! class_exists(Client::class)) {
            throw $this->missingSdk();
        }

        $message = $this->normalizer()->normalize($message, $this->config);

        try {
            $response = $this->mailjetClient('v3.1')->post(Resources::$Email, ['body' => $this->payload($message)]);
            $this->ensureSuccess($response, 'transactional.send');

            $data = $response->getData();

            return new SendResult($this->name, $data['Messages'][0]['To'][0]['MessageID'] ?? null, ['status' => $response->getStatus()]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'transactional.send', $exception);
        }
    }

    public function subscribe(string $list, Subscriber $subscriber): MarketingResult
    {
        try {
            $listId = $this->numericId($list, 'Mailjet list id');
            $response = $this->mailjetClient()->post(Resources::$Contact, ['body' => ['Email' => $subscriber->email, 'Name' => $subscriber->name]]);
            if (! $response->success() && (int) $response->getStatus() !== 400) {
                $this->ensureSuccess($response, 'marketing.subscribe.contact');
            }

            $contactId = $response->getData()[0]['ID'] ?? $this->contactId($subscriber->email);

            $response = $this->mailjetClient()->post(Resources::$ContactManagecontactslists, [
                'id' => $contactId,
                'body' => ['ContactsLists' => [['ListID' => $listId, 'Action' => 'addforce']]],
            ]);
            $this->ensureSuccess($response, 'marketing.subscribe.list');

            return new MarketingResult($this->name, 'subscribe', ['list' => (string) $listId, 'contact_id' => $contactId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscribe', $exception);
        }
    }

    public function unsubscribe(string $list, string $email): MarketingResult
    {
        try {
            $listId = $this->numericId($list, 'Mailjet list id');
            $contactId = $this->contactId($email);
            $response = $this->mailjetClient()->post(Resources::$ContactManagecontactslists, [
                'id' => $contactId,
                'body' => ['ContactsLists' => [['ListID' => $listId, 'Action' => 'remove']]],
            ]);
            $this->ensureSuccess($response, 'marketing.unsubscribe');

            return new MarketingResult($this->name, 'unsubscribe', ['list' => (string) $listId, 'contact_id' => $contactId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.unsubscribe', $exception);
        }
    }

    public function getSubscriber(string $email): ?SubscriberRecord
    {
        try {
            $response = $this->mailjetClient()->get(Resources::$Contact, ['filters' => ['Email' => $email]]);
            $this->ensureSuccess($response, 'marketing.subscriber.lookup');
            $data = $response->getData()[0] ?? null;

            return $data === null ? null : new SubscriberRecord($this->name, $email, $data);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscriber.lookup', $exception);
        }
    }

    public function deleteSubscriber(string $email): MarketingResult
    {
        try {
            $contactId = $this->contactId($email);
            $response = $this->mailjetClient('v4')->delete(Resources::$Contacts, ['id' => $contactId]);
            $this->ensureSuccess($response, 'marketing.subscriber.delete');

            return new MarketingResult($this->name, 'delete_subscriber', ['contact_id' => $contactId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscriber.delete', $exception);
        }
    }

    public function createCampaign(Campaign $campaign): MarketingResult
    {
        try {
            $response = $this->mailjetClient()->post(Resources::$Campaigndraft, ['body' => $this->campaignPayload($campaign)]);
            $this->ensureSuccess($response, 'marketing.campaign.create');

            return new MarketingResult($this->name, 'campaign_create', ['campaign_id' => $response->getData()[0]['ID'] ?? null]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.create', $exception);
        }
    }

    public function sendCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $response = $this->mailjetClient()->post(Resources::$CampaigndraftSend, ['id' => $campaignId]);
            $this->ensureSuccess($response, 'marketing.campaign.send');

            return new MarketingResult($this->name, 'campaign_send', ['campaign_id' => $campaignId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.send', $exception);
        }
    }

    public function scheduleCampaign(string|int $campaignId, \DateTimeInterface|string $when): MarketingResult
    {
        try {
            $scheduledAt = $when instanceof \DateTimeInterface ? $when->format(DATE_ATOM) : $when;
            $response = $this->mailjetClient()->post(Resources::$CampaigndraftSchedule, ['id' => $campaignId, 'body' => ['Date' => $scheduledAt]]);
            $this->ensureSuccess($response, 'marketing.campaign.schedule');

            return new MarketingResult($this->name, 'campaign_schedule', ['campaign_id' => $campaignId, 'scheduled_at' => $scheduledAt]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.schedule', $exception);
        }
    }

    public function getCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $response = $this->mailjetClient()->get(Resources::$Campaign, ['id' => $campaignId]);
            $this->ensureSuccess($response, 'marketing.campaign.get');

            return new MarketingResult($this->name, 'campaign_get', ['campaign_id' => $campaignId, 'campaign' => $response->getData()]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.get', $exception);
        }
    }

    public function deleteCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $response = $this->mailjetClient()->delete(Resources::$Campaigndraft, ['id' => $campaignId]);
            $this->ensureSuccess($response, 'marketing.campaign.delete');

            return new MarketingResult($this->name, 'campaign_delete', ['campaign_id' => $campaignId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.delete', $exception);
        }
    }

    public function payload(TransactionalMessage $message): array
    {
        $mail = [
            'From' => ['Email' => $message->from->email, 'Name' => $message->from->name],
            'To' => array_map(fn ($address): array => ['Email' => $address->email, 'Name' => $address->name], $message->to),
            'Cc' => array_map(fn ($address): array => ['Email' => $address->email, 'Name' => $address->name], $message->cc),
            'Bcc' => array_map(fn ($address): array => ['Email' => $address->email, 'Name' => $address->name], $message->bcc),
            'Subject' => $message->subject,
            'HTMLPart' => $message->html,
            'TextPart' => $message->text,
            'CustomID' => $message->metadata === [] ? null : json_encode($message->metadata, JSON_THROW_ON_ERROR),
        ];

        if ($message->replyTo !== null) {
            $mail['ReplyTo'] = ['Email' => $message->replyTo->email, 'Name' => $message->replyTo->name];
        }

        if ($message->isTemplateSend()) {
            $mail['TemplateID'] = is_numeric($message->templateId) ? (int) $message->templateId : $message->templateId;
            $mail['TemplateLanguage'] = true;
            $mail['Variables'] = $message->data;
        }

        if ($message->attachments !== []) {
            $mail['Attachments'] = array_map(fn (array $attachment): array => [
                'ContentType' => $attachment['mime'] ?? 'application/octet-stream',
                'Filename' => $attachment['name'] ?? 'attachment',
                'Base64Content' => base64_encode((string) $attachment['content']),
            ], $message->attachments);
        }

        return ['Messages' => [array_filter($mail, fn ($value) => $value !== null && $value !== [])]];
    }

    public function campaignPayload(Campaign $campaign): array
    {
        $listId = isset($campaign->lists[0]) ? $this->numericId($campaign->lists[0], 'Mailjet campaign list id') : null;
        $from = $this->campaignFrom($campaign->fromEmail, $campaign->fromName);

        return array_filter([
            'Locale' => $campaign->options['locale'] ?? 'en_US',
            'SenderEmail' => $from['email'],
            'Sender' => $from['name'],
            'Subject' => $campaign->subject,
            'Title' => $campaign->name,
            'ContactsListID' => $listId,
            'Html-part' => $campaign->html,
            ...$campaign->options,
        ], fn ($value) => $value !== null && $value !== []);
    }

    private function numericId(string|int $value, string $label): int
    {
        if (! is_numeric((string) $value)) {
            throw new MailbridgeValidationException("{$label} must be numeric.");
        }

        return (int) $value;
    }

    private function contactId(string $email): string|int
    {
        $record = $this->getSubscriber($email);

        if ($record === null) {
            throw new MailbridgeException("Mailjet contact [{$email}] was not found.", ['provider' => $this->name]);
        }

        return $record->data['ID'] ?? $email;
    }

    private function mailjetClient(string $version = 'v3'): mixed
    {
        if ($this->client === null && ! class_exists(Client::class)) {
            throw $this->missingSdk();
        }

        return $this->client ?? new Client($this->requireConfig('api_key'), $this->requireConfig('secret_key'), true, ['version' => $version]);
    }

    private function ensureSuccess(mixed $response, string $operation): void
    {
        if ($response->success()) {
            return;
        }

        $status = (int) $response->getStatus();
        $context = ['provider' => $this->name, 'operation' => $operation, 'status' => $status];

        if (ProviderFailureHandler::isTransientStatus($status)) {
            throw new ProviderTransientException("Provider [{$this->name}] failed transiently during [{$operation}].", $context);
        }

        throw new MailbridgeException("Provider [{$this->name}] failed during [{$operation}].", $context);
    }
}
