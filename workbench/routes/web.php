<?php

use CleaniqueCoders\ConfigWebhook\Livewire\Webhooks;
use Illuminate\Support\Facades\Route;
use Workbench\App\Events\OrderShipped;

// The bundled admin UI (requires livewire/flux to render).
Route::get('/webhooks', Webhooks::class)->name('webhooks');

// Fire the sample domain event to exercise the full delivery pipeline.
Route::get('/fire', function () {
    OrderShipped::dispatch(random_int(1, 9999));

    return 'OrderShipped dispatched — check your webhook delivery logs.';
});
