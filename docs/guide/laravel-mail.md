# Normal Laravel Mail

Existing Laravel mail keeps working:

```php
Mail::to($user)->send(new WelcomeMail($user));
```

This uses Laravel `config/mail.php`. Laravel native `failover` and `roundrobin` mailers still work.
