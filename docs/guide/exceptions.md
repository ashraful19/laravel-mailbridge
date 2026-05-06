# Exception Handling

MailBridge uses typed exceptions so application code can react differently to validation issues, provider/API failures, and retryable transient failures.

## Exception Hierarchy

`MailbridgeException` is the base package exception.

`MailbridgeValidationException` extends `MailbridgeException` and represents validation/configuration issues.

Specialized validation exceptions include:

- `MissingTransactionalRecipientException`
- `TemplatePayloadConflictException`
- `MissingTransactionalContentException`
- `MissingFromAddressException`
- `MissingTemplateMappingException`
- `UnknownProviderException`
- `UnknownDriverException`

`ProviderTransientException` extends `MailbridgeException` and signals retryable provider failures.

## Common Triggers

| Exception | Trigger |
| --- | --- |
| `MissingTransactionalRecipientException` | Transactional send without any `to()` recipient. |
| `TemplatePayloadConflictException` | Both `template()` and `templateId()` are set, or template send is combined with a Laravel `Mailable`. |
| `MissingTransactionalContentException` | Non-template send has no `html()`, no `text()`, and no mailable content. |
| `MissingFromAddressException` | No sender resolved from explicit `from()` and `mailbridge.from.address`. |
| `MissingTemplateMappingException` | Missing or empty mapping for `mailbridge.templates.<alias>.<provider>`. |
| `UnknownProviderException` | Provider key is not defined in `mailbridge.providers`. |
| `UnknownDriverException` | Provider config has an unsupported `driver`. |
| `ProviderTransientException` | Retryable network/rate-limit/provider errors while calling provider SDKs. |

## Recommended Catch Patterns

Catch specialized exceptions when you want targeted handling:

```php
use Ashraful19\LaravelMailbridge\Exceptions\MissingTemplateMappingException;
use Ashraful19\LaravelMailbridge\Exceptions\ProviderTransientException;

try {
    MailBridge::transactional()
        ->template('welcome')
        ->to($user->email)
        ->data(['name' => $user->name])
        ->send();
} catch (MissingTemplateMappingException $e) {
    // Alert config issue or fallback to a raw template ID path.
} catch (ProviderTransientException $e) {
    // Queue retry, trigger fallback workflow, or return a temporary failure response.
}
```

Catch `MailbridgeValidationException` for broad validation handling without listing every subtype:

```php
use Ashraful19\LaravelMailbridge\Exceptions\MailbridgeValidationException;

try {
    // MailBridge operation...
} catch (MailbridgeValidationException $e) {
    // Handle invalid input/config in one place.
}
```

## Backward Compatibility

Existing code that catches `MailbridgeValidationException` does not need to change.
Specialized exceptions are subclasses of `MailbridgeValidationException`.
