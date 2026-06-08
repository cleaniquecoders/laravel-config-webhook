<?php

use CleaniqueCoders\ConfigWebhook\Models\Webhook;

it('auto-generates a uuid on create', function () {
    $webhook = Webhook::factory()->create();

    expect($webhook->uuid)->not->toBeEmpty()
        ->and($webhook->getRouteKeyName())->toBe('uuid');
});

it('encrypts the secret at rest but decrypts transparently', function () {
    $webhook = Webhook::factory()->create(['secret' => 'super-secret-value']);

    expect($webhook->fresh()->secret)->toBe('super-secret-value');

    $raw = DB::table($webhook->getTable())->where('id', $webhook->id)->value('secret');

    expect($raw)->not->toBe('super-secret-value');
});

it('scopes active webhooks', function () {
    Webhook::factory()->create();
    Webhook::factory()->inactive()->create();

    expect(Webhook::active()->count())->toBe(1);
});

it('scopes webhooks listening for an event', function () {
    Webhook::factory()->listeningTo(['order.created'])->create();
    Webhook::factory()->listeningTo(['user.registered'])->create();

    expect(Webhook::forEvent('order.created')->count())->toBe(1);
});

it('reports whether it listens to an event', function () {
    $webhook = Webhook::factory()->listeningTo(['order.created', 'order.cancelled'])->create();

    expect($webhook->listensTo('order.created'))->toBeTrue()
        ->and($webhook->listensTo('user.registered'))->toBeFalse();
});
