# Capabilities

MailBridge has two provider lanes: transactional email and marketing email. Each adapter declares what it supports, and unsupported features throw `UnsupportedMailbridgeFeature` by default.

Use this page to decide which provider should handle each lane and which parts of the common API are available today.

<div class="mb-capability-grid">
  <a class="mb-capability-card" href="#transactional-features">
    <span>Transactional</span>
    <strong>Mailables, raw sends, hosted templates, attachments, metadata, tags, fallback.</strong>
  </a>
  <a class="mb-capability-card" href="#template-data-strategy">
    <span>Template Data</span>
    <strong>Common `data()` plus provider-specific `dataFor()` overrides.</strong>
  </a>
  <a class="mb-capability-card" href="#marketing-features">
    <span>Marketing</span>
    <strong>Subscribe, unsubscribe, lookup/delete, campaigns, provider override, fallback.</strong>
  </a>
</div>

## Provider Matrix

| Provider | Transactional | Hosted Templates | Provider Data | Marketing | Campaigns |
| --- | --- | --- | --- | --- | --- |
| SendGrid | Yes | Yes | Yes | Contacts + lists | Create/send/schedule/get/delete |
| Amazon SES | Yes | Yes | Yes | No | No |
| Brevo | Yes | Yes | Yes | Subscribers + lists | Create/send/schedule/get/delete |
| MailerSend | Yes | Yes | Yes | No | No |
| Resend | Yes | Partial | Yes | No | No |
| Postmark | Yes | Yes | Yes | No | No |
| Mailchimp | Yes | Yes | Yes | Audiences + members | Create/send/schedule/get/delete |
| Kit | No | No | No | Subscribers + tags/forms/sequences | Broadcast create/schedule/get/delete |
| MailerLite | No | No | No | Subscribers + groups | Create/schedule/get/delete |
| Mailgun | Yes | Yes | Yes | No | No |
| Mailjet | Yes | Yes | Yes | Subscribers + lists | Create/send/schedule/get/delete |
| Log | Yes | Yes | Yes | Yes | Yes |
| Array | Yes | Yes | Yes | Yes | Yes |

## Transactional Features

| Feature | Common API | Notes |
| --- | --- | --- |
| Raw send | `subject()`, `html()`, `text()`, `send()` | For simple app-rendered messages. |
| Laravel Mailables | `send(new WelcomeMail($user))` | Keeps existing Laravel mail classes usable. |
| Hosted templates | `template('welcome')` | Resolves provider-specific ids from `config/mailbridge.php`. |
| Direct template id | `templateId('...')` | Bypasses config mapping for one send. |
| Provider-specific data | `dataFor('brevo', [...])` | Provider data overrides common `data()` values. |
| Recipients | `to()`, `cc()`, `bcc()` | Normalized address objects. |
| Sender controls | `from()`, `replyTo()` | Sender precedence: runtime `from()` -> provider `mailbridge.providers.<provider>.from` -> global `mailbridge.from`. |
| Attachments | `attach()`, `attachData()` | Maps file/raw attachments to provider SDK payloads. |
| Tags/categories | `tag()` | Provider analytics labels where supported. |
| Metadata | `metadata()` | Safe correlation data for reporting/webhooks. |
| Provider options | `withProviderOptions()` | Escape hatch for provider-only settings. |
| Provider override | `transactional('postmark')` | Uses one provider for this request. |
| Fallback | `withFallback()` | Selected/default provider first, configured fallbacks after. |
| Testing | `MailBridge::fake()` | Fake assertions for app tests. |

## Template Data Strategy

Provider-hosted templates rarely use identical variable names. MailBridge keeps the app workflow stable:

```php
MailBridge::transactional()
    ->template('welcome')
    ->to($user->email)
    ->data([
        'name' => $user->name,
        'app_url' => config('app.url'),
    ])
    ->dataFor('brevo', [
        'FIRSTNAME' => $user->name,
    ])
    ->dataFor('mailgun', [
        'first_name' => $user->name,
    ])
    ->send();
```

Merge rule:

```php
finalData = array_replace_recursive(data(), dataFor(selectedProvider))
```

Provider-specific data wins on key conflicts. Data for unused providers is ignored. During fallback, each attempted provider gets its own merged data.

## Marketing Features

| Feature | Common API | Notes |
| --- | --- | --- |
| Subscribe | `list('signup')->subscribe($subscriber)` | Resolves list aliases from config. |
| Unsubscribe | `list('signup')->unsubscribe($email)` | Removes a subscriber from a list/group. |
| Subscriber lookup | `getSubscriber($email)` | Returns normalized `SubscriberRecord` or `null`. |
| Subscriber delete | `deleteSubscriber($email)` | Deletes the subscriber/contact where supported. |
| Subscriber data | `Subscriber::make()->name()->field()` | Normalized contact profile data. |
| Tags/groups | `Subscriber::make()->tag()` | Maps to provider concepts where supported. |
| Campaign create | `createCampaign(Campaign::make(...))` | Creates a marketing campaign/broadcast. |
| Campaign send | `sendCampaign($id)` | Sends immediately where provider supports it. |
| Campaign schedule | `scheduleCampaign($id, $when)` | Schedules a campaign where provider supports it. |
| Campaign report/get | `getCampaign($id)` | Returns provider campaign details/report data. |
| Campaign delete | `deleteCampaign($id)` | Deletes a campaign/draft where supported. |
| Provider override | `marketing('mailerlite')` | Uses one provider for this request. |
| Fallback | `withFallback()` | Retries transient provider/network failures only. |
| Testing | `MailBridge::fake()` | Fake assertions for marketing workflows. |

For provider-specific mappings and caveats, see [Provider Guides](/guide/providers).

## Campaign Purpose

Campaigns are marketing/broadcast emails sent to a list, group, or audience. Use them for newsletters, launches, and announcements. Use transactional email for one-recipient operational mail such as receipts, login links, and notifications.

## Planned Common APIs

| Area | Status |
| --- | --- |
| Webhook normalization | Planned with provider verification first. |
| Rich campaign reporting normalization | Current `getCampaign()` returns provider data; a normalized report shape can come later. |

## Capability Checks

```php
MailBridge::provider('brevo')->supports('marketing.lists');
MailBridge::provider('postmark')->supports('transactional.templates');
```
