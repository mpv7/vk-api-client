<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Tests\Unit;

use InvalidArgumentException;
use Mpv7\VkApi\Retry\RetryPolicy;
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase
{
    public function testDelayDoublesWithEveryAttempt(): void
    {
        $policy = new RetryPolicy(maxAttempts: 6, baseDelaySeconds: 1.0, maxDelaySeconds: 100.0);

        self::assertSame(1.0, $policy->delayFor(1));
        self::assertSame(2.0, $policy->delayFor(2));
        self::assertSame(4.0, $policy->delayFor(3));
        self::assertSame(8.0, $policy->delayFor(4));
    }

    public function testDelayIsCapped(): void
    {
        $policy = new RetryPolicy(maxAttempts: 10, baseDelaySeconds: 1.0, maxDelaySeconds: 4.0);

        self::assertSame(4.0, $policy->delayFor(3));
        self::assertSame(4.0, $policy->delayFor(9));
    }

    public function testServerSuggestedDelayWinsButIsCapped(): void
    {
        $policy = new RetryPolicy(maxAttempts: 3, baseDelaySeconds: 1.0, maxDelaySeconds: 5.0);

        self::assertSame(2.0, $policy->delayFor(1, 2.0));
        self::assertSame(5.0, $policy->delayFor(1, 60.0));
        self::assertSame(0.0, $policy->delayFor(1, -3.0));
    }

    public function testNoneDisablesRetries(): void
    {
        self::assertSame(1, RetryPolicy::none()->maxAttempts);
    }

    public function testDefaultsAreSane(): void
    {
        $policy = new RetryPolicy();

        self::assertSame(3, $policy->maxAttempts);
        self::assertSame(0.5, $policy->baseDelaySeconds);
        self::assertSame(8.0, $policy->maxDelaySeconds);
    }

    public function testRejectsZeroAttempts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RetryPolicy(maxAttempts: 0);
    }

    public function testRejectsNegativeDelays(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RetryPolicy(baseDelaySeconds: -1.0);
    }
}
