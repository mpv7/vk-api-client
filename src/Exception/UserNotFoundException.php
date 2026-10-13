<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Exception;

/**
 * users.get returned an empty list for the requested id.
 */
final class UserNotFoundException extends VkException
{
    public function __construct(private readonly int $userId)
    {
        parent::__construct(sprintf('User %d was not found.', $userId));
    }

    public function getUserId(): int
    {
        return $this->userId;
    }
}
