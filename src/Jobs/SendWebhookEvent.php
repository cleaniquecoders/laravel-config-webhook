<?php

namespace CleaniqueCoders\ConfigWebhook\Jobs;

use CleaniqueCoders\ConfigWebhook\Enums\DeliveryStatus;
use CleaniqueCoders\ConfigWebhook\Events\WebhookDelivered;
use CleaniqueCoders\ConfigWebhook\Events\WebhookFailed;
use CleaniqueCoders\ConfigWebhook\Models\Webhook;
use CleaniqueCoders\ConfigWebhook\Models\WebhookDeliveryLog;
use CleaniqueCoders\ConfigWebhook\Support\WebhookSignature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendWebhookEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * One try per job — retries are scheduled manually with backoff so each
     * attempt gets its own delivery-log accounting.
     */
    public int $tries = 1;

    public int $maxExceptions = 1;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Webhook $webhook,
        public string $eventType,
        public array $payload,
        public ?int $deliveryLogId = null,
        public int $attempt = 1,
    ) {
        $this->onQueue(config('config-webhook.queue', 'webhooks'));
    }

    public function handle(): void
    {
        $deliveryLog = $this->getOrCreateDeliveryLog();

        $jsonPayload = (string) json_encode($this->payload);
        $signature = WebhookSignature::generate($jsonPayload, $this->webhook->secret);

        $headers = array_merge(
            $this->webhook->headers ?? [],
            [
                'Content-Type' => 'application/json',
                config('config-webhook.signature.header', 'X-Webhook-Signature') => $signature,
                config('config-webhook.signature.event_header', 'X-Webhook-Event') => $this->eventType,
                config('config-webhook.signature.delivery_header', 'X-Webhook-Delivery') => $deliveryLog->uuid,
                'User-Agent' => (string) config('config-webhook.user_agent', 'Laravel-Config-Webhook'),
            ]
        );

        $startTime = microtime(true);

        try {
            $response = Http::withHeaders($headers)
                ->timeout($this->webhook->requestTimeout())
                ->post($this->webhook->url, $this->payload);

            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $deliveryLog->markSuccess($response->status(), $response->body(), $responseTimeMs);
                $this->webhook->forceFill(['last_triggered_at' => now()])->save();

                WebhookDelivered::dispatch($deliveryLog->refresh());

                return;
            }

            $this->handleFailure(
                $deliveryLog,
                "HTTP {$response->status()}",
                $response->status(),
                $response->body(),
                $responseTimeMs
            );
        } catch (Throwable $e) {
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->handleFailure($deliveryLog, $e->getMessage(), null, null, $responseTimeMs);
        }
    }

    protected function getOrCreateDeliveryLog(): WebhookDeliveryLog
    {
        if ($this->deliveryLogId) {
            return WebhookDeliveryLog::findOrFail($this->deliveryLogId);
        }

        return WebhookDeliveryLog::create([
            'webhook_id' => $this->webhook->id,
            'event_type' => $this->eventType,
            'payload' => $this->payload,
            'attempt' => $this->attempt,
            'status' => DeliveryStatus::PENDING,
        ]);
    }

    protected function handleFailure(
        WebhookDeliveryLog $deliveryLog,
        string $error,
        ?int $statusCode,
        ?string $body,
        int $responseTimeMs
    ): void {
        $deliveryLog->markFailed($this->attempt, $error, $statusCode, $body, $responseTimeMs);

        $willRetry = $deliveryLog->isRetryable();

        WebhookFailed::dispatch($deliveryLog->refresh(), $error, $willRetry);

        if ($willRetry) {
            $backoffSeconds = $deliveryLog->calculateBackoff($this->attempt);

            self::dispatch(
                $this->webhook,
                $this->eventType,
                $this->payload,
                $deliveryLog->id,
                $this->attempt + 1
            )->delay(now()->addSeconds($backoffSeconds));
        }
    }
}
