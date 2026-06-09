# Components

The classes that make up the package and what each is responsible for. Namespace root is
`CleaniqueCoders\ConfigWebhook`.

## Map

| Concern | Class | Responsibility |
|---|---|---|
| Manager (facade root) | `ConfigWebhook` | Register events, map domain events, dispatch, sign |
| Facade | `Facades\ConfigWebhook` | Static access to the manager singleton |
| Models | `Models\Webhook`, `Models\WebhookDeliveryLog` | Subscribers and per-attempt logs |
| UUID trait | `Concerns\HasUuid` | Auto-fills a `uuid` on create (package-owned) |
| Status | `Enums\DeliveryStatus` | `PENDING`, `SUCCESS`, `FAILED`, `RETRYING` (+ `label()`, `color()`) |
| Delivery | `Jobs\SendWebhookEvent` | Queued; one try per job; schedules its own retry |
| Signing | `Support\WebhookSignature` | `generate()` / `verify()` (constant-time) |
| Events | `Events\WebhookDelivered`, `Events\WebhookFailed` | Fired after a successful / failed attempt |
| CLI | `Commands\PruneWebhookDeliveryLogsCommand` | `config-webhook:prune --days=30` |
| UI | `Livewire\Webhooks` | Optional Flux admin component |
| Provider | `ConfigWebhookServiceProvider` | Wires config, migrations, command, UI, route |

## Manager — `ConfigWebhook`

The facade root and a singleton. It holds the registered event catalogue and exposes the public
surface: `registerEvent(s)`, `listen`, `send`, `dispatchFromEvent`, `availableEvents`,
`groupedEvents`, `eventTypes`, `generateSignature`, `verifySignature`. See the
[API Reference](../04-api/01-manager.md).

## Models

### `Webhook`

A subscriber: `name`, `url`, encrypted `secret`, `events` (JSON array), optional `headers`,
`is_active`, `max_retries`, `timeout`, `last_triggered_at`. Key scopes:

- `active()` — only `is_active = true`
- `forEvent(string $type)` — `whereJsonContains('events', $type)`

The `secret` uses the `encrypted` cast, so a valid `APP_KEY` is required wherever a webhook is
created or read.

### `WebhookDeliveryLog`

One row per delivery attempt: `event_type`, `payload`, `response_status`, `response_body`
(truncated to `response_body_limit`), `response_time_ms`, `attempt`, `status`, `error_message`,
`next_retry_at`, `completed_at`. Belongs to a `Webhook`.

## Delivery job — `SendWebhookEvent`

Implements `ShouldQueue`. Pushed onto the queue named by `config('config-webhook.queue')`
(default `webhooks`). It has **`$tries = 1`** and performs its own retries: each failed attempt
marks the log `RETRYING` and re-dispatches itself `->delay(backoff)` with the next attempt
number, until `max_retries` is exhausted (then `FAILED`). See
[Delivery Lifecycle](03-delivery-lifecycle.md).

## Signing — `WebhookSignature`

Stateless helper. `generate($payload, $secret, $algo = null)` returns the HMAC of the raw JSON
body; `verify($payload, $signature, $secret, $algo = null)` compares in constant time. The
manager wraps these as `generateSignature()` / `verifySignature()`.

## Events

- `WebhookDelivered($deliveryLog)` — dispatched after a 2xx response.
- `WebhookFailed($deliveryLog, $error, $willRetry)` — dispatched after a failed attempt;
  `$willRetry` tells you whether another attempt is scheduled.

## Service provider

`ConfigWebhookServiceProvider` (built on `spatie/laravel-package-tools`) registers the config,
migrations, views, and the prune command; binds the manager singleton; and — only when Livewire
is installed — registers the UI component (`config-webhook.webhooks`) and, if enabled, the
admin route.

> **Livewire 4 note:** the component is registered as `config-webhook.webhooks` (a dot, not
> `::`). Under Livewire 4 a `::` in the name triggers namespace resolution that ignores singly
> registered components, throwing `ComponentNotFoundException`.

## Next Steps

- [Delivery Lifecycle](03-delivery-lifecycle.md) — how the job behaves per attempt
- [API Reference](../04-api/README.md) — method signatures
- [Configuration](../03-configuration/README.md) — table names, queue, signature headers
