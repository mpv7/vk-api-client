<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Tests\Support;

use Mpv7\VkApi\Retry\SleeperInterface;

/**
 * Does not sleep, only remembers the requested delays.
 */
final class RecordingSleeper implements SleeperInterface
{
    /** @var list<float> */
    public array $delays = [];

    public function sleep(float $seconds): void
    {
        $this->delays[] = $seconds;
    }
}
