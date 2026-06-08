<?php

namespace CleaniqueCoders\ConfigWebhook\Database\Factories;

use CleaniqueCoders\ConfigWebhook\Enums\DeliveryStatus;
use CleaniqueCoders\ConfigWebhook\Models\Webhook;
use CleaniqueCoders\ConfigWebhook\Models\WebhookDeliveryLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDeliveryLog>
 */
class WebhookDeliveryLogFactory extends Factory
{
    protected $model = WebhookDeliveryLog::class;

    public function definition(): array
    {
        return [
            'webhook_id' => Webhook::factory(),
            'event_type' => 'order.created',
            'payload' => ['event' => 'order.created', 'data' => []],
            'attempt' => 1,
            'status' => DeliveryStatus::PENDING,
        ];
    }

    public function success(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryStatus::SUCCESS,
            'response_status' => 200,
            'response_time_ms' => 120,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryStatus::FAILED,
            'response_status' => 500,
            'error_message' => 'HTTP 500',
            'completed_at' => now(),
        ]);
    }
}
