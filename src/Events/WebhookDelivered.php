<?php

namespace CleaniqueCoders\ConfigWebhook\Events;

use CleaniqueCoders\ConfigWebhook\Models\WebhookDeliveryLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebhookDelivered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public WebhookDeliveryLog $deliveryLog,
    ) {}
}
