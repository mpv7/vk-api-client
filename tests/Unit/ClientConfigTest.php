<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Tests\Unit;

use InvalidArgumentException;
use Mpv7\VkApi\ClientConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = new ClientConfig();

        self::assertSame('5.199', $config->apiVersion);
        self::assertSame(10.0, $config->timeoutSeconds);
        self::assertSame('https://api.vk.com/method/users.get', $config->methodUrl('users.get'));
    }

    public function testTrailingSlashInBaseUrlIsHandled(): void
    {
        $config = new ClientConfig(baseUrl: 'https://proxy.example.com/vk/');

        self::assertSame('https://proxy.example.com/vk/users.get', $config->methodUrl('users.get'));
    }

    public function testPlainHttpIsRejectedBecauseTheTokenIsSentWithEveryRequest(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ClientConfig(baseUrl: 'http://api.vk.com/method');
    }

    public function testNonPositiveTimeoutIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ClientConfig(timeoutSeconds: 0.0);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badVersions(): iterable
    {
        yield 'empty' => [''];
        yield 'word' => ['latest'];
        yield 'no minor part' => ['5'];
        yield 'three parts' => ['5.199.1'];
    }

    #[DataProvider('badVersions')]
    public function testMalformedApiVersionIsRejected(string $version): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ClientConfig(apiVersion: $version);
    }
}
