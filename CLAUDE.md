# CLAUDE.md

Guidance for Claude Code (claude.ai/code) when working in this repository.

## Project Overview

**laravel-config-webhook** (`cleaniquecoders/laravel-config-webhook`) is a Laravel package
for **outgoing webhooks**: subscribers register a URL + secret + the event types they care
about, and the package delivers **HMAC-signed** JSON payloads with **retries + exponential
backoff** and full **delivery logging**. Ships an **optional Livewire + Flux** admin UI.

- **Package type**: Laravel package (Composer, built on `spatie/laravel-package-tools`)
- **Namespace**: `CleaniqueCoders\ConfigWebhook`
- **Origin**: extracted and decoupled from the g8stack-app "Webhook Integrations" feature
- **Tracking issue**: #1
- **Supports**: Laravel 12 & 13 (Testbench 10 & 11), PHP 8.4

### The core idea

The package knows **nothing** about your domain. Hosts register an event catalogue (config
or runtime) and then either:

1. **Dispatch manually** — `ConfigWebhook::send('order.created', $data)` fans the payload out
   to every active webhook subscribed to that type.
2. **Map a domain event** — `ConfigWebhook::listen(OrderCreated::class, 'order.created', $resolver)`
   wires a Laravel event listener that auto-dispatches when the event fires.

Each delivery is signed (`X-Webhook-Signature` = HMAC of the JSON body), logged, and retried
on failure with exponential backoff until `max_retries` is exhausted.

## Architecture

