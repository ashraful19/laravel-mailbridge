<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\MarketingProvider;
use Ashraful19\LaravelMailbridge\Contracts\TransactionalProvider;
use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\Campaign;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Data\SubscriberRecord;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeValidationException;
use Ashraful19\LaravelMailbridge\Support\AddressFormatter;
use Ashraful19\LaravelMailbridge\Support\ProviderFailureHandler;
use Brevo\Brevo;
use Brevo\Contacts\Requests\CreateContactRequest;
use Brevo\Contacts\Requests\RemoveContactFromListRequest;
use Brevo\EmailCampaigns\Requests\CreateEmailCampaignRequest;
use Brevo\EmailCampaigns\Requests\UpdateEmailCampaignRequest;
use Brevo\TransactionalEmails\Requests\SendTransacEmailRequest;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestAttachmentItem;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestBccItem;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestCcItem;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestReplyTo;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestSender;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem;
use Throwable;

final class BrevoProvider extends AbstractProvider implements TransactionalProvider, MarketingProvider
{
    private ?Brevo $cachedBrevo = null;

    public function __construct(
        string $name,
        array $config,
        \Illuminate\Contracts\Container\Container $app,
        private readonly mixed $transactionalApi = null,
        private readonly mixed $contactsApi = null,
        private readonly mixed $campaignsApi = null,
    ) {
        parent::__construct($name, $config, $app);
    }

    public function send(TransactionalMessage $message): SendResult
    {
        if ($this->transactionalApi === null && ! class_exists(Brevo::class)) {
            throw $this->missingSdk();
        }

        $message = $this->normalizer()->normalize($message, $this->config);
        $payload = $this->transactionalPayload($message);

        try {
            $response = $this->transactionalClient()->sendTransacEmail(new SendTransacEmailRequest($payload));

            return new SendResult($this->name, $response->messageId, ['message_ids' => $response->messageIds]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'transactional.send', $exception);
        }
    }

