<?php

namespace Ashraful19\LaravelMailbridge\Providers;

use Ashraful19\LaravelMailbridge\Contracts\MarketingProvider;
use Ashraful19\LaravelMailbridge\Contracts\TransactionalProvider;
use Ashraful19\LaravelMailbridge\Data\MarketingResult;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Support\AddressFormatter;
use Ashraful19\LaravelMailbridge\Support\ProviderFailureHandler;
use Brevo\Client\Api\ContactsApi;
use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Configuration;
use Brevo\Client\Model\AddContactToList;
use Brevo\Client\Model\CreateContact;
use Brevo\Client\Model\SendSmtpEmail;
use Throwable;

final class BrevoProvider extends AbstractProvider implements TransactionalProvider, MarketingProvider
{
    public function __construct(
        string $name,
        array $config,
        \Illuminate\Contracts\Container\Container $app,
        private readonly mixed $transactionalApi = null,
        private readonly mixed $contactsApi = null,
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

        $payload = [
            'email' => $subscriber->email,
            'attributes' => array_filter(array_replace($subscriber->fields, ['FIRSTNAME' => $subscriber->name]), fn ($value) => $value !== null),
            'listIds' => [(int) $list],
            'updateEnabled' => true,
        ];

        try {
            $this->contactsClient()->createContact(new CreateContact($payload));

            return new MarketingResult($this->name, 'subscribe', ['list' => $list]);
        } catch (Throwable $exception) {
            ProviderFailureHandler::throw($this->name, 'marketing.subscribe', $exception);
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

    private function configuration(): Configuration
    {
        return Configuration::getDefaultConfiguration()->setApiKey('api-key', $this->requireConfig('api_key'));
    }
}
