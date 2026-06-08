<?php

// config for CleaniqueCoders/ConfigWebhook
return [

    /*
    |--------------------------------------------------------------------------
    | Feature Toggle
    |--------------------------------------------------------------------------
    |
    | Master switch for the package. When disabled, dispatching webhooks
    | becomes a no-op and the (optional) admin route is not registered.
    |
    */
    'feature' => env('CONFIG_WEBHOOK_FEATURE', true),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | The queue name that webhook delivery jobs are pushed onto. Make sure a
    | worker is processing this queue in production.
    |
    */
    'queue' => env('CONFIG_WEBHOOK_QUEUE', 'webhooks'),

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The application's authenticatable model. Used for the optional
    | `webhooks.user_id` ownership relation. Set to null to disable the
    | relation entirely.
    |
    */
    'user_model' => env('CONFIG_WEBHOOK_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */
    'table' => [
        'webhooks' => 'webhooks',
        'delivery_logs' => 'webhook_delivery_logs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery Defaults
    |--------------------------------------------------------------------------
    |
    | Default values applied to new webhooks and the bounds enforced by the
    | admin UI validation.
    |
    */
    'defaults' => [
        'max_retries' => 5,
        'timeout' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Backoff
    |--------------------------------------------------------------------------
    |
    | Exponential backoff for failed deliveries. The delay before attempt N is
    | `base * (multiplier ^ (N - 1))` seconds. With base=10, multiplier=3 that
    | yields 10s, 30s, 90s, 270s, 810s ...
    |
    */
    'backoff' => [
        'base' => 10,
        'multiplier' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Signature
    |--------------------------------------------------------------------------
    |
    | Outgoing payloads are signed with an HMAC of the JSON body using the
    | webhook's secret. Receivers verify the signature header to authenticate
    | the request.
    |
    */
    'signature' => [
        'algo' => 'sha256',
        'header' => 'X-Webhook-Signature',
        'event_header' => 'X-Webhook-Event',
        'delivery_header' => 'X-Webhook-Delivery',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */
    'user_agent' => env('CONFIG_WEBHOOK_USER_AGENT', 'Laravel-Config-Webhook'),

    // Truncate stored response bodies to this many characters (0 = unlimited).
    'response_body_limit' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | The catalogue of event types a webhook may subscribe to, as a
    | `type => label` map (the label is shown in the admin UI). Hosts may also
    | register events at runtime via ConfigWebhook::registerEvent() and map
    | domain events via ConfigWebhook::listen(). Example:
    |
    |   'events' => [
    |       'order.created'   => 'Order Created',
    |       'order.cancelled' => 'Order Cancelled',
    |       'user.registered' => 'User Registered',
    |   ],
    |
    */
    'events' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Gate
    |--------------------------------------------------------------------------
    |
    | Controls access to the admin UI. May be:
    |   - null              : no authorization check (open / handled elsewhere)
    |   - an ability string : checked via Gate::allows($ability)
    |   - a callable        : fn ($user) => bool
    |
    */
    'gate' => null,

    /*
    |--------------------------------------------------------------------------
    | Admin Route
    |--------------------------------------------------------------------------
    |
    | When enabled, registers a full-page route that renders the Livewire
    | management component. Requires livewire/livewire (and livewire/flux for
    | the bundled view).
    |
    */
    'route' => [
        'enabled' => false,
        'prefix' => 'admin/webhooks',
        'name' => 'config-webhook.index',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'layout' => 'components.layouts.app',
        'per_page' => 15,
    ],

];
