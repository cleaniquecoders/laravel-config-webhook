<?php

namespace Workbench\Database\Seeders;

use CleaniqueCoders\ConfigWebhook\Models\Webhook;
use Illuminate\Database\Seeder;
use Workbench\App\Providers\WorkbenchServiceProvider;

/**
 * Seeds one ready-to-fire demo webhook so the workbench works end-to-end out of
 * the box: visit /fire to dispatch OrderShipped and a delivery log appears with
 * no manual setup. The webhook points at the in-workbench /receiver route.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Webhook::query()->updateOrCreate(
            ['name' => 'Demo Receiver'],
            [
                'url' => WorkbenchServiceProvider::DEMO_RECEIVER_URL,
                'secret' => WorkbenchServiceProvider::DEMO_SECRET,
                'events' => ['order.shipped'],
                'is_active' => true,
                'max_retries' => 3,
                'timeout' => 10,
            ],
        );
    }
}
