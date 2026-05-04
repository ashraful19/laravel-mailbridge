# Fallback

Laravel native mail fallback handles normal `Mail::send()` transports.

MailBridge fallback handles direct SDK/template/marketing operations:

```php
'fallbacks' => [
    'transactional' => ['brevo', 'postmark', 'resend'],
    'marketing' => ['mailerlite', 'brevo'],
],
```

Only transient provider/network failures retry.

When a specific provider is selected, fallback is off by default:

```php
MailBridge::transactional('postmark')->withFallback(false);
```

Enable fallback for that send:

```php
MailBridge::transactional('postmark')->withFallback();
```

MailBridge tries the selected provider first. If that provider also exists in configured fallbacks, duplicates are removed and the next fallback provider is tried next.
