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

## Release Workflow

1. Commit (do **not** hand-edit `CHANGELOG.md` — auto-generated by the changelog workflow).
2. Tag with **no `v` prefix** (e.g. `1.0.0`), push the tag, create a GitHub release.

## Self-Update Practice

This file is a living document. When a correction, preference, better pattern, or gotcha is
discovered during work here, update the relevant section immediately.