| Concern | Class |
|---|---|
| Manager (facade root) | `ConfigWebhook` — `registerEvent(s)`, `listen`, `send`, `dispatchFromEvent`, `availableEvents`, `groupedEvents`, `eventTypes`, `generate/verifySignature` |
| Models | `Models\Webhook`, `Models\WebhookDeliveryLog` |
| UUID trait | `Concerns\HasUuid` (package-owned, not the host's base model) |
| Status | `Enums\DeliveryStatus` — `PENDING`, `SUCCESS`, `FAILED`, `RETRYING` (+ `label()`, `color()`) |
| Delivery | `Jobs\SendWebhookEvent` — queued, one try per job, schedules its own retry with backoff |
| Signing | `Support\WebhookSignature` — `generate()` / `verify()` (constant-time) |
| Events | `Events\WebhookDelivered`, `Events\WebhookFailed` |
| CLI | `Commands\PruneWebhookDeliveryLogsCommand` (`config-webhook:prune --days=30`) |
| UI | `Livewire\Webhooks` + `resources/views/livewire/webhooks.blade.php` (Flux, opt-in) |
| Provider | `ConfigWebhookServiceProvider` |

## Decoupling rules (DO NOT regress)

This package was extracted from an app. Keep it app-agnostic:

1. **No `App\Models\Base`** — models extend `Illuminate\Database\Eloquent\Model` and use the
   package's own `Concerns\HasUuid` trait.
2. **No hardcoded `App\Models\User`** — the `Webhook::user()` relation resolves via
   `config('config-webhook.user_model')`. The `webhooks.user_id` column has **no DB-level FK**
   (the host's users table name/PK is unknown) — it's a plain nullable indexed column.
3. **No `G8Stack\Core` / Kong references** — event types are **config- or runtime-registered**
   by the host; the package has no built-in event map.
4. **No global helpers** — signing lives in `Support\WebhookSignature`.
5. **Authorization** goes through `config('config-webhook.gate')` (null = open, string = ability,
   callable = `fn ($user) => bool`), never a hardcoded gate string.
6. **Flux/Livewire UI is optional** — the Livewire component and route are registered only when
   `livewire/livewire` is installed; the manager + job + signing must work headless.
7. **Table names are config-driven** — models override `getTable()` and migrations read
   `config('config-webhook.table.*')` with literal fallbacks.

## Config surface (`config/config-webhook.php`)

- `feature` — master toggle; when false `send()` is a no-op and the route isn't registered
- `queue` — queue name delivery jobs are pushed onto (default `webhooks`)
- `user_model` — FQCN of the host's user model for the ownership relation
- `table` — `webhooks` + `delivery_logs` table names
- `defaults` — `max_retries`, `timeout` (also the UI validation bounds)
- `backoff` — `base` + `multiplier`; delay before attempt N = `base * multiplier^(N-1)`
- `signature` — `algo` + header names (`X-Webhook-Signature/Event/Delivery`)
- `user_agent`, `response_body_limit`
- `events` — the subscribable catalogue (`type => label` map, or flat list)
- `gate` — authorization for the admin UI
- `route` — enable/prefix/name/middleware for the bundled full-page route
- `ui` — `layout` (host layout; falls back to `config-webhook::layouts.app`) + `per_page`

## Conventions

- **Tests**: Pest. SQLite `:memory:`. `tests/TestCase.php` sets an explicit `app.key` (Testbench
  11 ships none and the `secret` `encrypted` cast needs one) and runs the migration stubs in
  `getEnvironmentSetUp()`. Registers `LivewireServiceProvider` so the UI component resolves.
- **Static analysis**: larastan level 5 over `src`, `config`, `database`. The baseline holds two
  unavoidable environmental items: `env()` in the package config file (larastan can't see the
  package as "the config dir" under Testbench) and the package view-string in the Livewire
  component (views aren't registered during analysis). Real code issues must be **fixed**, not
  baselined.
- **Formatting**: Laravel Pint (`composer format`).
- **Commands**: `composer test`, `composer analyse`, `composer format`.
- **End-to-end test**: `tests/EndToEndTest.php` runs the full pipeline on the **sync** queue
  (`config(['queue.default' => 'sync'])`) — domain event → `listen()` listener → manager →
  `SendWebhookEvent` → `Http::fake()` → delivery log. This is the canonical "does it actually
  work for a consumer" test; keep it green.
- **Workbench (Testbench)**: `testbench.yaml` + `workbench/` provide a runnable demo app.
  `WorkbenchServiceProvider` (registered in `testbench.yaml` `providers`, alongside the package
  provider) shows the host-side wiring — event catalogue + `listen(OrderShipped → order.shipped)`.
  `workbench/routes/web.php` exposes `/webhooks` (UI), `/fire` (dispatch `OrderShipped`),
  `/receiver` (a local subscriber endpoint that verifies the HMAC signature against the demo
  secret) and `/received` (what the receiver captured). `DatabaseSeeder` seeds one active
  "Demo Receiver" webhook subscribed to `order.shipped` so the demo works out of the box.
  `testbench.yaml` sets a fixed `APP_KEY` (the `secret` encrypted cast needs it, and it must
  match between seed-time and serve-time), `QUEUE_CONNECTION=database`, `CACHE_STORE=database`.
  `testbench.yaml` is **committed**; the generated `workbench/database/*.sqlite` is ignored.

  **Run the end-to-end demo:**
  ```
  vendor/bin/testbench migrate:fresh --seed   # (or workbench:build) — seed the Demo Receiver
  vendor/bin/testbench serve --port=8000       # terminal 1
  vendor/bin/testbench queue:work --queue=webhooks --tries=1   # terminal 2 (delivery is async)
  ```
  Then GET `/fire` → the worker delivers to `/receiver` → check `/received` (`verified: true`)
  and the delivery log (`status=success, http=200`) in the UI at `/webhooks`. A queue worker is
  required because `QUEUE_CONNECTION=database`; the receiver must run out-of-band from `/fire`
  (the built-in `serve` is effectively single-process, so a *sync* self-POST would deadlock).

  > **Workbench seeder needs PSR-4 + namespace gotchas.** `composer.json` `autoload-dev` must map
  > `Workbench\Database\Seeders\` and `Workbench\Database\Factories\` (not just `Workbench\App\`).
  > Raw `vendor/bin/testbench migrate:fresh --seed` looks for `DatabaseSeeder` (default namespace)
  > — seed the workbench class via `db:seed --class='Workbench\Database\Seeders\DatabaseSeeder'`
  > or let `workbench:build` map it.

  > **The CSRF middleware class is `Illuminate\Foundation\Http\Middleware\PreventRequestForgery`**
  > (not `ValidateCsrfToken`/`VerifyCsrfToken`) in this Laravel version. An inbound webhook
  > receiver in a `web` route must `->withoutMiddleware([PreventRequestForgery::class])` or it 419s.

## Gotchas

> **APP_KEY required in tests.** The `Webhook::secret` column uses the `encrypted` cast.
> Testbench 11 (Laravel 13) has no default `APP_KEY` → set one in `TestCase::getEnvironmentSetUp()`.

> **`Event::fake()` with no args suppresses model events.** That breaks `HasUuid`'s `creating`
> hook → NOT NULL violations on `uuid`. In job tests always fake specific events
> (`Event::fake([WebhookDelivered::class, WebhookFailed::class])`).

> **One try per job, manual retries.** `SendWebhookEvent::$tries = 1`. Each failed attempt marks
> the delivery log `RETRYING` and re-dispatches itself `->delay(backoff)` with the next attempt
> number, until `max_retries` → `FAILED`. Don't set `$tries > 1` or you'll double-count.

> **`whereJsonContains` for event matching.** `Webhook::forEvent()` uses `whereJsonContains('events', ...)`
> — needs SQLite JSON1 (bundled in modern PHP) for tests; fine on PostgreSQL/MySQL.

> **Livewire layout is dynamic, not a `#[Layout]` attribute.** `render()` calls
> `->layout(config('config-webhook.ui.layout') ?: 'config-webhook::layouts.app')` so hosts can
> point it at their own app layout. The package ships a minimal fallback layout.

> **Don't add a DB foreign key on `webhooks.user_id`.** The host's users table is unknown; keep
> it a nullable indexed column (decoupling rule #2).

> **Livewire 4: the component name must NOT contain `::`.** A `::` makes Livewire 4's `Finder`
> treat the name as a *namespace* lookup (only resolves things registered via
> `componentNamespace()`), so a single component registered with `Livewire::component('x::y', …)`
> throws `ComponentNotFoundException` at mount. Register the bundled UI as
> `Livewire::component('config-webhook.webhooks', Webhooks::class)` (dot, not `::`). The route
> points at the class directly, so the name just has to round-trip via the Finder.

> **`config-webhook.ui.layout` default is `null`, on purpose.** `render()` uses
> `config(...) ?: 'config-webhook::layouts.app'`, so a non-null default would *prevent* the
> bundled fallback layout from ever being used. A leftover `'components.layouts.app'` default
> 500s on any host without that view. Keep the default `null`; hosts set their own layout.

> **Bundled fallback layout must be self-contained.** `config-webhook::layouts.app` cannot
> `@vite` the host's `resources/css/app.css` (those files don't exist in fallback context →
> `ViteManifestNotFoundException`). It loads Tailwind via the Play CDN (demo/first-run only) plus
> `@fluxAppearance`/`@fluxScripts`. This mirrors the sibling packages' workbench layouts, which
> also fall back to the Tailwind CDN when no built CSS is present.

> **Bundled Blade only uses free (Heroicon) Flux icons.** `webhook`, `ellipsis`, and `list` are
> Lucide names (Flux Pro). The UI uses the Heroicon equivalents `bolt`, `ellipsis-horizontal`,
> and `queue-list` so it renders on free `livewire/flux`. `livewire/flux` is a `require-dev`
> dependency (for the workbench/UI render) and stays a `suggest` for consumers.

## Release Workflow

1. Commit (do **not** hand-edit `CHANGELOG.md` — auto-generated by the changelog workflow).
2. Tag with **no `v` prefix** (e.g. `1.0.0`), push the tag, create a GitHub release.

## Self-Update Practice

This file is a living document. When a correction, preference, better pattern, or gotcha is
discovered during work here, update the relevant section immediately.
