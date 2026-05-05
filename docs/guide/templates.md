# Templates

Hosted templates let the provider own the email design while your Laravel app sends recipients and variables through MailBridge.

MailBridge supports two ways to choose a hosted template:

- `template('welcome')` uses a config alias and resolves the provider-specific id.
- `templateId('...')` sends directly with the provider template id.

## Template Alias

```php
MailBridge::transactional()
    ->template('welcome')
    ->to($user->email)
    ->data(['name' => $user->name])
    ->send();
```

Configure aliases once:

```php
'templates' => [
    'welcome' => [
        'brevo' => 123,
        'mailersend' => 'ms-template-id',
        'postmark' => 'welcome-alias',
        'mailgun' => 'welcome',
    ],
],
```

## Direct Provider Template ID

```php
MailBridge::transactional()
    ->templateId('welcome-template-id')
    ->to($user->email)
    ->data(['name' => $user->name])
    ->send();
```

Use exactly one of `template()` or `templateId()`.

## Common vs Provider-Specific Data

This is important when you want one application workflow but each provider expects different variable names.

Use `data()` for variables shared by every provider:

```php
MailBridge::transactional()
    ->template('welcome')
    ->to($user->email)
    ->data([
        'name' => $user->name,
        'app_url' => config('app.url'),
    ])
    ->send();
```

Use `dataFor()` when one provider expects different variable names or values:

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
    ->dataFor('postmark', [
        'name' => $user->name,
    ])
    ->send();
```

Provider-specific data overrides common data for the selected provider:

```php
MailBridge::transactional()
    ->templateId('welcome-template-id')
    ->to($user->email)
    ->data(['name' => 'Ash'])
    ->dataFor('brevo', ['name' => 'Ashraful'])
    ->send();
```

If Brevo sends the message, final template data is:

```php
[
    'name' => 'Ashraful',
]
```

`templateDataFor()` is available as a readable alias for `dataFor()`.

## Provider Fallback With Template Data

Provider-specific data is resolved for the provider that actually sends the message. If the first provider fails transiently and fallback is enabled, the fallback provider gets its own override.

```php
MailBridge::transactional('brevo')
    ->withFallback()
    ->template('welcome')
    ->to($user->email)
    ->data(['name' => $user->name])
    ->dataFor('brevo', ['FIRSTNAME' => $user->name])
    ->dataFor('postmark', ['name' => $user->name])
    ->send();
```

If Brevo sends, Brevo receives `FIRSTNAME`. If MailBridge falls back to Postmark, Postmark receives its own `name` value.
