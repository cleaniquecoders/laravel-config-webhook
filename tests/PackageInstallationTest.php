<?php

use CleaniqueCoders\ConfigWebhook\ConfigWebhook;
use CleaniqueCoders\ConfigWebhook\Livewire\Webhooks;
use Livewire\Component;

it('binds the manager as a singleton', function () {
    expect(app(ConfigWebhook::class))->toBeInstanceOf(ConfigWebhook::class)
        ->and(app(ConfigWebhook::class))->toBe(app(ConfigWebhook::class));
});

it('merges the package config so a fresh app has working defaults', function () {
    expect(config('config-webhook.feature'))->toBeTrue()
        ->and(config('config-webhook.queue'))->not->toBeNull()
        ->and(config('config-webhook.user_model'))->toBe('App\\Models\\User')
        ->and(config('config-webhook.table.webhooks'))->toBe('webhooks');
});

it('ships a publishable config file that is merged at runtime', function () {
    expect(file_exists(dirname(__DIR__).'/config/config-webhook.php'))->toBeTrue()
        ->and(config('config-webhook'))->toBeArray();
});

it('ships both publishable migration stubs', function () {
    $dir = dirname(__DIR__).'/database/migrations';

    expect(file_exists($dir.'/create_webhooks_table.php.stub'))->toBeTrue()
        ->and(file_exists($dir.'/create_webhook_delivery_logs_table.php.stub'))->toBeTrue();
});

it('ships an installable Livewire UI component', function () {
    expect(is_subclass_of(Webhooks::class, Component::class))->toBeTrue();
});

it('exposes the config-webhook view namespace', function () {
    expect(view()->exists('config-webhook::livewire.webhooks'))->toBeTrue()
        ->and(view()->exists('config-webhook::layouts.app'))->toBeTrue();
});
