# Marketing

Marketing operations are for subscriber lists, groups, campaign sends, and contact lifecycle actions. MailBridge keeps the method names stable while SendGrid, Brevo, Mailchimp, Kit, MailerLite, and Mailjet use their own SDKs internally.

## Subscribe

```php
use Ashraful19\LaravelMailbridge\Data\Subscriber;

MailBridge::marketing()
    ->list('signup')
    ->subscribe(Subscriber::make($user->email)->name($user->name));
```

## Unsubscribe

```php
MailBridge::marketing()
    ->list('signup')
    ->unsubscribe($user->email);
```

## Lookup and Delete Subscribers

```php
$subscriber = MailBridge::marketing()
    ->getSubscriber($user->email);

MailBridge::marketing()
    ->deleteSubscriber($user->email);
```

## Campaigns

Campaigns are marketing/broadcast emails sent to a list or group. They are different from transactional email because they usually target many subscribers, use marketing reporting, and may be scheduled.

```php
use Ashraful19\LaravelMailbridge\Data\Campaign;

$result = MailBridge::marketing('brevo')
    ->createCampaign(
        Campaign::make('Product Launch')
            ->subject('New release is live')
            ->html('<h1>Launch</h1>')
            ->from('hello@example.com', 'Example App')
            ->list('signup')
    );

$campaignId = $result->metadata['campaign_id'];

MailBridge::marketing('brevo')->scheduleCampaign($campaignId, '2026-06-01 10:00:00');
MailBridge::marketing('brevo')->sendCampaign($campaignId);
MailBridge::marketing('brevo')->getCampaign($campaignId);
MailBridge::marketing('brevo')->deleteCampaign($campaignId);
```

Mailchimp maps MailBridge lists to Mailchimp audiences. Subscriber tags map to member tags, and campaigns map to regular Mailchimp campaigns with HTML content set after draft creation.

SendGrid maps MailBridge lists to ContactDB list ids. Campaigns use SendGrid campaign endpoints and need a verified Marketing Campaigns sender id:

```dotenv
SENDGRID_MARKETING_SENDER_ID=123456
```

You can also pass it per campaign:

```php
Campaign::make('Product Launch')
    ->subject('New release is live')
    ->html('<h1>Launch</h1>')
    ->list('signup')
    ->option('sender_id', 123456);
```

Kit does not have traditional lists. Configure list aliases as `tag:<id>`, `form:<id>`, or `sequence:<id>`. A bare numeric value is treated as a tag id:

```php
'lists' => [
    'signup' => [
        'kit' => 'tag:123',
    ],
],
```

For Kit, `unsubscribe()` removes a tag when the alias is `tag:<id>`. For form or sequence aliases, Kit performs a global email unsubscribe. Kit campaigns map to broadcasts, and `sendCampaign()` schedules the broadcast for near-immediate send.

SendGrid, Mailjet, Mailchimp, and Brevo support create, send, schedule, get, and delete where the provider API accepts the common campaign shape. Kit supports broadcast create, schedule, get, and delete. MailerLite supports create, schedule, get, and delete through its SDK. Unsupported provider methods throw `UnsupportedMailbridgeFeature`.

## Provider Override and Fallback

```php
MailBridge::marketing('mailerlite')
    ->withFallback()
    ->list('signup')
    ->subscribe(Subscriber::make($user->email));
```
