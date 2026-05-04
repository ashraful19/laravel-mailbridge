# Capabilities

MailBridge has two lanes: transactional email and marketing email. Each provider adapter declares what it supports, and unsupported features throw `UnsupportedMailbridgeFeature` by default.

## Transactional Features

| Feature | Common API | Notes |
| --- | --- | --- |
| Raw send | `subject()`, `html()`, `text()`, `send()` | For simple app-rendered messages. |
| Laravel Mailables | `send(new WelcomeMail($user))` | Keeps existing Laravel mail classes usable. |
| Hosted templates | `template('welcome')` | Resolves provider-specific ids from `config/mailbridge.php`. |
| Direct template id | `templateId('...')` | Bypasses config mapping for one send. |
| Provider-specific data | `dataFor('brevo', [...])` | Provider data overrides common `data()` values. |
| Recipients | `to()`, `cc()`, `bcc()` | Normalized address objects. |
| Sender controls | `from()`, `replyTo()` | Falls back to app mail config where needed. |
| Tags/categories | `tag()` | Provider analytics labels where supported. |
| Metadata | `metadata()` | Safe correlation data for reporting/webhooks. |
| Provider options | `withProviderOptions()` | Escape hatch for provider-only settings. |
| Provider override | `transactional('postmark')` | Uses one provider for this request. |
| Fallback | `withFallback()` | Selected/default provider first, configured fallbacks after. |
| Testing | `MailBridge::fake()` | Fake assertions for app tests. |

## Marketing Features

| Feature | Common API | Notes |
| --- | --- | --- |
| Subscribe | `list('signup')->subscribe($subscriber)` | Resolves list aliases from config. |
| Subscriber data | `Subscriber::make()->name()->field()` | Normalized contact profile data. |
| Tags/groups | `Subscriber::make()->tag()` | Maps to provider concepts where supported. |
| Unsubscribe | Planned common method | Supported providers will map list/audience removal. |
| Subscriber lookup | Planned common method | Used for sync and admin workflows. |
| Subscriber delete | Planned common method | Provider-dependent destructive contact removal. |
| Campaigns | Planned common methods | Create, update, send, schedule where supported. |
| Reports | Planned common methods | Campaign stats where provider exposes them. |
| Provider override | `marketing('mailerlite')` | Uses one provider for this request. |
| Fallback | `withFallback()` | Retries transient provider/network failures only. |
| Testing | `MailBridge::fake()` | Fake assertions for marketing workflows. |

## Provider Matrix

| Provider | Transactional | Templates | Marketing | Campaigns | Webhooks |
| --- | --- | --- | --- | --- | --- |
| Brevo | Yes | Yes | Yes | Yes | Yes |
| MailerSend | Yes | Yes | No | No | Planned |
| Resend | Yes | Partial | Partial | No | Planned |
| Postmark | Yes | Yes | No | No | Yes |
| Mailgun | Yes | Yes | No | No | Yes |
| MailerLite | No | No | Yes | Yes | Yes |
| Log | Yes | Yes | Yes | No | No |
| Array | Yes | Yes | Yes | No | No |

## Capability Checks

```php
MailBridge::provider('brevo')->supports('marketing.campaigns');
MailBridge::provider('postmark')->supports('transactional.templates');
```
