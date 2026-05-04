# Marketing

```php
use Ashraful19\LaravelMailbridge\Data\Subscriber;

MailBridge::marketing()
    ->list('signup')
    ->subscribe(Subscriber::make($user->email)->name($user->name));
```

```php
MailBridge::marketing('mailerlite')
    ->withFallback()
    ->list('signup')
    ->subscribe(Subscriber::make($user->email));
```
