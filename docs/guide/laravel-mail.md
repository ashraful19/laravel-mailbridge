# Laravel Mail Compatibility

MailBridge does not replace Laravel's mailer. Existing `Mail::send()` flows keep working and still use Laravel `config/mail.php`:

```php
Mail::to($user)->send(new WelcomeMail($user));
```

This uses Laravel `config/mail.php`. Laravel native `failover` and `roundrobin` mailers still work.

Use MailBridge when you want the package value: one API over official provider SDKs, hosted templates, marketing lists, provider-specific template data, and MailBridge-level fallback.
