# Provider Install

Provider SDKs are optional and installed with exact tested versions. MailBridge tests these SDK pins in development, but they stay out of production dependencies until a provider is installed.

For provider-specific behavior, mappings, and caveats, see [Provider Guides](/guide/providers).

| Provider | Command |
| --- | --- |
| SendGrid | `php artisan mailbridge:install sendgrid` |
| Amazon SES | `php artisan mailbridge:install ses` |
| Brevo | `php artisan mailbridge:install brevo` |
| MailerSend | `php artisan mailbridge:install mailersend` |
| Resend | `php artisan mailbridge:install resend` |
| Postmark | `php artisan mailbridge:install postmark` |
| Mailchimp | `php artisan mailbridge:install mailchimp` |
| Kit | `php artisan mailbridge:install kit` |
| MailerLite | `php artisan mailbridge:install mailerlite` |
| Mailgun | `php artisan mailbridge:install mailgun` |
| Mailjet | `php artisan mailbridge:install mailjet` |

Run:

```bash
php artisan mailbridge:doctor
```

Interactive install:

```bash
php artisan mailbridge:install
```

The interactive command shows a terminal checklist and installs all selected provider SDKs.

Mailgun also installs exact HTTP client support packages:

```bash
composer require mailgun/mailgun-php:4.4.0 symfony/http-client:7.4.8 nyholm/psr7:1.8.2
```

Exact SDK pins used by the installer:

| Provider | Composer package |
| --- | --- |
| SendGrid | `sendgrid/sendgrid:8.1.11` |
| Amazon SES | `aws/aws-sdk-php:3.379.0` |
| Brevo | `getbrevo/brevo-php:2.0.14` |
| MailerSend | `mailersend/laravel-driver:3.1.0` |
| Resend | `resend/resend-php:1.1.0` |
| Postmark | `wildbit/postmark-php:7.0.0` |
| Mailchimp | `mailchimp/marketing:3.0.80` and `mailchimp/transactional:1.4.1` |
| Kit | `convertkit/convertkitapi:2.4` |
| MailerLite | `mailerlite/mailerlite-php:1.0.5` |
| Mailgun | `mailgun/mailgun-php:4.4.0` |
| Mailjet | `mailjet/mailjet-apiv3-php:1.6.6` |
