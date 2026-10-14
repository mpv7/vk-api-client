<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Tests\Support;

use Mpv7\VkApi\Util\RandomIdGeneratorInterface;

/**
 * Predictable random_id source: 1000, 1001, 1002, ...
 */
final class SequenceRandomIdGenerator implements RandomIdGeneratorInterface
{
    private int $next = 1000;

    public int $calls = 0;

    public function generate(): int
    {
        ++$this->calls;

        return $this->next++;
    }
}
