<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Exception;

use Throwable;

/**
 * VK answered with a non-2xx HTTP status.
 */
final class HttpException extends VkException
{
    private const TRANSIENT_STATUSES = [420, 429, 500, 502, 503, 504];

    public function __construct(
        private readonly int $statusCode,
        private readonly ?float $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Unexpected HTTP status %d from VK API.', $statusCode),
            $statusCode,
            $previous
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isTransient(): bool
    {
        return in_array($this->statusCode, self::TRANSIENT_STATUSES, true);
    }

    public function getRetryAfter(): ?float
    {
        return $this->retryAfter;
    }
}
