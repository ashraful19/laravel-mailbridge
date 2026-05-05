# Response Shapes

MailBridge keeps method names consistent across providers and returns lightweight result objects.

These objects are intentionally small:
- normalized top-level fields for app logic
- provider-native fields inside `metadata`/`data` for advanced use

## Transactional Send

Methods:
- `MailBridge::transactional()->send(...)`

Return type:
- `Ashraful19\LaravelMailbridge\Data\SendResult`

Shape:

```php
final readonly class SendResult
{
    public string $provider;
    public ?string $messageId;
    public array $metadata;
}
```

Example:

```php
$result = MailBridge::transactional()
    ->to('a@example.com')
    ->subject('Welcome')
    ->text('Hello')
    ->send();

$result->provider;  // e.g. "postmark"
$result->messageId; // provider message id when available
$result->metadata;  // provider extra fields (status, ids, etc.)
```

## Marketing Actions

Methods:
- `subscribe()`
- `unsubscribe()`
- `deleteSubscriber()`
- `createCampaign()`
- `sendCampaign()`
- `scheduleCampaign()`
- `getCampaign()`
- `deleteCampaign()`

Return type:
- `Ashraful19\LaravelMailbridge\Data\MarketingResult`

Shape:

```php
final readonly class MarketingResult
{
    public string $provider;
    public string $operation;
    public array $metadata;
}
```

Example:

```php
$result = MailBridge::marketing()
    ->list('signup')
    ->subscribe(Subscriber::make('a@example.com'));

$result->provider;  // e.g. "brevo"
$result->operation; // "subscribe"
$result->metadata;  // provider extras (list, ids, modes, etc.)
```

## Subscriber Lookup

Method:
- `getSubscriber()`

Return type:
- `Ashraful19\LaravelMailbridge\Data\SubscriberRecord|null`

Shape:

```php
final readonly class SubscriberRecord
{
    public string $provider;
    public string $email;
    public array $data;
}
```

Example:

```php
$record = MailBridge::marketing()->getSubscriber('a@example.com');

if ($record === null) {
    // not found
}

$record->provider; // e.g. "mailchimp"
$record->email;    // normalized email
$record->data;     // provider-native subscriber payload
```

## Metadata Guidance

- `metadata` and `data` are provider-specific and may differ by provider and operation.
- Use normalized fields (`provider`, `messageId`, `operation`) for generic app flow.
- Use `metadata`/`data` only when your app needs provider-specific details.
