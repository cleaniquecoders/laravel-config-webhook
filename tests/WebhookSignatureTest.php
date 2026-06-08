<?php

use CleaniqueCoders\ConfigWebhook\Support\WebhookSignature;

it('generates a deterministic hmac signature', function () {
    $a = WebhookSignature::generate('{"a":1}', 'secret');
    $b = WebhookSignature::generate('{"a":1}', 'secret');

    expect($a)->toBe($b)->and($a)->toHaveLength(64); // sha256 hex
});

it('verifies a valid signature', function () {
    $payload = '{"event":"order.created"}';
    $signature = WebhookSignature::generate($payload, 'secret');

    expect(WebhookSignature::verify($payload, $signature, 'secret'))->toBeTrue();
});

it('rejects a tampered payload or wrong secret', function () {
    $payload = '{"event":"order.created"}';
    $signature = WebhookSignature::generate($payload, 'secret');

    expect(WebhookSignature::verify('{"event":"order.deleted"}', $signature, 'secret'))->toBeFalse()
        ->and(WebhookSignature::verify($payload, $signature, 'wrong'))->toBeFalse();
});
