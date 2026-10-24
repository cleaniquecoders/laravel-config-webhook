<?php

use CleaniqueCoders\ConfigWebhook\Livewire\Webhooks;
use CleaniqueCoders\ConfigWebhook\Models\Webhook;
use Livewire\Livewire;

beforeEach(fn () => config(['config-webhook.events' => ['order.created' => 'Order Created']]));

it('rejects a webhook URL pointing at a private address when saving', function () {
    Livewire::test(Webhooks::class)
        ->set('name', 'Internal')
        ->set('url', 'http://169.254.169.254/latest/meta-data/')
        ->set('events', ['order.created'])
        ->set('secret', str_repeat('s', 16))
        ->call('save')
        ->assertHasErrors('url');

    expect(Webhook::count())->toBe(0);
});
