<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use InvalidArgumentException;
use RuntimeException;

class PushEndpoint
{
    public function host(string $endpoint): string
    {
        $url = parse_url($endpoint);
        $host = strtolower($url['host'] ?? '');
        // Only browser push providers; never arbitrary caller-controlled destinations.
        $allowed = $host === 'fcm.googleapis.com'
            || $host === 'updates.push.services.mozilla.com'
            || preg_match('/^[a-z0-9-]+\.push\.apple\.com$/D', $host);
        if (! $allowed || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass'])
            || isset($url['fragment']) || ($url['port'] ?? 443) !== 443
            || empty($url['path']) || preg_match('/[\x00-\x20\x7f\\\\]/', $endpoint)) {
            throw new InvalidArgumentException('Unsupported push service endpoint.');
        }
        return $host;
    }

    public function resolve(string $host): array
    {
        return $this->safeAddresses(dns_get_record($host, DNS_A | DNS_AAAA) ?: []);
    }

    public function safeAddresses(array $records): array
    {
        $addresses = [];
        foreach ($records ?: [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($address !== null) {
                // Fail closed, including mixed public/private DNS answers.
                if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE)) {
                    throw new RuntimeException('Unsafe push service DNS.');
                }
                $addresses[] = $address;
            }
        }
        if ($addresses === []) {
            throw new RuntimeException('Push service DNS unavailable.');
        }
        return $addresses;
    }

    public function clientOptions(string $endpoint): array
    {
        $host = $this->host($endpoint);
        $addresses = $this->resolve($host);
        $address = $addresses[0];
        $address = str_contains($address, ':') ? '['.$address.']' : $address;
        // Pin the validated address, force cURL, forbid redirects/proxies and retain TLS verification.
        return [
            'handler' => new \GuzzleHttp\Handler\CurlHandler,
            'allow_redirects' => false,
            'proxy' => '', 'verify' => true, 'connect_timeout' => 5, 'timeout' => 10,
            'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$address], CURLOPT_PROTOCOLS => CURLPROTO_HTTPS],
        ];
    }
}
