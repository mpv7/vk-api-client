<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Http;

use Mpv7\VkApi\Exception\NetworkException;

/**
 * Transport abstraction. Implement it to plug in Guzzle, Symfony HttpClient,
 * a PSR-18 adapter, or a test double.
 */
interface HttpClientInterface
{
    /**
     * Sends an application/x-www-form-urlencoded POST request.
     *
     * Parameters travel in the request body, never in the URL, so the access
     * token does not end up in proxy or server logs.
     *
     * @param array<string, string|int|float> $params
     *
     * @throws NetworkException on any transport-level failure
     */
    public function post(string $url, array $params, float $timeoutSeconds): HttpResponse;
}
