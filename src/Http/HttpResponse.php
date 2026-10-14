<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Http;

/**
 * Minimal immutable HTTP response used between the transport and the client.
 */
final class HttpResponse
{
    /** @var array<string, string> header names are stored in lower case */
    public readonly array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        array $headers = [],
    ) {
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
