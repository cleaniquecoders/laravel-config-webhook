<?php

use CleaniqueCoders\ConfigWebhook\ConfigWebhook;
use CleaniqueCoders\ConfigWebhook\Enums\DeliveryStatus;
use CleaniqueCoders\ConfigWebhook\Events\WebhookDelivered;
use CleaniqueCoders\ConfigWebhook\Events\WebhookFailed;
use CleaniqueCoders\ConfigWebhook\Jobs\SendWebhookEvent;
use CleaniqueCoders\ConfigWebhook\Models\Webhook;
use CleaniqueCoders\ConfigWebhook\Models\WebhookDeliveryLog;
use CleaniqueCoders\ConfigWebhook\Support\WebhookSignature;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

it('delivers the payload, logs success, and signs the request', function () {
    Event::fake([WebhookDelivered::class, WebhookFailed::class]);
    Http::fake(['*' => Http::response('ok', 200)]);

    $webhook = Webhook::factory()->create(['secret' => 'my-secret', 'url' => 'https://example.test/hook']);
    $payload = ['event' => 'order.created', 'data' => ['id' => 1]];

    (new SendWebhookEvent($webhook, 'order.created', $payload))->handle();

    $log = WebhookDeliveryLog::first();
    expect($log->status)->toBe(DeliveryStatus::SUCCESS)
        ->and($log->response_status)->toBe(200)
        ->and($webhook->fresh()->last_triggered_at)->not->toBeNull();

    Http::assertSent(function ($request) use ($payload) {
        $expected = WebhookSignature::generate((string) json_encode($payload), 'my-secret');

        return $request->hasHeader('X-Webhook-Signature', $expected)
            && $request->hasHeader('X-Webhook-Event', 'order.created');
    });

    Event::assertDispatched(WebhookDelivered::class);
});

it('logs a failure and schedules a retry on a 500', function () {
    Bus::fake();
    Event::fake([WebhookDelivered::class, WebhookFailed::class]);
    Http::fake(['*' => Http::response('boom', 500)]);

    $webhook = Webhook::factory()->create(['max_retries' => 3]);

    (new SendWebhookEvent($webhook, 'order.created', ['event' => 'order.created']))->handle();

    $log = WebhookDeliveryLog::first();
    expect($log->status)->toBe(DeliveryStatus::RETRYING)
        ->and($log->response_status)->toBe(500);

    Bus::assertDispatched(SendWebhookEvent::class, fn (SendWebhookEvent $job) => $job->attempt === 2);
    Event::assertDispatched(WebhookFailed::class, fn (WebhookFailed $e) => $e->willRetry === true);
});

it('stops retrying after max retries', function () {
    Bus::fake();
    Event::fake([WebhookDelivered::class, WebhookFailed::class]);
    Http::fake(['*' => Http::response('boom', 500)]);

    $webhook = Webhook::factory()->create(['max_retries' => 2]);
    $log = WebhookDeliveryLog::factory()->for($webhook)->create(['attempt' => 2]);

    (new SendWebhookEvent($webhook, 'order.created', ['event' => 'order.created'], $log->id, 2))->handle();

    expect($log->fresh()->status)->toBe(DeliveryStatus::FAILED);
    Bus::assertNotDispatched(SendWebhookEvent::class);
});

it('pushes delivery jobs onto the configured queue', function () {
    Bus::fake();
    config()->set('config-webhook.queue', 'custom-webhooks');

    Webhook::factory()->listeningTo(['order.created'])->create();

    app(ConfigWebhook::class)
        ->registerEvent('order.created')
        ->send('order.created', ['id' => 1]);

    Bus::assertDispatched(SendWebhookEvent::class, fn (SendWebhookEvent $job) => $job->queue === 'custom-webhooks');
});
