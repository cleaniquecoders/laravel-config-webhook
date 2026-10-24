<?php

namespace CleaniqueCoders\ConfigWebhook\Support;

use CleaniqueCoders\ConfigWebhook\Exceptions\UnsafeWebhookUrl;

/**
 * Webhook URLs are operator input, and the delivery log stores the response body — so an
 * unguarded URL is a server-side request forgery that reads back the answer (cloud metadata at
 * 169.254.169.254, an internal admin port, Redis on localhost). Only http(s) to public addresses
 * is allowed unless `config-webhook.allow_private_hosts` is true (local development).
 */
class WebhookUrlGuard
{
    /**
     * Validate the URL and return the IPs it resolves to, so the caller can pin the connection
     * to them (CURLOPT_RESOLVE) and a DNS answer cannot change between this check and the send.
     *
     * @return list<string>
     *
     * @throws UnsafeWebhookUrl
     */
    public static function check(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = trim((string) ($parts['host'] ?? ''), '[]');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new UnsafeWebhookUrl('Webhook URL must be an http(s) URL with a host.');
        }

        if (config('config-webhook.allow_private_hosts', false)) {
            return [];
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new UnsafeWebhookUrl("Webhook host {$host} resolves to a private or reserved address ({$ip}).");
            }
        }

        return $ips;
    }

    /**
     * @return list<string>
     */
    protected static function resolve(string $host): array
    {
        $host = strtolower(rtrim($host, '.'));

        // RFC 6761: localhost always means loopback, whatever DNS says.
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return ['127.0.0.1'];
        }

        // RFC 2606 names never resolve — skip a lookup that can only time out.
        foreach (['.test', '.example', '.invalid'] as $tld) {
            if (str_ends_with($host, $tld)) {
                return [];
            }
        }

        $ips = gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        // Unresolvable: nothing to forge a request to; the HTTP call fails on its own.
        return array_values(array_unique($ips));
    }
}
