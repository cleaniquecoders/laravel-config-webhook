# Workbench (end-to-end demo)

The package ships an [Orchestra Testbench](https://github.com/orchestral/testbench) **Workbench**
— a real Laravel app, pre-seeded so the whole pipeline runs end-to-end with no manual setup.

## What is included

- `testbench.yaml` — registers `WorkbenchServiceProvider`, seeds the demo data, and sets the
  app env (fixed `APP_KEY`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`).
- `workbench/app/Providers/WorkbenchServiceProvider.php` — shows the host-side wiring: it
  registers an event catalogue and maps `OrderShipped` → `order.shipped`.
- `workbench/database/seeders/DatabaseSeeder.php` — seeds one active **"Demo Receiver"** webhook
  subscribed to `order.shipped`, pointing at the in-app `/receiver` route.
- `workbench/routes/web.php` — the demo routes (below).

## Boot it

```bash
composer install
vendor/bin/testbench migrate:fresh --seed                    # tables + the seeded Demo Receiver
vendor/bin/testbench serve --port=8000                       # terminal 1 — http://127.0.0.1:8000
vendor/bin/testbench queue:work --queue=webhooks --tries=1   # terminal 2 — delivery is async
```

> A queue worker is required because the demo uses the `database` queue connection. The receiver
> must run out-of-band from `/fire`: the built-in `serve` is effectively single-process, so a
> *sync* self-POST would deadlock. In your own app, set the queue connection to `sync` for inline
> delivery.

## The demo routes

| Route | What it does |
|---|---|
| `GET /webhooks` | The bundled Livewire admin UI (needs `livewire/flux` to render) |
| `GET /fire` | Dispatches the sample `OrderShipped` domain event |
| `POST /receiver` | A local subscriber endpoint that **verifies the HMAC signature** against the demo secret and records the payload |
| `GET /received` | Shows what the receiver captured — proof of the round-trip |

## Exercise it

1. Open `GET /webhooks` — the seeded **Demo Receiver** webhook is listed (subscribed to
   `order.shipped`, active).
2. Hit `GET /fire` — this dispatches `OrderShipped`; the listener maps it to `order.shipped` and
   queues a delivery.
3. The worker delivers a signed POST to `/receiver`.
4. Open `GET /received` — you will see the received payload with `"verified": true` (the HMAC
   signature checked out).
5. Back in `/webhooks`, open the webhook's **Logs** — the delivery shows `status=success,
   http=200`.

## How the receiver verifies the signature

`/receiver` is the mirror image of a real subscriber. It excludes CSRF (an inbound
machine-to-machine POST is not a browser form) and recomputes the HMAC:

```php
$verified = ConfigWebhook::verifySignature(
    $request->getContent(),
    (string) $request->header(config('config-webhook.signature.header')),
    WorkbenchServiceProvider::DEMO_SECRET,
);
```

> **CSRF note:** the CSRF middleware class in this Laravel version is
> `Illuminate\Foundation\Http\Middleware\PreventRequestForgery`. A `web` route that receives an
> external POST must `->withoutMiddleware([PreventRequestForgery::class])` or it returns 419.

## Seeder namespace gotcha

`composer.json` `autoload-dev` maps `Workbench\Database\Seeders\` and
`Workbench\Database\Factories\` (not just `Workbench\App\`). Raw `migrate:fresh --seed` looks for
the default `DatabaseSeeder`; seed the workbench class explicitly when needed:

```bash
vendor/bin/testbench db:seed --class='Workbench\Database\Seeders\DatabaseSeeder'
```

## Next Steps

- [Usage](02-usage.md) — the same APIs, in your own app
- [Architecture: Delivery Lifecycle](../01-architecture/03-delivery-lifecycle.md)
- [API: Payload & Headers](../04-api/03-payload-and-headers.md)
