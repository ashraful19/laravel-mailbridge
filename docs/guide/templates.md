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
