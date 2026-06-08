<?php

use CleaniqueCoders\ConfigWebhook\Enums\DeliveryStatus;
use CleaniqueCoders\ConfigWebhook\Models\Webhook;
use CleaniqueCoders\ConfigWebhook\Models\WebhookDeliveryLog;

it('marks a delivery successful', function () {
    $log = WebhookDeliveryLog::factory()->create();

    $log->markSuccess(200, 'ok', 120);

    expect($log->status)->toBe(DeliveryStatus::SUCCESS)
        ->and($log->response_status)->toBe(200)
        ->and($log->completed_at)->not->toBeNull()
        ->and($log->next_retry_at)->toBeNull();
});

it('marks a delivery retrying when attempts remain', function () {
    $webhook = Webhook::factory()->create(['max_retries' => 5]);
    $log = WebhookDeliveryLog::factory()->for($webhook)->create();

    $log->markFailed(1, 'HTTP 500', 500);

    expect($log->status)->toBe(DeliveryStatus::RETRYING)
        ->and($log->next_retry_at)->not->toBeNull()
        ->and($log->completed_at)->toBeNull()
        ->and($log->isRetryable())->toBeTrue();
});

it('marks a delivery failed on the final attempt', function () {
    $webhook = Webhook::factory()->create(['max_retries' => 3]);
    $log = WebhookDeliveryLog::factory()->for($webhook)->create();

    $log->markFailed(3, 'HTTP 500', 500);

    expect($log->status)->toBe(DeliveryStatus::FAILED)
        ->and($log->next_retry_at)->toBeNull()
        ->and($log->completed_at)->not->toBeNull()
        ->and($log->isRetryable())->toBeFalse();
});

it('calculates exponential backoff from config', function () {
    config()->set('config-webhook.backoff', ['base' => 10, 'multiplier' => 3]);
    $log = WebhookDeliveryLog::factory()->make();

    expect($log->calculateBackoff(1))->toBe(10)
        ->and($log->calculateBackoff(2))->toBe(30)
        ->and($log->calculateBackoff(3))->toBe(90)
        ->and($log->calculateBackoff(4))->toBe(270);
});

it('truncates the stored response body to the configured limit', function () {
    config()->set('config-webhook.response_body_limit', 10);
    $log = WebhookDeliveryLog::factory()->create();

    $log->markSuccess(200, str_repeat('x', 50), 5);

    expect(mb_strlen((string) $log->response_body))->toBe(10);
});
