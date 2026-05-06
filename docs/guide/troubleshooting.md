# Troubleshooting

Run `mailbridge:doctor` first:

```bash
php artisan mailbridge:doctor
```

It checks missing SDKs, wrong SDK versions, missing provider runtime keys, unsupported providers, and fallback config.

## Provider Setup

| Provider | Check |
| --- | --- |
| SendGrid | `SENDGRID_API_KEY` exists; sender authentication/domain authentication is complete. Marketing campaigns also need `SENDGRID_MARKETING_SENDER_ID` or `Campaign::option('sender_id', ...)`. |
| Amazon SES | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, and `AWS_DEFAULT_REGION` exist; sender identity is verified in the same SES region. |
| Postmark | `POSTMARK_SERVER_TOKEN` exists and sender signature/domain is verified. |
| Brevo | `BREVO_API_KEY` exists and transactional sender is verified. |
| Resend | `RESEND_API_KEY` exists and sender domain is verified. |
| MailerSend | `MAILERSEND_API_KEY` exists and sending domain is verified. |
| Mailchimp | `MAILCHIMP_API_KEY`, `MAILCHIMP_SERVER_PREFIX`, `MAILCHIMP_AUDIENCE_ID`, and `MAILCHIMP_TRANSACTIONAL_API_KEY` exist. Transactional sending requires the separate Mailchimp Transactional product. |
| Kit | `KIT_API_KEY` exists. List aliases must be `tag:<id>`, `form:<id>`, `sequence:<id>`, or a numeric tag id. |
| MailerLite | `MAILERLITE_API_KEY` exists and group ids are mapped for list aliases. |
| Mailgun | `MAILGUN_API_KEY` and `MAILGUN_DOMAIN` exist; domain DNS is verified. |
| Mailjet | `MAILJET_API_KEY` and `MAILJET_SECRET_KEY` exist; sender/domain is validated. |

For providers that require numeric list IDs in their native APIs (SendGrid, Brevo, Mailjet), MailBridge now validates list IDs before request payload creation and throws `MailbridgeValidationException` for invalid values.

For the full exception map and recommended catch patterns, see [Exception handling](/guide/exceptions).

For provider-specific setup and behavior differences, see [Provider Guides](/guide/providers).

## Template and List Mappings

If `template('welcome')` fails, add provider ids in `config/mailbridge.php`:

```php
'templates' => [
    'welcome' => [
        'sendgrid' => 'd-template-id',
        'ses' => 'welcome',
        'mailchimp' => 'welcome-template',
        'mailjet' => 123456,
    ],
],
```

`template('alias')` mappings must be present and non-empty for the selected provider.

If `list('signup')` fails for marketing providers, add list/group ids:

```php
'lists' => [
    'signup' => [
        'brevo' => 456,
        'sendgrid' => 123,
        'mailchimp' => 'audience-id',
        'kit' => 'tag:123',
        'mailerlite' => 'group-id',
        'mailjet' => 789,
    ],
],
```
