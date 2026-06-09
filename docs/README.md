# Documentation

Complete documentation for **laravel-config-webhook** — define, sign, dispatch and log
outgoing webhooks in any Laravel application, with retries, exponential backoff, HMAC
signatures, and an optional Livewire + Flux admin UI.

For a quick overview and installation, see the [project README](../README.md).

## Documentation Structure

### [01. Architecture](01-architecture/README.md)

How the package is put together: the manager, models, the queued delivery job, and the
signed-delivery lifecycle with retries and backoff.

### [02. Development](02-development/README.md)

Install it, register your events, dispatch payloads, verify signatures, run the test suite,
and boot the bundled Testbench workbench end-to-end.

### [03. Configuration](03-configuration/README.md)

Every key in `config/config-webhook.php` — the feature toggle, queue, tables, retry/backoff,
signature headers, authorization gate, admin route, and UI.

### [04. API Reference](04-api/README.md)

The `ConfigWebhook` manager methods, the `WebhookSignature` helper, and the exact payload
and HTTP headers a subscriber receives.

### [05. Releasing](05-releasing/README.md)

Versioning, tagging, and publishing the package to Packagist.

## Quick Start

New here? Start with [Getting Started](02-development/01-getting-started.md), then read the
[Architecture Overview](01-architecture/01-overview.md).

## Finding Information

- **How it works / concepts** — [Architecture](01-architecture/README.md)
- **How do I use it?** — [Development](02-development/README.md)
- **What does this config key do?** — [Configuration](03-configuration/README.md)
- **What methods / payload shape?** — [API Reference](04-api/README.md)
