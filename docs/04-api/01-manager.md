# Manager — `ConfigWebhook`

The `CleaniqueCoders\ConfigWebhook\ConfigWebhook` manager is the facade root and a singleton. Use
it via the facade:

```php
use CleaniqueCoders\ConfigWebhook\Facades\ConfigWebhook;
```

All registration methods are chainable (return `static`).

## Registering events

### `registerEvent(string $type, ?string $label = null): static`

Register a single subscribable event type. The label (shown in the admin UI) defaults to the
type.

```php
ConfigWebhook::registerEvent('order.created', 'Order Created');
```

### `registerEvents(array $events): static`

Register many at once. Accepts a `type => label` map or a flat list of types.

```php
ConfigWebhook::registerEvents([
    'order.created' => 'Order Created',
    'user.registered' => 'User Registered',
]);
```

## Mapping a domain event

### `listen(string $domainEvent, string $type, callable $payloadResolver): static`

Wire a Laravel event class to a webhook type. When the event fires, the resolver converts it into
the payload `data`, and the package dispatches to every active subscriber of `$type`.

```php
ConfigWebhook::listen(
    OrderCreated::class,
    'order.created',
    fn (OrderCreated $event) => ['id' => $event->order->id],
);
```

## Dispatching

### `send(string $type, array $data = []): int`

Fan a payload out to every active webhook subscribed to `$type`. Returns the number of webhooks
the payload was queued for. No-op (returns `0`) when `config('config-webhook.feature')` is false.

```php
$count = ConfigWebhook::send('order.created', ['id' => 1]);
```

### `dispatchFromEvent(object $event): int`

Dispatch from an already-instantiated event object using the mapping registered with `listen()`.
This is what the auto-registered listener calls internally; you rarely call it directly. Returns
the number of webhooks queued.

## Inspecting the catalogue

### `availableEvents(): array`

The full `type => label` map of registered events.

### `eventTypes(): array`

Just the registered type keys (used to validate webhook subscriptions).

### `groupedEvents(): array`

Events grouped by their prefix (the segment before the first `.`) — e.g. `order` → `order.created`,
`order.cancelled`. Powers the grouped event picker in the admin UI.

## Signing

### `generateSignature(string $payload, string $secret): string`

HMAC of the raw payload using the configured algorithm. Thin wrapper over
`WebhookSignature::generate()`.

### `verifySignature(string $payload, string $signature, string $secret): bool`

Constant-time comparison. Thin wrapper over `WebhookSignature::verify()`. See
[Signatures](02-signatures.md).

## Next Steps

- [Signatures](02-signatures.md) — the signing helper in detail
- [Payload & Headers](03-payload-and-headers.md) — what `send()` puts on the wire
- [Usage](../02-development/02-usage.md) — task-oriented examples
