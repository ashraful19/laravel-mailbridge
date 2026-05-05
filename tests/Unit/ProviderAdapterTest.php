<?php

namespace Ashraful19\LaravelMailbridge\Tests\Unit;

use Ashraful19\LaravelMailbridge\Contracts\ProviderAdapter;
use Ashraful19\LaravelMailbridge\Contracts\TransactionalProvider;
use Ashraful19\LaravelMailbridge\Data\Address;
use Ashraful19\LaravelMailbridge\Data\Campaign;
use Ashraful19\LaravelMailbridge\Data\SendResult;
use Ashraful19\LaravelMailbridge\Data\Subscriber;
use Ashraful19\LaravelMailbridge\Data\TransactionalMessage;
use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeException;
use Ashraful19\LaravelMailbridge\Exceptions\ProviderTransientException;
use Ashraful19\LaravelMailbridge\MailbridgeManager;
use Ashraful19\LaravelMailbridge\Providers\BrevoProvider;
use Ashraful19\LaravelMailbridge\Providers\MailerliteProvider;
use Ashraful19\LaravelMailbridge\Providers\MailersendProvider;
use Ashraful19\LaravelMailbridge\Providers\MailgunProvider;
use Ashraful19\LaravelMailbridge\Providers\PostmarkProvider;
use Ashraful19\LaravelMailbridge\Providers\ResendProvider;
use Ashraful19\LaravelMailbridge\Providers\SendgridProvider;
use Ashraful19\LaravelMailbridge\Tests\TestCase;
use Illuminate\Mail\Mailable;
use ReflectionProperty;

final class ProviderAdapterTest extends TestCase
{
    public function test_brevo_maps_template_payload_to_official_model(): void
    {
        $message = $this->message();
        $message->templateId = 123;
        $message->data = ['name' => 'Ash'];

        $payload = (new BrevoProvider('brevo', ['api_key' => 'key'], $this->app))->transactionalPayload($message);

        $this->assertSame(123, $payload['templateId']);
        $this->assertEquals((object) ['name' => 'Ash'], $payload['params']);
        $this->assertSame([['email' => 'a@example.com', 'name' => 'A']], $payload['to']);
    }

    public function test_resend_maps_raw_payload(): void
    {
        $payload = (new ResendProvider('resend', ['api_key' => 'key'], $this->app))->payload($this->message());

        $this->assertSame('Sender <sender@example.com>', $payload['from']);
        $this->assertSame(['A <a@example.com>'], $payload['to']);
        $this->assertSame('Hello', $payload['subject']);
        $this->assertSame('<p>Hello</p>', $payload['html']);
    }

    public function test_postmark_uses_template_arguments(): void
    {
        $message = $this->message();
        $message->templateId = 'welcome-alias';
        $message->data = ['name' => 'Ash'];

        $args = (new PostmarkProvider('postmark', ['server_token' => 'token'], $this->app))->templateArguments($message);

        $this->assertSame('Sender <sender@example.com>', $args[0]);
        $this->assertSame(['A <a@example.com>'], $args[1]);
        $this->assertSame('welcome-alias', $args[2]);
        $this->assertSame(['name' => 'Ash'], $args[3]);
    }

    public function test_mailgun_maps_template_payload(): void
    {
        $message = $this->message();
        $message->templateId = 'welcome';
        $message->data = ['name' => 'Ash'];

        $payload = (new MailgunProvider('mailgun', ['api_key' => 'key', 'domain' => 'mg.example.com'], $this->app))->payload($message);

        $this->assertSame('welcome', $payload['template']);
        $this->assertSame('{"name":"Ash","campaign":"signup"}', $payload['h:X-Mailgun-Variables']);
    }

