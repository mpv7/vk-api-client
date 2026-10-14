<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Retry;

use InvalidArgumentException;

/**
 * Exponential back-off: baseDelay, 2 * baseDelay, 4 * baseDelay, ...
 * capped at maxDelay. A server-provided Retry-After value takes precedence
 * over the computed delay but is capped as well.
 */
final class RetryPolicy
{
    /**
     * @param int   $maxAttempts      total number of attempts including the first one (1 = no retries)
     * @param float $baseDelaySeconds delay after the first failed attempt
     * @param float $maxDelaySeconds  upper bound for any single delay
     */
    public function __construct(
        public readonly int $maxAttempts = 3,
        public readonly float $baseDelaySeconds = 0.5,
        public readonly float $maxDelaySeconds = 8.0,
    ) {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be at least 1.');
        }

        if ($baseDelaySeconds < 0 || $maxDelaySeconds < 0) {
            throw new InvalidArgumentException('Delays must not be negative.');
        }
    }

    /**
     * Policy that never retries.
     */
    public static function none(): self
    {
        return new self(maxAttempts: 1);
    }

    /**
     * Delay to wait after the given failed attempt (1-based).
     */
    public function delayFor(int $attempt, ?float $retryAfterSeconds = null): float
    {
        $delay = $retryAfterSeconds ?? $this->baseDelaySeconds * (2 ** max(0, $attempt - 1));

        return min(max($delay, 0.0), $this->maxDelaySeconds);
    }
}
