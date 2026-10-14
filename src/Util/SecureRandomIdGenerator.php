<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Util;

/**
 * Cryptographically secure random_id in the range VK accepts (positive int32).
 */
final class SecureRandomIdGenerator implements RandomIdGeneratorInterface
{
    public function generate(): int
    {
        return random_int(1, 2_147_483_647);
    }
}
