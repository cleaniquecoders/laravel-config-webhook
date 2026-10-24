# Changelog

All notable changes to `laravel-config-webhook` will be documented in this file.

## 1.1.0 - 2026-10-24

### Security

- Webhook URLs pointing at private, loopback, link-local (cloud metadata) or reserved addresses are refused at save and at send; redirects are not followed and DNS is pinned to the checked IP.
- The request body is now exactly the signed JSON string (previously re-encoded, so signatures could fail to verify).

### Added

- `allow_private_hosts` config (`CONFIG_WEBHOOK_ALLOW_PRIVATE_HOSTS`, default `false`) for local development.

### Behaviour change

- Existing webhooks targeting internal addresses stop delivering (marked failed, no retry) unless private hosts are allowed.

**Full Changelog**: https://github.com/cleaniquecoders/laravel-config-webhook/compare/1.0.1...1.1.0

## 1.0.1 - 2026-06-09

**Full Changelog**: https://github.com/cleaniquecoders/laravel-config-webhook/compare/1.0.0...1.0.1

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
