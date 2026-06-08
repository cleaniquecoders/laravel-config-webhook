<?php

use CleaniqueCoders\ConfigWebhook\ConfigWebhook;
use CleaniqueCoders\ConfigWebhook\Enums\DeliveryStatus;
use CleaniqueCoders\ConfigWebhook\Models\Webhook;
use CleaniqueCoders\ConfigWebhook\Models\WebhookDeliveryLog;
use CleaniqueCoders\ConfigWebhook\Support\WebhookSignature;
use Illuminate\Support\Facades\Http;
use Workbench\App\Events\OrderShipped;

/**
 * Full consumer journey, exactly as another Laravel app would use the package:
 * map a domain event -> create a webhook subscriber -> fire the event ->
 * the queued job runs (sync) -> a signed HTTP request is delivered ->
 * the delivery is logged.
 */
it('delivers a mapped domain event end-to-end and logs success', function () {
    config()->set('queue.default', 'sync');
    Http::fake(['hooks.test/*' => Http::response('received', 200)]);

    // 1. The host app wires a domain event to a webhook type (e.g. in a provider).
    app(ConfigWebhook::class)->listen(
        OrderShipped::class,
        'order.shipped',
        fn (OrderShipped $event) => [
            'order_id' => $event->orderId,
            'tracking' => $event->tracking,
        ],
    );

    // 2. A subscriber registers a webhook for that event type.
    $webhook = Webhook::create([
        'name' => 'Fulfilment Service',
        'url' => 'https://hooks.test/order-shipped',
        'secret' => 'shared-secret',
        'events' => ['order.shipped'],
        'is_active' => true,
        'max_retries' => 3,
        'timeout' => 10,
    ]);

    // 3. The domain event fires somewhere in the application.
    OrderShipped::dispatch(99, 'TRK-99');

    // 4. A signed request was delivered with the resolved payload.
    Http::assertSent(function ($request) {
        $payload = json_decode($request->body(), true);

        return $request->url() === 'https://hooks.test/order-shipped'
            && $request->method() === 'POST'
            && $payload['event'] === 'order.shipped'
            && $payload['data']['order_id'] === 99
            && $payload['data']['tracking'] === 'TRK-99'
            && $request->hasHeader('X-Webhook-Event', 'order.shipped')
            && $request->hasHeader('X-Webhook-Signature', WebhookSignature::generate($request->body(), 'shared-secret'));
    });

    // 5. The delivery was logged as a success.
    $log = WebhookDeliveryLog::where('webhook_id', $webhook->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(DeliveryStatus::SUCCESS)
        ->and($log->event_type)->toBe('order.shipped')
        ->and($log->response_status)->toBe(200)
        ->and($webhook->fresh()->last_triggered_at)->not->toBeNull();
});

it('does not deliver to webhooks that did not subscribe to the event', function () {
    config()->set('queue.default', 'sync');
    Http::fake();

    app(ConfigWebhook::class)->listen(
        OrderShipped::class,
        'order.shipped',
        fn (OrderShipped $event) => ['order_id' => $event->orderId],
    );

    Webhook::factory()->listeningTo(['user.registered'])->create([
        'url' => 'https://hooks.test/other',
    ]);

    OrderShipped::dispatch(1);

    Http::assertNothingSent();
    expect(WebhookDeliveryLog::count())->toBe(0);
});

it('retries through to a failed log when the endpoint keeps erroring', function () {
    config()->set('queue.default', 'sync');
    config()->set('config-webhook.backoff', ['base' => 0, 'multiplier' => 1]); // no real delay
    Http::fake(['hooks.test/*' => Http::response('boom', 500)]);

    $webhook = Webhook::create([
        'name' => 'Flaky Service',
        'url' => 'https://hooks.test/flaky',
        'secret' => 'secret',
        'events' => ['order.shipped'],
        'is_active' => true,
        'max_retries' => 3,
        'timeout' => 10,
    ]);

    app(ConfigWebhook::class)->send('order.shipped', ['order_id' => 7]);

    $log = WebhookDeliveryLog::where('webhook_id', $webhook->id)->first();

    expect($log->status)->toBe(DeliveryStatus::FAILED)
        ->and($log->attempt)->toBe(3);
    Http::assertSentCount(3);
});
