<?php

namespace Workbench\App\Providers;

use CleaniqueCoders\ConfigWebhook\Facades\ConfigWebhook;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Events\OrderShipped;

/**
 * Demonstrates how a *consuming* application wires the package:
 *  - registers an event catalogue (shown in the admin UI)
 *  - maps a domain event to a webhook type for auto-dispatch
 *
 * Boot a real app around it with:  vendor/bin/testbench serve
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        ConfigWebhook::registerEvents([
            'order.created' => 'Order Created',
            'order.shipped' => 'Order Shipped',
            'user.registered' => 'User Registered',
        ]);

        ConfigWebhook::listen(
            OrderShipped::class,
            'order.shipped',
            fn (OrderShipped $event) => [
                'order_id' => $event->orderId,
                'tracking' => $event->tracking,
            ],
        );
    }
}
