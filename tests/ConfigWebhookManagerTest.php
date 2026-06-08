<?php

use CleaniqueCoders\ConfigWebhook\ConfigWebhook;
use CleaniqueCoders\ConfigWebhook\Jobs\SendWebhookEvent;
use CleaniqueCoders\ConfigWebhook\Models\Webhook;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->manager = new ConfigWebhook;
});

it('registers events from a flat list and a labelled map', function () {
    $this->manager->registerEvents(['order.created', 'user.registered' => 'New User']);

    expect($this->manager->eventTypes())->toBe(['order.created', 'user.registered'])
        ->and($this->manager->availableEvents()['order.created'])->toBe('Order Created')
        ->and($this->manager->availableEvents()['user.registered'])->toBe('New User');
});

it('groups events by prefix', function () {
    $this->manager->registerEvents(['order.created', 'order.cancelled', 'user.registered']);

    $groups = $this->manager->groupedEvents();

    expect($groups)->toHaveKeys(['order', 'user'])
        ->and($groups['order'])->toHaveCount(2);
});

it('dispatches a job to every active webhook subscribed to the event', function () {
    Bus::fake();

    Webhook::factory()->listeningTo(['order.created'])->create();
    Webhook::factory()->listeningTo(['order.created'])->create();
    Webhook::factory()->listeningTo(['user.registered'])->create();
    Webhook::factory()->inactive()->listeningTo(['order.created'])->create();

    $count = $this->manager->send('order.created', ['id' => 1]);

    expect($count)->toBe(2);
    Bus::assertDispatchedTimes(SendWebhookEvent::class, 2);
});

it('does not dispatch when the feature is disabled', function () {
    Bus::fake();
    config()->set('config-webhook.feature', false);

    Webhook::factory()->listeningTo(['order.created'])->create();

    expect($this->manager->send('order.created'))->toBe(0);
    Bus::assertNothingDispatched();
});

it('maps a domain event to a webhook type and wires a listener', function () {
    Bus::fake();

    Webhook::factory()->listeningTo(['order.created'])->create();

    $domainEvent = new class
    {
        public int $id = 42;
    };

    $this->manager->listen($domainEvent::class, 'order.created', fn ($e) => ['id' => $e->id]);

    Event::dispatch($domainEvent);

    Bus::assertDispatched(SendWebhookEvent::class, function (SendWebhookEvent $job) {
        return $job->eventType === 'order.created'
            && $job->payload['data']['id'] === 42;
    });
});
