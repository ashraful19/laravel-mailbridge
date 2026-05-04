# Transactional

```php
MailBridge::transactional()
    ->to($user->email, $user->name)
    ->send(new WelcomeMail($user));
```

```php
MailBridge::transactional()
    ->to($user->email)
    ->subject('Welcome')
    ->html('<p>Hello</p>')
    ->send();
```

## Specific Provider

```php
MailBridge::transactional('postmark')
    ->withFallback(false)
    ->to($user->email)
    ->subject('Welcome')
    ->text('Hello')
    ->send();
```

```php
MailBridge::transactional('postmark')
    ->withFallback()
    ->to($user->email)
    ->subject('Welcome')
    ->text('Hello')
    ->send();
```
