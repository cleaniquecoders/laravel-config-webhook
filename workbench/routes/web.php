<?php

use CleaniqueCoders\ConfigWebhook\Facades\ConfigWebhook;
use CleaniqueCoders\ConfigWebhook\Livewire\Webhooks;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Workbench\App\Events\OrderShipped;
use Workbench\App\Providers\WorkbenchServiceProvider;

// The bundled admin UI (requires livewire/flux to render).
Route::get('/webhooks', Webhooks::class)->name('webhooks');

// Fire the sample domain event to exercise the full delivery pipeline.
// With a queue worker running, this returns immediately and the seeded
// "Demo Receiver" webhook is delivered to /receiver in the background.
Route::get('/fire', function () {
    $orderId = random_int(1, 9999);

    OrderShipped::dispatch($orderId);

    return response()->json([
        'dispatched' => 'order.shipped',
        'order_id' => $orderId,
        'next' => 'See the delivery at /received, or the logs in the UI at /webhooks.',
    ]);
});

// In-workbench webhook receiver. A real subscriber endpoint would live in a
// different app; here it verifies the HMAC signature against the demo secret
// and records the payload so the round-trip is observable. No CSRF (this is an
// inbound machine-to-machine POST, not a browser form).
Route::post('/receiver', function (Request $request) {
    $raw = $request->getContent();
    $signatureHeader = config('config-webhook.signature.header', 'X-Webhook-Signature');

    $verified = ConfigWebhook::verifySignature(
        $raw,
        (string) $request->header($signatureHeader, ''),
        WorkbenchServiceProvider::DEMO_SECRET,
    );

    $received = Cache::get('workbench.received', []);
    array_unshift($received, [
        'event' => $request->header(config('config-webhook.signature.event_header', 'X-Webhook-Event')),
        'delivery' => $request->header(config('config-webhook.signature.delivery_header', 'X-Webhook-Delivery')),
        'verified' => $verified,
        'payload' => $request->json()->all(),
    ]);
    Cache::put('workbench.received', array_slice($received, 0, 20));

    return response()->json(['ok' => true, 'verified' => $verified]);
})->withoutMiddleware([PreventRequestForgery::class]);

// View what the receiver has captured — proof of the end-to-end delivery.
Route::get('/received', fn () => response()->json(Cache::get('workbench.received', [])));
