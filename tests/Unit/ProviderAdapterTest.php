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
use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeValidationException;
use Ashraful19\LaravelMailbridge\Exceptions\ProviderTransientException;
use Ashraful19\LaravelMailbridge\MailbridgeManager;
use Ashraful19\LaravelMailbridge\Providers\AutosendProvider;
use Ashraful19\LaravelMailbridge\Providers\BrevoProvider;
use Ashraful19\LaravelMailbridge\Providers\KitProvider;
use Ashraful19\LaravelMailbridge\Providers\MailerliteProvider;
use Ashraful19\LaravelMailbridge\Providers\MailersendProvider;
use Ashraful19\LaravelMailbridge\Providers\MailchimpProvider;
use Ashraful19\LaravelMailbridge\Providers\MailgunProvider;
use Ashraful19\LaravelMailbridge\Providers\MailjetProvider;
use Ashraful19\LaravelMailbridge\Providers\PostmarkProvider;
use Ashraful19\LaravelMailbridge\Providers\ResendProvider;
use Ashraful19\LaravelMailbridge\Providers\SendgridProvider;
use Ashraful19\LaravelMailbridge\Providers\SesProvider;
use Ashraful19\LaravelMailbridge\Support\TransactionalMessageNormalizer;
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

    public function test_postmark_raw_arguments_match_sdk_signature_slots(): void
    {
        $message = $this->message();
        $message->attachments[] = ['content' => 'invoice-bytes', 'name' => 'invoice.txt', 'mime' => 'text/plain'];

        $args = (new PostmarkProvider('postmark', ['server_token' => 'token'], $this->app))->rawArguments($message);

        $this->assertNull($args[6]);
        $this->assertNull($args[7]);
        $this->assertIsArray($args[11]);
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

    public function test_sendgrid_maps_marketing_payloads(): void
    {
        $provider = new SendgridProvider('sendgrid', ['api_key' => 'key', 'marketing_sender_id' => '42'], $this->app);

        $subscriber = $provider->subscriberPayload(Subscriber::make('a@example.com')->name('Ash Islam')->field('company', 'Converlo'));
        $campaign = $provider->campaignPayload(
            Campaign::make('Launch')
                ->subject('Hello')
                ->html('<p>Hello</p>')
                ->list(123)
                ->option('categories', ['launch'])
        );

        $this->assertSame('a@example.com', $subscriber['email']);
        $this->assertSame('Ash', $subscriber['first_name']);
        $this->assertSame('Islam', $subscriber['last_name']);
        $this->assertSame('Converlo', $subscriber['company']);
        $this->assertSame('42', $campaign['sender_id']);
        $this->assertSame([123], $campaign['list_ids']);
        $this->assertSame(['launch'], $campaign['categories']);
    }

    public function test_sendgrid_uses_official_marketing_client_paths(): void
    {
        $client = new FakeSendgridSdk();
        $provider = new SendgridProvider('sendgrid', ['api_key' => 'key', 'marketing_sender_id' => '42'], $this->app, $client);

        $provider->subscribe('123', Subscriber::make('a@example.com')->name('Ash Islam'));
        $provider->unsubscribe('123', 'a@example.com');
        $provider->getSubscriber('a@example.com');
        $provider->deleteSubscriber('a@example.com');
        $created = $provider->createCampaign(Campaign::make('Launch')->subject('Hello')->html('<p>Hello</p>')->list(123));
        $provider->scheduleCampaign(456, '2026-06-01 10:00:00');
        $provider->sendCampaign(456);
        $provider->getCampaign(456);
        $provider->deleteCampaign(456);

        $this->assertSame('sg_campaign_123', $created->metadata['campaign_id']);
        $this->assertContains('/contactdb/recipients', $client->client->paths());
        $this->assertContains('/contactdb/lists/123/recipients', $client->client->paths());
        $this->assertContains('/contactdb/lists/123/recipients/recipient_123', $client->client->paths());
        $this->assertContains('/contactdb/recipients/search', $client->client->paths());
        $this->assertContains('/contactdb/recipients/recipient_123', $client->client->paths());
        $this->assertContains('/campaigns', $client->client->paths());
        $this->assertContains('/campaigns/456/schedules', $client->client->paths());
        $this->assertContains('/campaigns/456/schedules/now', $client->client->paths());
        $this->assertContains('/campaigns/456', $client->client->paths());
    }

    public function test_sendgrid_rejects_non_numeric_list_ids(): void
    {
        $provider = new SendgridProvider('sendgrid', ['api_key' => 'key', 'marketing_sender_id' => '42'], $this->app);

        $this->expectException(MailbridgeValidationException::class);
        $provider->campaignPayload(Campaign::make('Launch')->subject('Hello')->html('<p>Hello</p>')->list('not-numeric'));
    }

    public function test_ses_maps_template_payload(): void
    {
        $message = $this->message();
        $message->templateId = 'welcome';
        $message->data = ['name' => 'Ash'];

        $payload = (new SesProvider('ses', ['key' => 'key', 'secret' => 'secret', 'region' => 'us-east-1'], $this->app))->templatePayload($message);

        $this->assertSame('Sender <sender@example.com>', $payload['Source']);
        $this->assertSame(['A <a@example.com>'], $payload['Destination']['ToAddresses']);
        $this->assertSame('welcome', $payload['Template']);
        $this->assertSame('{"name":"Ash"}', $payload['TemplateData']);
        $this->assertSame(['Name' => 'tag', 'Value' => 'welcome'], $payload['Tags'][0]);
    }

    public function test_mailjet_maps_template_payload(): void
    {
        $message = $this->message();
        $message->templateId = 123456;
        $message->data = ['name' => 'Ash'];
        $message->attachments[] = ['content' => 'invoice-bytes', 'name' => 'invoice.txt', 'mime' => 'text/plain'];

        $payload = (new MailjetProvider('mailjet', ['api_key' => 'key', 'secret_key' => 'secret'], $this->app))->payload($message);
        $mail = $payload['Messages'][0];

        $this->assertSame(123456, $mail['TemplateID']);
        $this->assertTrue($mail['TemplateLanguage']);
        $this->assertSame(['name' => 'Ash'], $mail['Variables']);
        $this->assertSame([['Email' => 'a@example.com', 'Name' => 'A']], $mail['To']);
        $this->assertSame('{"campaign":"signup"}', $mail['CustomID']);
        $this->assertSame(base64_encode('invoice-bytes'), $mail['Attachments'][0]['Base64Content']);
    }

    public function test_mailjet_maps_campaign_payload(): void
    {
        $payload = (new MailjetProvider('mailjet', ['api_key' => 'key', 'secret_key' => 'secret'], $this->app))->campaignPayload(
            Campaign::make('Launch')->subject('Hello')->html('<p>Hello</p>')->from('sender@example.com', 'Sender')->list(123)
        );

        $this->assertSame('Launch', $payload['Title']);
        $this->assertSame('Hello', $payload['Subject']);
        $this->assertSame(123, $payload['ContactsListID']);
    }

    public function test_mailjet_rejects_non_numeric_list_ids(): void
    {
        $provider = new MailjetProvider('mailjet', ['api_key' => 'key', 'secret_key' => 'secret'], $this->app);

        $this->expectException(MailbridgeValidationException::class);
        $provider->campaignPayload(Campaign::make('Launch')->subject('Hello')->html('<p>Hello</p>')->list('group-id'));
    }

    public function test_mailchimp_maps_transactional_template_payload(): void
    {
        $message = $this->message();
        $message->templateId = 'welcome';
        $message->data = ['name' => 'Ash'];
        $message->attachments[] = ['content' => 'invoice-bytes', 'name' => 'invoice.txt', 'mime' => 'text/plain'];

        $payload = (new MailchimpProvider('mailchimp', ['api_key' => 'key', 'server' => 'us1', 'audience_id' => 'aud', 'transactional_api_key' => 'tx'], $this->app))->transactionalTemplatePayload($message);
        $mail = $payload['message'];

        $this->assertSame('welcome', $payload['template_name']);
        $this->assertSame('sender@example.com', $mail['from_email']);
        $this->assertSame(['name' => 'name', 'content' => 'Ash'], $mail['global_merge_vars'][0]);
        $this->assertSame(['email' => 'a@example.com', 'name' => 'A', 'type' => 'to'], $mail['to'][0]);
        $this->assertSame(base64_encode('invoice-bytes'), $mail['attachments'][0]['content']);
    }

    public function test_mailchimp_maps_subscriber_and_campaign_payloads(): void
    {
        $provider = new MailchimpProvider('mailchimp', ['api_key' => 'key', 'server' => 'us1', 'audience_id' => 'aud', 'transactional_api_key' => 'tx'], $this->app);

        $subscriber = $provider->subscriberPayload(Subscriber::make('a@example.com')->name('Ash')->field('COMPANY', 'Converlo'));
        $campaign = $provider->campaignPayload(Campaign::make('Launch')->subject('Hello')->html('<p>Hello</p>')->from('sender@example.com', 'Sender')->list('audience-id'));

        $this->assertSame('subscribed', $subscriber['status']);
        $this->assertSame('Ash', $subscriber['merge_fields']['FNAME']);
        $this->assertSame('regular', $campaign['type']);
        $this->assertSame('audience-id', $campaign['recipients']['list_id']);
        $this->assertSame('Hello', $campaign['settings']['subject_line']);
    }

    public function test_kit_maps_list_targets_and_broadcast_filters(): void
    {
        $provider = new KitProvider('kit', ['api_key' => 'key'], $this->app);

        $this->assertSame(['type' => 'tag', 'id' => 123], $provider->listTarget('123'));
        $this->assertSame(['type' => 'form', 'id' => 456], $provider->listTarget('form:456'));

        $payload = $provider->campaignPayload(
            Campaign::make('Launch')->subject('Hello')->html('<p>Hello</p>')->from('sender@example.com')->list('tag:123')
        );

        $this->assertSame('Hello', $payload['subject']);
        $this->assertSame('sender@example.com', $payload['email_address']);
        $this->assertSame([['all' => [['type' => 'tag', 'ids' => [123]]]]], $payload['subscriber_filter']);
    }

    public function test_kit_supports_form_and_sequence_filters(): void
    {
        $provider = new KitProvider('kit', ['api_key' => 'key'], $this->app);

        $formPayload = $provider->campaignPayload(
            Campaign::make('Launch')->subject('Hello')->html('<p>Hello</p>')->from('sender@example.com')->list('form:12')
        );
        $sequencePayload = $provider->campaignPayload(
            Campaign::make('Launch')->subject('Hello')->html('<p>Hello</p>')->from('sender@example.com')->list('sequence:34')
        );

        $this->assertSame([['all' => [['type' => 'segment', 'ids' => [12]]]]], $formPayload['subscriber_filter']);
        $this->assertSame([['all' => [['type' => 'segment', 'ids' => [34]]]]], $sequencePayload['subscriber_filter']);
    }

    public function test_kit_send_campaign_maps_to_campaign_send_operation(): void
    {
        $client = new FakeKitClient();
        $provider = new KitProvider('kit', ['api_key' => 'key'], $this->app, $client);

        $result = $provider->sendCampaign(99);

        $this->assertSame('campaign_send', $result->operation);
        $this->assertSame(99, $result->metadata['campaign_id']);
        $this->assertSame(1, $client->updateCalls);
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

    public function test_brevo_rejects_non_numeric_list_ids(): void
    {
        $provider = new BrevoProvider('brevo', ['api_key' => 'key'], $this->app);

        $this->expectException(MailbridgeValidationException::class);
        $provider->campaignPayload(Campaign::make('Launch')->subject('Hello')->html('<p>Hello</p>')->from('sender@example.com', 'Sender')->list('abc'));
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

    public function test_transactional_provider_level_from_used_when_message_from_missing(): void
    {
        $message = new TransactionalMessage();
        $message->to[] = Address::make('a@example.com', 'A');
        $message->subject = 'Hello';
        $message->html = '<p>Hello</p>';

        $provider = new CapturingFromProvider(
            'provider-a',
            $this->app,
            ['from' => ['address' => 'provider@example.com', 'name' => 'Provider Sender']],
            false
        );

        $result = $provider->send($message);

        $this->assertSame('Provider Sender <provider@example.com>', $result->metadata['from']);
    }

    public function test_transactional_fallback_uses_each_provider_specific_from(): void
    {
        config()->set('mailbridge.fallbacks.transactional', ['provider-b']);
        config()->set('mailbridge.from.address', 'global@example.com');
        config()->set('mailbridge.from.name', 'Global Sender');

        $manager = app(MailbridgeManager::class);
        $this->setResolvedProviders($manager, [
            'provider-a' => new CapturingFromProvider(
                'provider-a',
                $this->app,
                ['from' => ['address' => 'a@example.com', 'name' => 'Provider A']],
                true
            ),
            'provider-b' => new CapturingFromProvider(
                'provider-b',
                $this->app,
                ['from' => ['address' => 'b@example.com', 'name' => 'Provider B']],
                false
            ),
        ]);

        $message = new TransactionalMessage();
        $message->to[] = Address::make('a@example.com', 'A');
        $message->subject = 'Hello';
        $message->html = '<p>Hello</p>';

        $result = $manager->sendTransactional($message, 'provider-a', true);

        $this->assertSame('provider-b', $result->provider);
        $this->assertSame('Provider B <b@example.com>', $result->metadata['from']);
    }

    public function test_marketing_campaign_sender_falls_back_to_provider_and_global_defaults(): void
    {
        config()->set('mailbridge.from.address', 'global@example.com');
        config()->set('mailbridge.from.name', 'Global Sender');

        $providerOnly = (new MailjetProvider('mailjet', [
            'api_key' => 'key',
            'secret_key' => 'secret',
            'from' => ['address' => 'provider@example.com', 'name' => 'Provider Sender'],
        ], $this->app))->campaignPayload(
            Campaign::make('Launch')->subject('Hello')->html('<p>Hello</p>')->list(123)
        );

        $globalOnly = (new MailjetProvider('mailjet', ['api_key' => 'key', 'secret_key' => 'secret'], $this->app))->campaignPayload(
            Campaign::make('Launch')->subject('Hello')->html('<p>Hello</p>')->list(123)
        );

        $explicit = (new MailjetProvider('mailjet', [
            'api_key' => 'key',
            'secret_key' => 'secret',
            'from' => ['address' => 'provider@example.com', 'name' => 'Provider Sender'],
        ], $this->app))->campaignPayload(
            Campaign::make('Launch')->subject('Hello')->html('<p>Hello</p>')->from('explicit@example.com', 'Explicit Sender')->list(123)
        );

        $this->assertSame('provider@example.com', $providerOnly['SenderEmail']);
        $this->assertSame('Provider Sender', $providerOnly['Sender']);
        $this->assertSame('global@example.com', $globalOnly['SenderEmail']);
        $this->assertSame('Global Sender', $globalOnly['Sender']);
        $this->assertSame('explicit@example.com', $explicit['SenderEmail']);
        $this->assertSame('Explicit Sender', $explicit['Sender']);
    }

    public function test_autosend_maps_raw_payload(): void
    {
        $payload = (new AutosendProvider('autosend', ['api_key' => 'key'], $this->app))->payload($this->message());

        $this->assertSame(['email' => 'sender@example.com', 'name' => 'Sender'], $payload['from']);
        $this->assertSame(['email' => 'a@example.com', 'name' => 'A'], $payload['to']);
        $this->assertSame('Hello', $payload['subject']);
        $this->assertSame('<p>Hello</p>', $payload['html']);
        $this->assertSame('Hello', $payload['text']);
        $this->assertSame('welcome', $payload['headers']['X-Mailbridge-Tags']);
        $this->assertSame('signup', $payload['headers']['campaign']);
    }

    public function test_autosend_maps_template_payload(): void
    {
        $message = $this->message();
        $message->templateId = 'A-welcome123';
        $message->data = ['name' => 'Ash'];

        $payload = (new AutosendProvider('autosend', ['api_key' => 'key'], $this->app))->payload($message);

        $this->assertSame('A-welcome123', $payload['templateId']);
        $this->assertSame(['name' => 'Ash'], $payload['dynamicData']);
        $this->assertArrayNotHasKey('subject', $payload);
        $this->assertArrayNotHasKey('html', $payload);
    }

    public function test_autosend_maps_campaign_payload(): void
    {
        $payload = (new AutosendProvider('autosend', [
            'api_key' => 'key',
            'from' => ['address' => 'provider@example.com', 'name' => 'Provider'],
        ], $this->app))->campaignPayload(
            Campaign::make('Launch')
                ->subject('Hello World')
                ->html('<p>Hey</p>')
                ->list('list_abc123')
                ->option('sendNow', true)
        );

        $this->assertSame('Launch', $payload['name']);
        $this->assertSame('Hello World', $payload['subject']);
        $this->assertSame('<p>Hey</p>', $payload['htmlTemplate']);
        $this->assertSame(['list_abc123'], $payload['toLists']);
        $this->assertTrue($payload['publish']);
        $this->assertTrue($payload['sendNow']);
        $this->assertSame('provider@example.com', $payload['from']['email']);
    }

    public function test_autosend_maps_attachments(): void
    {
        $message = $this->message();
        $message->attachments[] = ['content' => 'invoice-bytes', 'name' => 'invoice.txt', 'mime' => 'text/plain'];

        $payload = (new AutosendProvider('autosend', ['api_key' => 'key'], $this->app))->payload($message);

        $this->assertCount(1, $payload['attachments']);
        $this->assertSame('invoice.txt', $payload['attachments'][0]['fileName']);
        $this->assertSame(base64_encode('invoice-bytes'), $payload['attachments'][0]['content']);
        $this->assertSame('text/plain', $payload['attachments'][0]['contentType']);
    }

    public function test_autosend_cc_and_bcc_are_mapped(): void
    {
        $message = $this->message();
        $message->cc[] = Address::make('cc@example.com', 'CC User');
        $message->bcc[] = Address::make('bcc@example.com');

        $payload = (new AutosendProvider('autosend', ['api_key' => 'key'], $this->app))->payload($message);

        $this->assertCount(1, $payload['cc']);
        $this->assertSame('cc@example.com', $payload['cc'][0]['email']);
        $this->assertSame('CC User', $payload['cc'][0]['name']);
        $this->assertCount(1, $payload['bcc']);
        $this->assertSame('bcc@example.com', $payload['bcc'][0]['email']);
    }

    public function test_autosend_maps_subscriber_payload_and_splits_name(): void
    {
        $payload = (new AutosendProvider('autosend', ['api_key' => 'key'], $this->app))->subscriberPayload(
            Subscriber::make('a@example.com')->name('Ash Islam')->field('company', 'Converlo')
        );

        $this->assertSame('a@example.com', $payload['email']);
        $this->assertSame('Ash', $payload['firstName']);
        $this->assertSame('Islam', $payload['lastName']);
        $this->assertSame('Converlo', $payload['customFields']['company']);
    }

    public function test_autosend_subscriber_payload_single_name(): void
    {
        $payload = (new AutosendProvider('autosend', ['api_key' => 'key'], $this->app))->subscriberPayload(
            Subscriber::make('a@example.com')->name('Ash')
        );

        $this->assertSame('Ash', $payload['firstName']);
        $this->assertArrayNotHasKey('lastName', $payload);
    }

    public function test_autosend_subscriber_payload_no_name(): void
    {
        $payload = (new AutosendProvider('autosend', ['api_key' => 'key'], $this->app))->subscriberPayload(
            Subscriber::make('a@example.com')
        );

        $this->assertArrayNotHasKey('firstName', $payload);
        $this->assertArrayNotHasKey('lastName', $payload);
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

final class FakeSendgridSdk
{
    public FakeSendgridClient $client;

    public function __construct()
    {
        $this->client = new FakeSendgridClient();
    }
}

final class FakeSendgridClient
{
    public array $requests = [];

    public function __call(string $name, array $arguments): FakeSendgridEndpoint
    {
        return (new FakeSendgridEndpoint($this))->__call($name, $arguments);
    }

    public function paths(): array
    {
        return array_column($this->requests, 'path');
    }
}

final class FakeSendgridEndpoint
{
    public function __construct(
        private readonly FakeSendgridClient $client,
        private readonly array $segments = [],
    ) {}

    public function __call(string $name, array $arguments): self
    {
        return new self($this->client, [...$this->segments, $name]);
    }

    public function _(string|int $id): self
    {
        return new self($this->client, [...$this->segments, (string) $id]);
    }

    public function post(mixed $body = null, mixed $query = null): FakeSendgridResponse
    {
        return $this->record('POST', $body, $query);
    }

    public function get(mixed $body = null, mixed $query = null): FakeSendgridResponse
    {
        return $this->record('GET', $body, $query);
    }

    public function delete(mixed $body = null, mixed $query = null): FakeSendgridResponse
    {
        return $this->record('DELETE', $body, $query);
    }

    private function record(string $method, mixed $body, mixed $query): FakeSendgridResponse
    {
        $path = '/' . implode('/', $this->segments);
        $this->client->requests[] = compact('method', 'path', 'body', 'query');

        return match ([$method, $path]) {
            ['POST', '/contactdb/recipients'] => new FakeSendgridResponse(['persisted_recipients' => ['recipient_123']]),
            ['POST', '/contactdb/recipients/search'] => new FakeSendgridResponse(['recipients' => [['id' => 'recipient_123', 'email' => 'a@example.com']]]),
            ['GET', '/contactdb/recipients/search'] => new FakeSendgridResponse(['recipients' => [['id' => 'recipient_123', 'email' => 'a@example.com']]]),
            ['POST', '/campaigns'] => new FakeSendgridResponse(['id' => 'sg_campaign_123']),
            default => new FakeSendgridResponse([]),
        };
    }
}

final class FakeSendgridResponse
{
    public function __construct(private readonly array $body, private readonly int $status = 202) {}

    public function statusCode(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return json_encode($this->body, JSON_THROW_ON_ERROR);
    }

    public function headers(bool $assoc = false): array
    {
        return $assoc ? ['X-Message-Id' => 'sg_123'] : [];
    }
}

final class FakeKitClient
{
    public int $updateCalls = 0;

    public function update_broadcast(int $id, bool $public, \DateTimeInterface $send_at): void
    {
        $this->updateCalls++;
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

final class CapturingFromProvider implements ProviderAdapter, TransactionalProvider
{
    public function __construct(
        private readonly string $providerName,
        private readonly \Illuminate\Contracts\Container\Container $app,
        private readonly array $config,
        private readonly bool $transientFailure,
    ) {}

    public function name(): string
    {
        return $this->providerName;
    }

    public function supports(string $feature): bool
    {
        return true;
    }

    public function send(TransactionalMessage $message): SendResult
    {
        $normalized = (new TransactionalMessageNormalizer($this->app))->normalize($message, $this->config);

        if ($this->transientFailure) {
            throw new ProviderTransientException('Temporary failure.');
        }

        return new SendResult($this->providerName, 'ok', [
            'from' => (string) $normalized->from?->name . ' <' . (string) $normalized->from?->email . '>',
        ]);
    }
}
