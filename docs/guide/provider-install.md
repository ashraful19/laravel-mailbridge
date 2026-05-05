# Provider Install

Provider SDKs are optional and installed with exact tested versions. MailBridge tests these SDK pins in development, but they stay out of production dependencies until a provider is installed.

| Provider | Command |
| --- | --- |
| SendGrid | `php artisan mailbridge:install sendgrid` |
| Brevo | `php artisan mailbridge:install brevo` |
| MailerSend | `php artisan mailbridge:install mailersend` |
| Resend | `php artisan mailbridge:install resend` |
| Postmark | `php artisan mailbridge:install postmark` |
| Mailgun | `php artisan mailbridge:install mailgun` |
| MailerLite | `php artisan mailbridge:install mailerlite` |

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
