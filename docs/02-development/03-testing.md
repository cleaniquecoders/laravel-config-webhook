# Testing

The package uses [Pest](https://pestphp.com) over an SQLite `:memory:` database via Orchestra
Testbench.

## Run the suite

```bash
composer test          # pest
composer analyse       # phpstan (larastan) level 5
composer format        # laravel pint
```

## The canonical end-to-end test

`tests/EndToEndTest.php` is the "does it actually work for a consumer" test. It runs the full
pipeline on the **sync** queue so everything happens inline:

```php
config(['queue.default' => 'sync']);
```

It maps a domain event with `ConfigWebhook::listen()`, creates a subscriber webhook, fires the
event, lets the queued `SendWebhookEvent` run, fakes the HTTP layer with `Http::fake()`, and
asserts both the outgoing signed request and the recorded delivery log. Keep it green — it is
the contract the package promises to consumers.

## Testing gotchas

These bite hard if you do not know them:

### APP_KEY is required

The `secret` column uses the `encrypted` cast. Testbench 11 (Laravel 13) ships no default
`APP_KEY`, so `tests/TestCase.php` sets one in `getEnvironmentSetUp()`. Without it, creating a
webhook throws a decryption error.

### `Event::fake()` with no arguments breaks UUIDs

Faking *all* events suppresses model events, which stops `HasUuid`'s `creating` hook and causes
`NOT NULL` violations on `uuid`. In job tests, fake **specific** events only:

```php
Event::fake([WebhookDelivered::class, WebhookFailed::class]);
```

### One try per job

`SendWebhookEvent::$tries = 1`. Each failed attempt marks the log `RETRYING` and re-dispatches
itself with backoff until `max_retries`. Do not set `$tries > 1` in tests — attempts get
double-counted.

### `whereJsonContains` needs JSON1

`Webhook::forEvent()` uses `whereJsonContains('events', ...)`, which requires SQLite's JSON1
extension (bundled in modern PHP) — fine on PostgreSQL/MySQL.

## Static analysis

Larastan runs at level 5 over `src`, `config`, and `database`. The baseline holds only
unavoidable environmental items (an `env()` call in the package config file). Real code issues
must be **fixed**, not baselined — and a baseline entry that no longer matches should be removed.

## Next Steps

- [Workbench](04-workbench.md) — exercise the pipeline against a real HTTP receiver
- [Architecture: Delivery Lifecycle](../01-architecture/03-delivery-lifecycle.md) — what the
  job does per attempt
