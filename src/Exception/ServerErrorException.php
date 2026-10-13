<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Exception;

/**
 * Error 10: internal server error on the VK side. Usually temporary.
 */
final class ServerErrorException extends ApiException
{
    public function isTransient(): bool
    {
        return true;
    }
}
