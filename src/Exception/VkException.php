<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Exception;

use RuntimeException;

/**
 * Base class for every exception thrown by the library.
 */
abstract class VkException extends RuntimeException
{
    /**
     * Whether repeating the same request may succeed.
     */
    public function isTransient(): bool
    {
        return false;
    }

    /**
     * Server-suggested delay (seconds) before the next attempt, if any.
     */
    public function getRetryAfter(): ?float
    {
        return null;
    }
}
