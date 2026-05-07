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

## Sender Resolution

For transactional sends, sender resolution order is:

1. `from()` set on the message at runtime.
2. `mailbridge.providers.<provider>.from.address` / `name`.
3. Global `mailbridge.from.address` / `name` (typically from `MAIL_FROM_*`).

If none resolves, MailBridge throws `MissingFromAddressException`.

## Return Type

`send()` returns `SendResult`:

```php
$result = MailBridge::transactional()->to($user->email)->subject('Welcome')->text('Hello')->send();

$result->provider;  // provider name
$result->messageId; // provider message id when available
$result->metadata;  // provider-specific extra fields
```

For full response shapes, see [Response Shapes](/guide/responses).
For provider-specific setup and caveats, see [Provider Guides](/guide/providers).

```php
MailBridge::transactional('postmark')
    ->withFallback()
    ->to($user->email)
    ->subject('Welcome')
    ->text('Hello')
    ->send();
```