    public function test_sendgrid_maps_template_payload(): void
    {
        $message = $this->message();
        $message->templateId = 'd-welcome';
        $message->data = ['name' => 'Ash'];
        $message->attachments[] = ['content' => 'invoice-bytes', 'name' => 'invoice.txt', 'mime' => 'text/plain'];

        $payload = json_decode(json_encode((new SendgridProvider('sendgrid', ['api_key' => 'key'], $this->app))->payload($message), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('d-welcome', $payload['template_id']);
        $this->assertSame(['name' => 'Ash'], $payload['personalizations'][0]['dynamic_template_data']);
        $this->assertSame('welcome', $payload['categories'][0]);
        $this->assertSame('signup', $payload['personalizations'][0]['custom_args']['campaign']);
        $this->assertSame(base64_encode('invoice-bytes'), $payload['attachments'][0]['content']);
        $this->assertSame('invoice.txt', $payload['attachments'][0]['filename']);
    }

    public function test_mailersend_maps_template_personalization(): void
    {
        $message = $this->message();
        $message->templateId = 'template-id';
        $message->data = ['name' => 'Ash'];

        $params = (new MailersendProvider('mailersend', ['api_key' => 'key'], $this->app))->emailParams($message);

        $this->assertSame('template-id', $params->getTemplateId());
        $this->assertSame('a@example.com', $params->getPersonalization()[0]->toArray()['email']);
        $this->assertSame(['name' => 'Ash'], $params->getPersonalization()[0]->toArray()['data']);
    }

    public function test_mailerlite_maps_subscriber_payload(): void
    {
        $subscriber = Subscriber::make('a@example.com')->name('Ash')->field('company', 'Converlo');

        $payload = (new MailerliteProvider('mailerlite', ['api_key' => 'key'], $this->app))->payload($subscriber);

        $this->assertSame([
            'email' => 'a@example.com',
            'name' => 'Ash',
            'fields' => ['company' => 'Converlo'],
        ], $payload);
    }

    public function test_brevo_maps_campaign_payload(): void
    {
        $payload = (new BrevoProvider('brevo', ['api_key' => 'key'], $this->app))->campaignPayload(
            Campaign::make('Launch')->subject('Hello')->html('<p>Hello</p>')->from('sender@example.com', 'Sender')->list(123)
        );

        $this->assertSame('Launch', $payload['name']);
        $this->assertSame('Hello', $payload['subject']);
        $this->assertSame(['email' => 'sender@example.com', 'name' => 'Sender'], $payload['sender']);
    }

    public function test_mailerlite_maps_campaign_payload(): void
    {
        $payload = (new MailerliteProvider('mailerlite', ['api_key' => 'key'], $this->app))->campaignPayload(
            Campaign::make('Launch')->subject('Hello')->html('<p>Hello</p>')->from('sender@example.com', 'Sender')->list('group-id')
        );

        $this->assertSame('Launch', $payload['name']);
        $this->assertSame(['group-id'], $payload['groups']);
        $this->assertSame('Hello', $payload['emails'][0]['subject']);
    }

    public function test_mailable_is_rendered_before_sdk_send(): void
    {
        config()->set('mailbridge.from.address', 'sender@example.com');
        config()->set('mailbridge.from.name', 'Sender');

        $message = new TransactionalMessage();
        $message->to[] = Address::make('a@example.com', 'A');
        $message->mailable = new HtmlOnlyMailable();

        $client = new FakeResendClient();
        (new ResendProvider('resend', ['api_key' => 'key'], $this->app, $client))->send($message);

        $this->assertSame('<strong>Welcome</strong>', $client->emails->payload['html']);
        $this->assertSame('Custom Welcome', $client->emails->payload['subject']);
    }

    public function test_fallback_retries_only_transient_provider_failure(): void
    {
        $manager = app(MailbridgeManager::class);
        $this->setResolvedProviders($manager, [
            'broken' => new BrokenProvider(true),
            'working' => new WorkingProvider(),
        ]);

        config()->set('mailbridge.fallbacks.transactional', ['working']);

        $result = $manager->sendTransactional($this->message(), 'broken', true);

        $this->assertSame('working', $result->provider);
    }

    public function test_fallback_does_not_retry_non_transient_provider_failure(): void
    {
        $manager = app(MailbridgeManager::class);
        $this->setResolvedProviders($manager, [
            'broken' => new BrokenProvider(false),
            'working' => new WorkingProvider(),
        ]);

        config()->set('mailbridge.fallbacks.transactional', ['working']);

        $this->expectException(MailbridgeException::class);

        $manager->sendTransactional($this->message(), 'broken', true);
    }

    private function message(): TransactionalMessage
    {
        $message = new TransactionalMessage();
        $message->from = Address::make('sender@example.com', 'Sender');
        $message->to[] = Address::make('a@example.com', 'A');
        $message->subject = 'Hello';
        $message->html = '<p>Hello</p>';
        $message->text = 'Hello';
        $message->tags = ['welcome'];
        $message->metadata = ['campaign' => 'signup'];

        return $message;
    }

    private function setResolvedProviders(MailbridgeManager $manager, array $providers): void
    {
        $property = new ReflectionProperty($manager, 'resolved');
        $property->setAccessible(true);
        $property->setValue($manager, $providers);
    }
}

final class HtmlOnlyMailable extends Mailable
{
    public function build(): self
    {
        return $this->subject('Custom Welcome')->html('<strong>Welcome</strong>');
    }
}

final class FakeResendClient
{
    public FakeResendEmails $emails;

    public function __construct()
    {
        $this->emails = new FakeResendEmails();
    }
}

final class FakeResendEmails
{
    public array $payload = [];

    public function send(array $payload): object
    {
        $this->payload = $payload;

        return new class {
            public string $id = 'resend_123';
        };
    }
}

final class WorkingProvider implements ProviderAdapter, TransactionalProvider
{
    public function name(): string
    {
        return 'working';
    }

    public function supports(string $feature): bool
    {
        return true;
    }

    public function send(TransactionalMessage $message): SendResult
    {
        return new SendResult('working', 'ok');
    }
}

final class BrokenProvider implements ProviderAdapter, TransactionalProvider
{
    public function __construct(private readonly bool $transient) {}

    public function name(): string
    {
        return 'broken';
    }

    public function supports(string $feature): bool
    {
        return true;
    }

    public function send(TransactionalMessage $message): SendResult
    {
        if ($this->transient) {
            throw new ProviderTransientException('Temporary failure.');
        }

        throw new MailbridgeException('Validation failure.');
    }
}
