<?php

namespace CleaniqueCoders\ConfigWebhook;

use CleaniqueCoders\ConfigWebhook\Commands\PruneWebhookDeliveryLogsCommand;
use CleaniqueCoders\ConfigWebhook\Livewire\Webhooks;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ConfigWebhookServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-config-webhook')
            ->hasConfigFile('config-webhook')
            ->hasViews('config-webhook')
            ->hasMigrations([
                'create_webhooks_table',
                'create_webhook_delivery_logs_table',
            ])
            ->hasCommand(PruneWebhookDeliveryLogsCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ConfigWebhook::class, function (): ConfigWebhook {
            return (new ConfigWebhook)->registerEvents(
                config('config-webhook.events', [])
            );
        });
    }

    public function packageBooted(): void
    {
        $this->registerLivewireComponent();
        $this->registerRoute();
    }

    protected function registerLivewireComponent(): void
    {
        if (class_exists(Livewire::class)) {
            // NOTE: the component name must not contain "::". Under Livewire 4 a
            // "::" turns the name into a *namespace* lookup (Finder::resolveClassComponentClassName)
            // which only resolves components registered via componentNamespace(), not
            // single components registered here — so a "::" name throws
            // ComponentNotFoundException at mount. A dotted name round-trips correctly.
            Livewire::component('config-webhook.webhooks', Webhooks::class);
        }
    }

    protected function registerRoute(): void
    {
        /** @var array<string, mixed> $config */
        $config = config('config-webhook.route', []);

        if (! ($config['enabled'] ?? false) || ! config('config-webhook.feature', true)) {
            return;
        }

        if (! class_exists(Livewire::class)) {
            return;
        }

        Route::middleware($config['middleware'] ?? ['web'])
            ->prefix($config['prefix'] ?? 'admin/webhooks')
            ->group(function () use ($config): void {
                Route::get('/', Webhooks::class)->name($config['name'] ?? 'config-webhook.index');
            });
    }
}
