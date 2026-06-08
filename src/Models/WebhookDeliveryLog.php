<?php

namespace CleaniqueCoders\ConfigWebhook\Models;

use CleaniqueCoders\ConfigWebhook\Concerns\HasUuid;
use CleaniqueCoders\ConfigWebhook\Database\Factories\WebhookDeliveryLogFactory;
use CleaniqueCoders\ConfigWebhook\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $webhook_id
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property int|null $response_status
 * @property string|null $response_body
 * @property int|null $response_time_ms
 * @property int $attempt
 * @property DeliveryStatus $status
 * @property string|null $error_message
 * @property Carbon|null $next_retry_at
 * @property Carbon|null $completed_at
 * @property-read Webhook|null $webhook
 */
class WebhookDeliveryLog extends Model
{
    use HasFactory;
    use HasUuid;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('config-webhook.table.delivery_logs', 'webhook_delivery_logs');
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'meta' => 'array',
            'attempt' => 'integer',
            'response_status' => 'integer',
            'response_time_ms' => 'integer',
            'next_retry_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => DeliveryStatus::class,
        ];
    }

    protected static function newFactory(): WebhookDeliveryLogFactory
    {
        return WebhookDeliveryLogFactory::new();
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DeliveryStatus::PENDING->value);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', DeliveryStatus::FAILED->value);
    }

    public function scopeRetrying(Builder $query): Builder
    {
        return $query->where('status', DeliveryStatus::RETRYING->value);
    }

    public function isRetryable(): bool
    {
        return $this->status === DeliveryStatus::RETRYING
            && $this->attempt < ($this->webhook?->maxRetries() ?? (int) config('config-webhook.defaults.max_retries', 5));
    }

    public function markSuccess(int $statusCode, ?string $body, int $responseTimeMs): void
    {
        $this->update([
            'status' => DeliveryStatus::SUCCESS,
            'response_status' => $statusCode,
            'response_body' => $this->truncateBody($body),
            'response_time_ms' => $responseTimeMs,
            'completed_at' => now(),
            'next_retry_at' => null,
        ]);
    }

    public function markFailed(int $attempt, ?string $error, ?int $statusCode = null, ?string $body = null, ?int $responseTimeMs = null): void
    {
        $maxRetries = $this->webhook?->maxRetries() ?? (int) config('config-webhook.defaults.max_retries', 5);
        $isLastAttempt = $attempt >= $maxRetries;

        $this->update([
            'status' => $isLastAttempt ? DeliveryStatus::FAILED : DeliveryStatus::RETRYING,
            'attempt' => $attempt,
            'error_message' => $error,
            'response_status' => $statusCode,
            'response_body' => $this->truncateBody($body),
            'response_time_ms' => $responseTimeMs,
            'next_retry_at' => $isLastAttempt ? null : now()->addSeconds($this->calculateBackoff($attempt)),
            'completed_at' => $isLastAttempt ? now() : null,
        ]);
    }

    public function calculateBackoff(int $attempt): int
    {
        $base = (int) config('config-webhook.backoff.base', 10);
        $multiplier = (int) config('config-webhook.backoff.multiplier', 3);

        return (int) ($base * ($multiplier ** max(0, $attempt - 1)));
    }

    protected function truncateBody(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        $limit = (int) config('config-webhook.response_body_limit', 5000);

        return $limit > 0 ? mb_substr($body, 0, $limit) : $body;
    }
}
