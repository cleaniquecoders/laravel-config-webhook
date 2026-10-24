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

it('sends exactly the bytes it signed', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $webhook = Webhook::factory()->create(['secret' => 'my-secret', 'url' => 'https://example.test/hook']);
    $payload = ['event' => 'order.created', 'data' => ['name' => 'Café / "quoted"', 'amount' => 1.5]];

    (new SendWebhookEvent($webhook, 'order.created', $payload))->handle();

    Http::assertSent(fn ($request) => WebhookSignature::verify($request->body(), $request->header('X-Webhook-Signature')[0], 'my-secret'));
});

it('refuses to deliver to private, loopback and metadata addresses without retrying', function (string $url) {
    Bus::fake();
    Event::fake([WebhookFailed::class]);
    Http::fake();

    $webhook = Webhook::factory()->create(['url' => $url, 'max_retries' => 3]);

    (new SendWebhookEvent($webhook, 'order.created', ['event' => 'order.created']))->handle();

    Http::assertNothingSent();
    expect(WebhookDeliveryLog::first()->status)->toBe(DeliveryStatus::FAILED);
    Bus::assertNotDispatched(SendWebhookEvent::class);
    Event::assertDispatched(WebhookFailed::class, fn (WebhookFailed $e) => $e->willRetry === false);
})->with([
    'metadata' => 'http://169.254.169.254/latest/meta-data/',
    'loopback' => 'http://127.0.0.1:6379/',
    'private' => 'https://10.0.0.5/hook',
    'ipv6 loopback' => 'http://[::1]/hook',
    'non-http' => 'file:///etc/passwd',
]);

it('allows private hosts when explicitly enabled for local development', function () {
    config(['config-webhook.allow_private_hosts' => true]);
    Http::fake(['*' => Http::response('ok', 200)]);

    $webhook = Webhook::factory()->create(['url' => 'http://127.0.0.1:8000/hook']);

    (new SendWebhookEvent($webhook, 'order.created', ['event' => 'order.created']))->handle();

    expect(WebhookDeliveryLog::first()->status)->toBe(DeliveryStatus::SUCCESS);
});

it('treats localhost names as loopback', function () {
    Http::fake();
    $webhook = Webhook::factory()->create(['url' => 'http://api.localhost/hook']);

    (new SendWebhookEvent($webhook, 'order.created', ['event' => 'order.created']))->handle();

    Http::assertNothingSent();
});
