<?php

namespace CleaniqueCoders\ConfigWebhook;

use CleaniqueCoders\ConfigWebhook\Jobs\SendWebhookEvent;
use CleaniqueCoders\ConfigWebhook\Models\Webhook;
use CleaniqueCoders\ConfigWebhook\Support\WebhookSignature;
use Illuminate\Support\Facades\Event;

/**
 * The webhook manager — the object resolved by the ConfigWebhook facade.
 *
 * Responsibilities:
 *  - hold the catalogue of subscribable event types (for UI + validation)
 *  - fan a payload out to every matching active webhook (`send`)
 *  - optionally map host domain events to webhook event types (`listen`)
 */
class ConfigWebhook
{
    /**
     * type => label
     *
     * @var array<string, string>
     */
    protected array $events = [];

    /**
     * domain event class => ['type' => string, 'resolver' => callable]
     *
     * @var array<class-string, array{type: string, resolver: callable}>
     */
    protected array $listeners = [];

    public function registerEvent(string $type, ?string $label = null): static
    {
        $this->events[$type] = $label ?? $this->humanize($type);

        return $this;
    }

    /**
     * Accepts either a flat list of type strings, or a `type => label` map.
     *
     * @param  array<int|string, string>  $events
     */
    public function registerEvents(array $events): static
    {
        foreach ($events as $key => $value) {
            is_int($key)
                ? $this->registerEvent($value)
                : $this->registerEvent($key, $value);
        }

        return $this;
    }

    /**
     * Map a host domain event to a webhook event type. When the domain event
     * fires, the resolver builds the payload `data` and webhooks are dispatched.
     *
     * @param  class-string  $domainEvent
     * @param  callable(object): array<string, mixed>  $payloadResolver
     */
    public function listen(string $domainEvent, string $type, callable $payloadResolver): static
    {
        $this->registerEvent($type);

        $this->listeners[$domainEvent] = ['type' => $type, 'resolver' => $payloadResolver];

        Event::listen($domainEvent, fn (object $event) => $this->dispatchFromEvent($event));

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function availableEvents(): array
    {
        return $this->events;
    }

    /**
     * @return array<int, string>
     */
    public function eventTypes(): array
    {
        return array_keys($this->events);
    }

    /**
     * Group event types by their prefix (the part before the first dot).
     *
     * @return array<string, array<string, string>>
     */
    public function groupedEvents(): array
    {
        $groups = [];

        foreach ($this->events as $type => $label) {
            $group = str_contains($type, '.') ? explode('.', $type, 2)[0] : 'general';
            $groups[$group][$type] = $label;
        }

        return $groups;
    }

    /**
     * Fan a payload out to every active webhook subscribed to the event type.
     *
     * @param  array<string, mixed>  $data
     * @return int number of webhooks dispatched to
     */
    public function send(string $type, array $data = []): int
    {
        if (! config('config-webhook.feature', true)) {
            return 0;
        }

        $payload = [
            'event' => $type,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ];

        $count = 0;

        Webhook::query()
            ->active()
            ->forEvent($type)
            ->get()
            ->each(function (Webhook $webhook) use ($type, $payload, &$count): void {
                SendWebhookEvent::dispatch($webhook, $type, $payload);
                $count++;
            });

        return $count;
    }

    /**
     * Resolve a registered domain event to its type + payload, then dispatch.
     */
    public function dispatchFromEvent(object $event): int
    {
        $mapping = $this->listeners[$event::class] ?? null;

        if ($mapping === null) {
            return 0;
        }

        return $this->send($mapping['type'], (array) ($mapping['resolver'])($event));
    }

    public function generateSignature(string $payload, string $secret): string
    {
        return WebhookSignature::generate($payload, $secret);
    }

    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        return WebhookSignature::verify($payload, $signature, $secret);
    }

    protected function humanize(string $type): string
    {
        return ucwords(str_replace(['.', '_', '-'], ' ', $type));
    }
}
