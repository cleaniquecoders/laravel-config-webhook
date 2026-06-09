# Getting Started

Install the package, set up the database, and register the events your app can emit.

## Requirements

- PHP **8.3+**
- Laravel **12** or **13**
- (Optional, for the admin UI) `livewire/livewire` **^3 || ^4** and `livewire/flux`

## Install

```bash
composer require cleaniquecoders/laravel-config-webhook
```

The service provider and the `ConfigWebhook` facade are auto-discovered — no manual
registration needed.

## Publish and migrate

Publish the migrations and run them:

```bash
php artisan vendor:publish --tag="laravel-config-webhook-migrations"
php artisan migrate
```

This creates the `webhooks` and `webhook_delivery_logs` tables (names are configurable — see
[Configuration](../03-configuration/01-reference.md)).

Publish the config to customise behaviour:

```bash
php artisan vendor:publish --tag="laravel-config-webhook-config"
```

Optionally publish the views to customise the Flux UI:

```bash
php artisan vendor:publish --tag="laravel-config-webhook-views"
```

## A valid `APP_KEY` is required

The `Webhook::secret` column uses Laravel's `encrypted` cast. Make sure your app has a valid
`APP_KEY` (`php artisan key:generate`) — creating or reading a webhook without one throws a
decryption error.

## Register your event catalogue

The package ships **no** events of its own. Register the types your app can emit, either in
`config/config-webhook.php`:

```php
'events' => [
    'order.created'   => 'Order Created',
    'order.cancelled' => 'Order Cancelled',
    'user.registered' => 'User Registered',
],
```

…or at runtime in a service provider's `boot()`:

```php
use CleaniqueCoders\ConfigWebhook\Facades\ConfigWebhook;

ConfigWebhook::registerEvents([
    'order.created' => 'Order Created',
    'user.registered' => 'User Registered',
]);
```

A flat list (`['order.created', 'user.registered']`) also works; labels default to the type.
Registered types power the admin UI's event picker and validate webhook subscriptions.

## Verify the install

Create a webhook (via the admin UI or a factory/seeder) subscribed to one of your types, then
dispatch a payload:

```php
ConfigWebhook::send('order.created', ['id' => 1]);
```

A queued `SendWebhookEvent` job delivers a signed POST and writes a `WebhookDeliveryLog`.

## Next Steps

- [Usage](02-usage.md) — manual dispatch, domain-event mapping, signature verification
- [Configuration](../03-configuration/01-reference.md) — tune queues, retries, headers, gate
- [Workbench](04-workbench.md) — see the whole pipeline run end-to-end
