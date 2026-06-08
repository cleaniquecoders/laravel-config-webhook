# Changelog

All notable changes to `laravel-config-webhook` will be documented in this file.

## 1.0.0 - 2026-06-08

First stable release.

Outgoing webhooks for Laravel — extracted and generalised from the g8stack webhook integrations feature.

### Features

- Config/runtime event registry (no hardcoded domain events)
- HMAC-SHA256 signed delivery with retries + exponential backoff
- Delivery logging (status, HTTP code, response time, attempts, errors)
- Manual dispatch (`ConfigWebhook::send`) + domain-event mapping (`ConfigWebhook::listen`)
- Optional Livewire + Flux admin UI (registered only when Livewire is present)
- Configurable gate, user model, queue, table names; secret encrypted at rest
- Testbench Workbench setup + end-to-end test suite (32 tests)

### Requirements

- PHP 8.3+
- Laravel 12 or 13

### Install

```bash
composer require cleaniquecoders/laravel-config-webhook
php artisan vendor:publish --tag="laravel-config-webhook-migrations"
php artisan migrate

```
**Next step to enable `composer require` everywhere:** submit the repo to Packagist (one-time) at https://packagist.org/packages/submit — afterwards the GitHub auto-update webhook keeps it in sync.
