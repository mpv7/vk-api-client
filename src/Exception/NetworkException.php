<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Exception;

/**
 * Transport-level failure: DNS, connection, TLS, timeout.
 */
final class NetworkException extends VkException
{
    public function isTransient(): bool
    {
        return true;
    }
}