    public function subscribe(string $list, Subscriber $subscriber): MarketingResult
    {
        if ($this->contactsApi === null && ! class_exists(Brevo::class)) {
            throw $this->missingSdk();
        }
        $listId = $this->numericId($list, 'Brevo list id');

        $payload = [
            'email' => $subscriber->email,
            'attributes' => array_filter(array_merge(
                $subscriber->name !== null ? ['FIRSTNAME' => $subscriber->name] : [],
                $subscriber->fields,
            ), fn ($value) => $value !== null),
            'listIds' => [$listId],
            'updateEnabled' => true,
        ];

        try {
            $this->contactsClient()->createContact(new CreateContactRequest($payload));

            return new MarketingResult($this->name, 'subscribe', ['list' => $list]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscribe', $exception);
        }
    }

    public function unsubscribe(string $list, string $email): MarketingResult
    {
        try {
            $this->contactsClient()->removeContactFromList($this->numericId($list, 'Brevo list id'), new RemoveContactFromListRequest(['emails' => [$email]]));

            return new MarketingResult($this->name, 'unsubscribe', ['list' => $list]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.unsubscribe', $exception);
        }
    }

    public function getSubscriber(string $email): ?SubscriberRecord
    {
        try {
            $record = $this->contactsClient()->getContactInfo($email);

            return new SubscriberRecord($this->name, $email, method_exists($record, 'jsonSerialize') ? $record->jsonSerialize() : (array) $record);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscriber.lookup', $exception);
        }
    }

    public function deleteSubscriber(string $email): MarketingResult
    {
        try {
            $this->contactsClient()->deleteContact($email);

            return new MarketingResult($this->name, 'delete_subscriber');
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscriber.delete', $exception);
        }
    }

    public function createCampaign(Campaign $campaign): MarketingResult
    {
        try {
            $response = $this->campaignsClient()->createEmailCampaign(new CreateEmailCampaignRequest($this->campaignPayload($campaign)));

            return new MarketingResult($this->name, 'campaign_create', ['campaign_id' => $response?->id]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.create', $exception);
        }
    }

    public function sendCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $this->campaignsClient()->sendEmailCampaignNow((int) $campaignId);

            return new MarketingResult($this->name, 'campaign_send', ['campaign_id' => $campaignId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.send', $exception);
        }
    }

    public function scheduleCampaign(string|int $campaignId, \DateTimeInterface|string $when): MarketingResult
    {
        try {
            $this->campaignsClient()->updateEmailCampaign((int) $campaignId, new UpdateEmailCampaignRequest([
                'scheduledAt' => $when instanceof \DateTimeInterface ? $when->format(DATE_ATOM) : $when,
            ]));

            return new MarketingResult($this->name, 'campaign_schedule', ['campaign_id' => $campaignId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.schedule', $exception);
        }
    }

    public function getCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $response = $this->campaignsClient()->getEmailCampaign((int) $campaignId, 'globalStats');

            return new MarketingResult($this->name, 'campaign_get', ['campaign_id' => $campaignId, 'campaign' => method_exists($response, 'jsonSerialize') ? $response->jsonSerialize() : (array) $response]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.get', $exception);
        }
    }

    public function deleteCampaign(string|int $campaignId): MarketingResult
    {
        try {
            $this->campaignsClient()->deleteEmailCampaign((int) $campaignId);

            return new MarketingResult($this->name, 'campaign_delete', ['campaign_id' => $campaignId]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.campaign.delete', $exception);
        }
    }

    public function transactionalPayload(TransactionalMessage $message): array
    {
        $payload = [
            'sender' => $message->from !== null ? new SendTransacEmailRequestSender($message->from->toArray()) : null,
            'to' => array_map(fn (array $addr) => new SendTransacEmailRequestToItem($addr), AddressFormatter::arrays($message->to)),
            'cc' => array_map(fn (array $addr) => new SendTransacEmailRequestCcItem($addr), AddressFormatter::arrays($message->cc)),
            'bcc' => array_map(fn (array $addr) => new SendTransacEmailRequestBccItem($addr), AddressFormatter::arrays($message->bcc)),
            'replyTo' => $message->replyTo !== null ? new SendTransacEmailRequestReplyTo($message->replyTo->toArray()) : null,
            'tags' => $message->tags,
            'headers' => $message->metadata,
        ];

        if ($message->isTemplateSend()) {
            $payload['templateId'] = is_numeric($message->templateId) ? (int) $message->templateId : $message->templateId;
            $payload['params'] = $message->data;
        } else {
            $payload['subject'] = $message->subject;
            $payload['htmlContent'] = $message->html;
            $payload['textContent'] = $message->text;
        }

        if ($message->attachments !== []) {
            $payload['attachment'] = array_map(fn (array $attachment) => new SendTransacEmailRequestAttachmentItem([
                'content' => base64_encode((string) $attachment['content']),
                'name' => $attachment['name'] ?? 'attachment',
            ]), $message->attachments);
        }

        return array_filter($payload, fn ($value) => $value !== null && $value !== []);
    }

    private function brevo(): Brevo
    {
        return $this->cachedBrevo ??= new Brevo($this->requireConfig('api_key'));
    }

    private function transactionalClient(): mixed
    {
        return $this->transactionalApi ?? $this->brevo()->transactionalEmails;
    }

    private function contactsClient(): mixed
    {
        return $this->contactsApi ?? $this->brevo()->contacts;
    }

    private function campaignsClient(): mixed
    {
        return $this->campaignsApi ?? $this->brevo()->emailCampaigns;
    }

    public function campaignPayload(Campaign $campaign): array
    {
        $listIds = array_map(fn (string|int $list): int => $this->numericId($list, 'Brevo campaign list id'), $campaign->lists);
        $from = $this->campaignFrom($campaign->fromEmail, $campaign->fromName);

        return array_filter([
            'name' => $campaign->name,
            'subject' => $campaign->subject,
            'htmlContent' => $campaign->html,
            'sender' => $from,
            'recipients' => new \Brevo\EmailCampaigns\Types\CreateEmailCampaignRequestRecipients(['listIds' => $listIds]),
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
}
