# Installation

Install the base package first:

```bash
composer require ashraful19/laravel-mailbridge
php artisan vendor:publish --tag=mailbridge-config
```

Laravel MailBridge supports PHP `^8.2` and Laravel `^12|^13`.

## Install Provider SDKs

The base package does not install every provider SDK. Install only the providers you use:

```bash
php artisan mailbridge:install
```

The command opens an interactive checklist. You can also install one provider directly:

```bash
php artisan mailbridge:install <provider>
```

Current provider values:
`sendgrid`, `ses`, `brevo`, `mailersend`, `resend`, `postmark`, `mailchimp`, `kit`, `mailerlite`, `mailgun`, `mailjet`

See [Provider Install](/guide/provider-install) for commands and exact SDK pins.
See [Provider Guides](/guide/providers) for provider-specific setup, mappings, and caveats.

## Configure Environment Variables

Set Laravel's normal mail sender first. MailBridge uses this as the default sender for SDK-based transactional sends:

```dotenv
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="Example App"
```

Then choose default MailBridge providers:

```dotenv
MAILBRIDGE_TRANSACTIONAL=brevo
MAILBRIDGE_MARKETING=mailerlite
MAILBRIDGE_TRANSACTIONAL_FALLBACKS=postmark,resend
MAILBRIDGE_MARKETING_FALLBACKS=brevo
```

Add provider runtime settings for the providers you installed:

```dotenv
BREVO_API_KEY=
SENDGRID_API_KEY=
SENDGRID_MARKETING_SENDER_ID=
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
MAILERSEND_API_KEY=
RESEND_API_KEY=
POSTMARK_SERVER_TOKEN=
MAILCHIMP_API_KEY=
MAILCHIMP_SERVER_PREFIX=us1
MAILCHIMP_AUDIENCE_ID=
MAILCHIMP_TRANSACTIONAL_API_KEY=
KIT_API_KEY=
MAILERLITE_API_KEY=
MAILGUN_API_KEY=
MAILGUN_DOMAIN=
MAILJET_API_KEY=
MAILJET_SECRET_KEY=
```

You only need env vars for providers you actually use.

## Configure Templates and Lists

Template and list identifiers are provider-specific. Define alias mappings in `config/mailbridge.php`:

```php
'templates' => [
    'welcome' => [
        'sendgrid' => 'd-welcome-template-id',
        'ses' => 'welcome',
        'brevo' => 123,
        'postmark' => 'welcome-alias',
        'mailchimp' => 'welcome-template',
        'mailgun' => 'welcome',
        'mailjet' => 123456,
    ],
],

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

If your deployment model requires environment-driven mappings, reference `env(...)` in config:

```php
'templates' => [
    'welcome' => [
        'sendgrid' => env('MAILBRIDGE_TEMPLATE_WELCOME_SENDGRID'),
        'mailjet' => (int) env('MAILBRIDGE_TEMPLATE_WELCOME_MAILJET'),
    ],
],

'lists' => [
    'signup' => [
        'sendgrid' => (int) env('MAILBRIDGE_LIST_SIGNUP_SENDGRID'),
        'mailchimp' => env('MAILBRIDGE_LIST_SIGNUP_MAILCHIMP'),
    ],
],
```

Your app can now use the same alias regardless of provider:

```php
MailBridge::transactional()
    ->template('welcome')
    ->to($user->email)
    ->data(['name' => $user->name])
    ->send();
```

## Check Setup

Run the doctor command after changing provider config:

```bash
php artisan mailbridge:doctor
```
