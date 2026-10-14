<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Retry;

final class UsleepSleeper implements SleeperInterface
{
    public function sleep(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int) round($seconds * 1_000_000));
        }
    }
}
