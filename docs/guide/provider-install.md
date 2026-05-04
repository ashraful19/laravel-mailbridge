# Provider Install

Provider SDKs are optional and installed with exact tested versions.

| Provider | Command |
| --- | --- |
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
