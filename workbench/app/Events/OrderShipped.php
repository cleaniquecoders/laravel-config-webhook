<?php

namespace Workbench\App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A sample host domain event used by the workbench + end-to-end tests to
 * demonstrate mapping an app event to a webhook type.
 */
class OrderShipped
{
    use Dispatchable;

    public function __construct(
        public int $orderId,
        public string $tracking = 'TRK-0001',
    ) {}
}
