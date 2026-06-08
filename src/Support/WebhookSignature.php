<?php

namespace CleaniqueCoders\ConfigWebhook\Support;

class WebhookSignature
{
    /**
     * Generate an HMAC signature for a raw payload string.
     */
    public static function generate(string $payload, string $secret, ?string $algo = null): string
    {
        return hash_hmac($algo ?? self::algo(), $payload, $secret);
    }

    /**
     * Verify a signature in constant time.
     */
    public static function verify(string $payload, string $signature, string $secret, ?string $algo = null): bool
    {
        $expected = self::generate($payload, $secret, $algo);

        return hash_equals($expected, $signature);
    }

    protected static function algo(): string
    {
        return (string) config('config-webhook.signature.algo', 'sha256');
    }
}
