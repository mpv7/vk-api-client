<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Tests\Support;

use Mpv7\VkApi\Auth\StaticTokenProvider;
use Mpv7\VkApi\ClientConfig;
use Mpv7\VkApi\Retry\RetryPolicy;
use Mpv7\VkApi\Util\RandomIdGeneratorInterface;
use Mpv7\VkApi\VkClient;
use PHPUnit\Framework\TestCase;

/**
 * Wires a VkClient to the fakes: scripted HTTP, no real sleeping, fixed random_id.
 */
abstract class VkClientTestCase extends TestCase
{
    protected const TOKEN = 'test-secret-token';

    protected FakeHttpClient $http;

    protected RecordingSleeper $sleeper;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClient();
        $this->sleeper = new RecordingSleeper();
    }

    protected function makeClient(
        ?RetryPolicy $policy = null,
        ?RandomIdGeneratorInterface $randomIds = null,
    ): VkClient {
        $policy ??= new RetryPolicy(maxAttempts: 3, baseDelaySeconds: 0.5, maxDelaySeconds: 4.0);

        return new VkClient(
            $this->http,
            new StaticTokenProvider(self::TOKEN),
            new ClientConfig(retryPolicy: $policy),
            $this->sleeper,
            $randomIds ?? new SequenceRandomIdGenerator(),
        );
    }
}
