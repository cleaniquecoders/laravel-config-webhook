# Configuration

Reference for `config/config-webhook.php` — every key, its default, and what it controls.

## Overview

Publish the config with `php artisan vendor:publish --tag="laravel-config-webhook-config"`.
Most behaviour described across the rest of the docs is tuned here: the master feature toggle,
the delivery queue, table names, retry/backoff, signature headers, the authorization gate, and
the optional admin route and UI.

## Table of Contents

### [1. Reference](01-reference.md)

Every configuration key, grouped by concern, with defaults and notes.

## Related Documentation

- [Architecture: Delivery Lifecycle](../01-architecture/03-delivery-lifecycle.md) — how
  `queue`, `defaults`, and `backoff` drive retries
- [API: Payload & Headers](../04-api/03-payload-and-headers.md) — how `signature` shapes the
  outgoing request
- [Usage](../02-development/02-usage.md) — the `gate`, `route`, and `ui` keys in practice
