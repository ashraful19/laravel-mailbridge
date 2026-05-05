# Transactional

Transactional sends are for application email: welcome messages, passwordless login links, invoices, receipts, lifecycle messages, and alerts.

MailBridge can send rendered Laravel `Mailable` classes, raw HTML/text, or provider-hosted templates through the selected transactional provider.

## Laravel Mailable

```php
MailBridge::transactional()
    ->to($user->email, $user->name)
    ->send(new WelcomeMail($user));
```

## Raw HTML/Text

```php
MailBridge::transactional()
    ->to($user->email)
    ->subject('Welcome')
    ->html('<p>Hello</p>')
    ->send();
```

## Hosted Template

Use `template()` when ids are mapped in `config/mailbridge.php`:

```php
MailBridge::transactional()
    ->template('welcome')
    ->to($user->email)
    ->data(['name' => $user->name])
    ->send();
```

Use `templateId()` when the provider template id is known at send time:

```php
MailBridge::transactional()
    ->templateId('welcome-template-id')
    ->to($user->email)
    ->data(['name' => $user->name])
    ->send();
```

## Provider-Specific Template Data

Different providers often use different variable names for the same template. Keep shared values in `data()`, then override only the provider-specific differences with `dataFor()`.

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

Final template data is `data()` merged with `dataFor($selectedProvider)`. Provider-specific values win.

## Specific Provider

```php
MailBridge::transactional('postmark')
    ->withFallback(false)
    ->to($user->email)
    ->subject('Welcome')
    ->text('Hello')
    ->send();
```

## Provider Notes

| Provider | Notes |
| --- | --- |
| SendGrid | Uses dynamic templates, categories, and custom args through `sendgrid/sendgrid`. |
| Amazon SES | Uses `SendEmail`, `SendTemplatedEmail`, and raw MIME for attachments through `aws/aws-sdk-php`. |
| Mailjet | Uses Send API v3.1 templates and variables through `mailjet/mailjet-apiv3-php`. |

```php
MailBridge::transactional('postmark')
    ->withFallback()
    ->to($user->email)
    ->subject('Welcome')
    ->text('Hello')
    ->send();
```
