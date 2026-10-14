<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Http;

use LogicException;
use Mpv7\VkApi\Exception\NetworkException;

/**
 * Default transport built on ext-curl.
 *
 * TLS verification is always on and redirects are never followed.
 */
final class CurlHttpClient implements HttpClientInterface
{
    public function __construct()
    {
        if (!extension_loaded('curl')) {
            throw new LogicException(
                'The curl extension is required by CurlHttpClient. '
                . 'Install it or inject another HttpClientInterface implementation.'
            );
        }
    }

    public function post(string $url, array $params, float $timeoutSeconds): HttpResponse
    {
        $handle = curl_init();

        if ($handle === false) {
            throw new NetworkException('Failed to initialise cURL.');
        }

        $headers = [];
        $timeoutMs = max(1, (int) ceil($timeoutSeconds * 1000));

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                $parts = explode(':', $line, 2);

                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);

        $body = curl_exec($handle);

        if ($body === false || !is_string($body)) {
            throw new NetworkException(
                sprintf('HTTP request failed: %s (cURL error %d).', curl_error($handle), curl_errno($handle))
            );
        }

        return new HttpResponse((int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $body, $headers);
    }
}
