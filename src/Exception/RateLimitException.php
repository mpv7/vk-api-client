<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Exception;

use Mpv7\VkApi\Enum\VkErrorCode;

/**
 * Errors 6, 9 and 29.
 *
 * Only error 6 ("too many requests per second") is transient and worth
 * retrying; flood control and method limits last much longer.
 */
final class RateLimitException extends ApiException
{
    public function isTransient(): bool
    {
        return $this->getVkCode() === VkErrorCode::TooManyRequests->value;
    }
}
