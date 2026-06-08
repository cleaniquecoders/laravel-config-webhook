<?php

namespace CleaniqueCoders\ConfigWebhook\Models;

use CleaniqueCoders\ConfigWebhook\Concerns\HasUuid;
use CleaniqueCoders\ConfigWebhook\Database\Factories\WebhookFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $url
 * @property string $secret
 * @property array<int, string> $events
 * @property array<string, string>|null $headers
 * @property bool $is_active
 * @property int $max_retries
 * @property int $timeout
 * @property Carbon|null $last_triggered_at
 */
class Webhook extends Model
{
    use HasFactory;
    use HasUuid;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('config-webhook.table.webhooks', 'webhooks');
    }

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'headers' => 'array',
            'meta' => 'array',
            'is_active' => 'boolean',
            'max_retries' => 'integer',
            'timeout' => 'integer',
            'last_triggered_at' => 'datetime',
            'secret' => 'encrypted',
        ];
    }

    protected static function newFactory(): WebhookFactory
    {
        return WebhookFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('config-webhook.user_model', 'App\\Models\\User'));
    }

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(WebhookDeliveryLog::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent(Builder $query, string $eventType): Builder
    {
        return $query->whereJsonContains('events', $eventType);
    }

    public function listensTo(string $eventType): bool
    {
        return in_array($eventType, $this->events ?? [], true);
    }

    public function maxRetries(): int
    {
        return $this->max_retries ?? (int) config('config-webhook.defaults.max_retries', 5);
    }

    public function requestTimeout(): int
    {
        return $this->timeout ?? (int) config('config-webhook.defaults.timeout', 30);
    }
}
