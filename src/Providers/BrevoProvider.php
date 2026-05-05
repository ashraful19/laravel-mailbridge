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
use Brevo\Client\Api\ContactsApi;
use Brevo\Client\Api\EmailCampaignsApi;
use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Configuration;
use Brevo\Client\Model\AddContactToList;
use Brevo\Client\Model\CreateContact;
use Brevo\Client\Model\CreateEmailCampaign;
use Brevo\Client\Model\CreateEmailCampaignRecipients;
use Brevo\Client\Model\SendSmtpEmail;
use Brevo\Client\Model\RemoveContactFromList;
use Throwable;

final class BrevoProvider extends AbstractProvider implements TransactionalProvider, MarketingProvider
{
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
        if ($this->transactionalApi === null && ! class_exists(TransactionalEmailsApi::class)) {
            throw $this->missingSdk();
        }

        $message = $this->normalizer()->normalize($message);
        $payload = $this->transactionalPayload($message);

        try {
            $response = $this->transactionalClient()->sendTransacEmail(new SendSmtpEmail($payload));

            return new SendResult($this->name, $response->getMessageId(), ['message_ids' => $response->getMessageIds()]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'transactional.send', $exception);
        }
    }

    public function subscribe(string $list, Subscriber $subscriber): MarketingResult
    {
        if ($this->contactsApi === null && ! class_exists(ContactsApi::class)) {
            throw $this->missingSdk();
        }
        $listId = $this->numericId($list, 'Brevo list id');

        $payload = [
            'email' => $subscriber->email,
            'attributes' => array_filter(array_replace($subscriber->fields, ['FIRSTNAME' => $subscriber->name]), fn ($value) => $value !== null),
            'listIds' => [$listId],
            'updateEnabled' => true,
        ];

        try {
            $this->contactsClient()->createContact(new CreateContact($payload));

            return new MarketingResult($this->name, 'subscribe', ['list' => $list]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscribe', $exception);
        }
    }

    public function unsubscribe(string $list, string $email): MarketingResult
    {
        try {
            $this->contactsClient()->removeContactFromList(new RemoveContactFromList(['emails' => [$email]]), $this->numericId($list, 'Brevo list id'));

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
            $response = $this->campaignsClient()->createEmailCampaign(new CreateEmailCampaign($this->campaignPayload($campaign)));

            return new MarketingResult($this->name, 'campaign_create', ['campaign_id' => method_exists($response, 'getId') ? $response->getId() : null]);
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
            $this->campaignsClient()->updateEmailCampaign(new \Brevo\Client\Model\UpdateEmailCampaign([
                'scheduledAt' => $when instanceof \DateTimeInterface ? $when->format(DATE_ATOM) : $when,
            ]), (int) $campaignId);

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
            'sender' => $message->from?->toArray(),
            'to' => AddressFormatter::arrays($message->to),
            'cc' => AddressFormatter::arrays($message->cc),
            'bcc' => AddressFormatter::arrays($message->bcc),
            'replyTo' => $message->replyTo?->toArray(),
            'tags' => $message->tags,
            'headers' => $message->metadata,
        ];

        if ($message->isTemplateSend()) {
            $payload['templateId'] = is_numeric($message->templateId) ? (int) $message->templateId : $message->templateId;
            $payload['params'] = (object) $message->data;
        } else {
            $payload['subject'] = $message->subject;
            $payload['htmlContent'] = $message->html;
            $payload['textContent'] = $message->text;
        }

        if ($message->attachments !== []) {
            $payload['attachment'] = array_map(fn (array $attachment): array => [
                'content' => base64_encode((string) $attachment['content']),
                'name' => $attachment['name'] ?? 'attachment',
            ], $message->attachments);
        }

        return array_filter($payload, fn ($value) => $value !== null && $value !== []);
    }

    private function transactionalClient(): mixed
    {
        return $this->transactionalApi ?? new TransactionalEmailsApi(null, $this->configuration());
    }

    private function contactsClient(): mixed
    {
        return $this->contactsApi ?? new ContactsApi(null, $this->configuration());
    }

    private function campaignsClient(): mixed
    {
        return $this->campaignsApi ?? new EmailCampaignsApi(null, $this->configuration());
    }

    public function campaignPayload(Campaign $campaign): array
    {
        $listIds = array_map(fn (string|int $list): int => $this->numericId($list, 'Brevo campaign list id'), $campaign->lists);
        $from = [
            'email' => $campaign->fromEmail ?? $this->config['from']['address'] ?? $this->app['config']->get('mailbridge.from.address'),
            'name' => $campaign->fromName ?? $this->config['from']['name'] ?? $this->app['config']->get('mailbridge.from.name'),
        ];

        return array_filter([
            'name' => $campaign->name,
            'subject' => $campaign->subject,
            'htmlContent' => $campaign->html,
            'sender' => $from,
            'recipients' => new CreateEmailCampaignRecipients(['listIds' => $listIds]),
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

    private function configuration(): Configuration
    {
        return Configuration::getDefaultConfiguration()->setApiKey('api-key', $this->requireConfig('api_key'));
    }
}
