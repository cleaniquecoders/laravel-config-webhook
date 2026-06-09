# Configuration Reference

Every key in `config/config-webhook.php`, grouped by concern. Defaults shown are the package
defaults.

## Feature toggle

```php
'feature' => env('CONFIG_WEBHOOK_FEATURE', true),
```

Master switch. When `false`, dispatching becomes a **no-op** and the admin route is not
registered. Useful to disable webhooks per-environment without removing wiring.

## Queue

```php
'queue' => env('CONFIG_WEBHOOK_QUEUE', 'webhooks'),
```

The queue name delivery jobs (`SendWebhookEvent`) are pushed onto. Make sure a worker processes
this queue in production: `php artisan queue:work --queue=webhooks`. Set your queue *connection*
to `sync` if you want inline delivery (e.g. in small apps or tests).

## User model

```php
'user_model' => env('CONFIG_WEBHOOK_USER_MODEL', 'App\\Models\\User'),
```

The authenticatable model for the optional `webhooks.user_id` ownership relation. There is **no**
DB-level foreign key on `user_id` (the host's users table is unknown) — it is a plain nullable
indexed column. Set to `null` to disable the relation.

## Table names

```php
'table' => [
    'webhooks' => 'webhooks',
    'delivery_logs' => 'webhook_delivery_logs',
],
```

Models override `getTable()` to read these, and the migrations honour them. Change before first
migrating, or migrate the rename yourself.

## Delivery defaults

```php
'defaults' => [
    'max_retries' => 5,
    'timeout' => 30,
],
```

Defaults applied to new webhooks and the bounds the admin UI validates against. A webhook's own
`max_retries` / `timeout` override these per subscriber.

## Retry backoff

```php
'backoff' => [
    'base' => 10,
    'multiplier' => 3,
],
```

Exponential backoff for failed deliveries. The delay before attempt **N** is
`base * (multiplier ^ (N - 1))` seconds. With these defaults: 10s, 30s, 90s, 270s, 810s, …

## Signature

```php
'signature' => [
    'algo' => 'sha256',
    'header' => 'X-Webhook-Signature',
    'event_header' => 'X-Webhook-Event',
    'delivery_header' => 'X-Webhook-Delivery',
],
```

Outgoing payloads are signed with an HMAC of the raw JSON body using the webhook's secret.
`algo` is any algorithm supported by PHP's `hash_hmac`. The three header names carry the
signature, the event type, and the delivery log UUID respectively.

## HTTP

```php
'user_agent' => env('CONFIG_WEBHOOK_USER_AGENT', 'Laravel-Config-Webhook'),

'response_body_limit' => 5000,
```

`user_agent` is sent on every delivery. `response_body_limit` truncates the stored response body
to this many characters in the delivery log (`0` = unlimited).

## Events catalogue

```php
'events' => [
    'order.created'   => 'Order Created',
    'order.cancelled' => 'Order Cancelled',
    'user.registered' => 'User Registered',
],
```

The `type => label` map of subscribable events (the label appears in the admin UI). A flat list
is also accepted. Hosts may instead register at runtime with `ConfigWebhook::registerEvent(s)`
and map domain events with `ConfigWebhook::listen()`.

## Authorization gate

```php
'gate' => null,
```

Controls access to the admin UI:

- `null` — no check (open, or handled by your route middleware)
- ability string — checked via `Gate::allows($ability)`
- callable — `fn ($user) => bool`

## Admin route

```php
'route' => [
    'enabled' => false,
    'prefix' => 'admin/webhooks',
    'name' => 'config-webhook.index',
    'middleware' => ['web', 'auth'],
],
```

When `enabled` (and `feature` is on, and Livewire is installed), registers a full-page route
rendering the Livewire component. The bundled view also needs `livewire/flux`.

## UI

```php
'ui' => [
    'layout' => null,
    'per_page' => 15,
],
```

`layout` is the host layout the admin UI renders into. Leave `null` to use the package's bundled
minimal fallback layout (`config-webhook::layouts.app`); set it to your own app layout (e.g.
`'components.layouts.app'`) in production. `per_page` controls pagination on the webhooks table.

> **Keep `layout` null by default.** The component renders with
> `->layout(config('config-webhook.ui.layout') ?: 'config-webhook::layouts.app')`, so a non-null
> default would prevent the bundled fallback from ever being used.

## Next Steps

- [Usage](../02-development/02-usage.md) — these keys in action
- [API: Payload & Headers](../04-api/03-payload-and-headers.md) — the `signature` keys on the wire
