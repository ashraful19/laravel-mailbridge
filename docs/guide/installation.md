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
php artisan mailbridge:install brevo
php artisan mailbridge:install sendgrid
php artisan mailbridge:install mailerlite
```

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

Add the API keys for the providers you installed:

```dotenv
BREVO_API_KEY=
SENDGRID_API_KEY=
MAILERSEND_API_KEY=
RESEND_API_KEY=
POSTMARK_SERVER_TOKEN=
MAILGUN_API_KEY=
MAILGUN_DOMAIN=
MAILERLITE_API_KEY=
```

You only need env vars for providers you actually use.

## Configure Templates and Lists

Provider-hosted templates and marketing lists usually have different ids per provider. Map them in `config/mailbridge.php`:

```php
'templates' => [
    'welcome' => [
        'sendgrid' => 'd-welcome-template-id',
        'brevo' => 123,
        'postmark' => 'welcome-alias',
        'mailgun' => 'welcome',
    ],
],

'lists' => [
    'signup' => [
        'brevo' => 456,
        'mailerlite' => 'group-id',
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
