# Payload & Headers

The exact request a subscriber receives on every delivery.

## Request

The package sends a `POST` to the webhook's `url` with a JSON body and signed headers.

```http
POST https://subscriber.example.com/hook HTTP/1.1
Content-Type: application/json
User-Agent: Laravel-Config-Webhook
X-Webhook-Signature: 9f86d081884c7d659a2feaa0c55ad015a3bf4f1b...
X-Webhook-Event: order.created
X-Webhook-Delivery: 4115d073-6256-4ab5-8ad2-c56c6337ccc3
```

## Body

A stable envelope wrapping your `data`:

```json
{
    "event": "order.created",
    "timestamp": "2026-06-09T05:24:18+00:00",
    "data": {
        "id": 123,
        "total": 49.90
    }
}
```

| Field | Description |
|---|---|
| `event` | The event type the webhook is subscribed to |
| `timestamp` | ISO-8601 time the payload was built |
| `data` | Your payload — the array passed to `send()` or returned by a `listen()` resolver |

## Headers

| Header | Source (config key) | Description |
|---|---|---|
| `X-Webhook-Signature` | `signature.header` | HMAC of the raw JSON body (see [Signatures](02-signatures.md)) |
| `X-Webhook-Event` | `signature.event_header` | The event type |
| `X-Webhook-Delivery` | `signature.delivery_header` | The delivery log UUID — use it to de-duplicate / correlate |
| `User-Agent` | `user_agent` | Defaults to `Laravel-Config-Webhook` |

Header names and the signing algorithm are configurable — see
[Configuration: Signature](../03-configuration/01-reference.md#signature). Any custom `headers`
set on the `Webhook` are merged in as well.

## A success response

Return any `2xx` status to mark the delivery successful. Anything else (or a transport error)
triggers a retry with backoff until `max_retries`. The subscriber should respond quickly; the
delivery uses the webhook's `timeout` (seconds).

## What gets logged

Each attempt records the response in a `WebhookDeliveryLog`: `response_status`, `response_body`
(truncated to `response_body_limit`), `response_time_ms`, `attempt`, and the terminal `status`.
See [Delivery Lifecycle](../01-architecture/03-delivery-lifecycle.md).

## Next Steps

- [Signatures](02-signatures.md) — verify the `X-Webhook-Signature`
- [Configuration: Signature](../03-configuration/01-reference.md#signature) — rename headers,
  change the algorithm
