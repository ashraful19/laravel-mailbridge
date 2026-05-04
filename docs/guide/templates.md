# Templates

Template alias:

```php
MailBridge::transactional()
    ->template('welcome')
    ->to($user->email)
    ->data(['name' => $user->name])
    ->send();
```

Direct provider template id:

```php
MailBridge::transactional()
    ->templateId('welcome-template-id')
    ->to($user->email)
    ->data(['name' => $user->name])
    ->send();
```

Use exactly one of `template()` or `templateId()`.

## Common vs Provider-Specific Data

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
