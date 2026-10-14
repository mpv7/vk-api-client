<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Util;

/**
 * Source of random_id values for messages.send.
 */
interface RandomIdGeneratorInterface
{
    public function generate(): int;
}
