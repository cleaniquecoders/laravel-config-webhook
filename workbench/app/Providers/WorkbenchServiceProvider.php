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
    /**
     * Shared secret for the seeded demo webhook. The local /receiver route uses
     * the same value to verify the X-Webhook-Signature, proving the HMAC round-trip
     * end-to-end. (Demo only — real subscribers each generate their own secret.)
     */
    public const DEMO_SECRET = 'demo-secret-please-change-0123456789';

    /**
     * URL of the in-workbench receiver the demo webhook delivers to.
     */
    public const DEMO_RECEIVER_URL = 'http://127.0.0.1:8000/receiver';

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
