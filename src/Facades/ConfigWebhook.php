<?php

namespace CleaniqueCoders\ConfigWebhook\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \CleaniqueCoders\ConfigWebhook\ConfigWebhook registerEvent(string $type, ?string $label = null)
 * @method static \CleaniqueCoders\ConfigWebhook\ConfigWebhook registerEvents(array $events)
 * @method static \CleaniqueCoders\ConfigWebhook\ConfigWebhook listen(string $domainEvent, string $type, callable $payloadResolver)
 * @method static array availableEvents()
 * @method static array eventTypes()
 * @method static array groupedEvents()
 * @method static int send(string $type, array $data = [])
 * @method static int dispatchFromEvent(object $event)
 * @method static string generateSignature(string $payload, string $secret)
 * @method static bool verifySignature(string $payload, string $signature, string $secret)
 *
 * @see \CleaniqueCoders\ConfigWebhook\ConfigWebhook
 */
class ConfigWebhook extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \CleaniqueCoders\ConfigWebhook\ConfigWebhook::class;
    }
}
