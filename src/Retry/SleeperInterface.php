<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Retry;

/**
 * Abstraction over sleeping, so tests do not have to wait for real.
 */
interface SleeperInterface
{
    public function sleep(float $seconds): void;
}
