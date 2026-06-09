# Signatures — `WebhookSignature`

`CleaniqueCoders\ConfigWebhook\Support\WebhookSignature` is a stateless helper for HMAC signing
and verification. Every outgoing delivery is signed; subscribers verify to authenticate the
request.

## Methods

### `generate(string $payload, string $secret, ?string $algo = null): string`

Returns the HMAC of `$payload` (the raw JSON body) using `$secret`. When `$algo` is null it falls
back to `config('config-webhook.signature.algo')` (default `sha256`).

```php
use CleaniqueCoders\ConfigWebhook\Support\WebhookSignature;

$signature = WebhookSignature::generate($jsonBody, $webhook->secret);
```

### `verify(string $payload, string $signature, string $secret, ?string $algo = null): bool`

Recomputes the signature for `$payload` and compares it to `$signature` in **constant time**
(`hash_equals`), so verification is not vulnerable to timing attacks. Returns `true` on a match.

```php
$valid = WebhookSignature::verify($jsonBody, $signatureHeader, $sharedSecret);
```

## Verifying on the receiving side

A subscriber recomputes the HMAC of the **raw request body** (not a re-encoded array — byte
differences break the signature) and compares it to the signature header:

```php
use CleaniqueCoders\ConfigWebhook\Support\WebhookSignature;

public function handle(Request $request)
{
    $valid = WebhookSignature::verify(
        payload: $request->getContent(),
        signature: $request->header('X-Webhook-Signature'),
        secret: $this->sharedSecretForThisSubscriber(),
    );

    abort_unless($valid, 401);

    // ... process $request->json()
}
```

If you do not depend on the package's internals on the receiving end, you can reproduce the
signature with plain PHP — it is just `hash_hmac($algo, $body, $secret)`.

## Notes

- **Sign the bytes you send.** The package signs the exact JSON string it POSTs; verify against
  `$request->getContent()`, not `json_encode($request->all())`.
- **One secret per subscriber.** Each `Webhook` has its own `secret` (encrypted at rest). A
  receiver verifies with the secret it shares with that specific webhook.
- **Algorithm must match.** Both ends must use the same `algo`; change it centrally via config.

## Next Steps

- [Payload & Headers](03-payload-and-headers.md) — the body and headers that get signed
- [Manager API](01-manager.md) — `generateSignature()` / `verifySignature()` wrappers
