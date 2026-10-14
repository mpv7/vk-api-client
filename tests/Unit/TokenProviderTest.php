<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Tests\Unit;

use Mpv7\VkApi\Auth\EnvTokenProvider;
use Mpv7\VkApi\Auth\FileTokenProvider;
use Mpv7\VkApi\Auth\StaticTokenProvider;
use Mpv7\VkApi\Exception\AuthenticationException;
use PHPUnit\Framework\TestCase;

final class TokenProviderTest extends TestCase
{
    private const VARIABLE = 'VK_API_CLIENT_TEST_TOKEN';

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        putenv(self::VARIABLE);

        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->tempFiles = [];
    }

    public function testStaticProviderReturnsTheToken(): void
    {
        self::assertSame('abc', (new StaticTokenProvider('  abc '))->getToken());
    }

    public function testStaticProviderRejectsEmptyToken(): void
    {
        $this->expectException(AuthenticationException::class);

        new StaticTokenProvider('   ');
    }

    public function testStaticProviderHidesTheTokenFromDumps(): void
    {
        ob_start();
        var_dump(new StaticTokenProvider('super-secret-value'));
        $dump = (string) ob_get_clean();

        self::assertStringNotContainsString('super-secret-value', $dump);
    }

    public function testEnvProviderReadsTheVariable(): void
    {
        putenv(self::VARIABLE . '=from-env');

        self::assertSame('from-env', (new EnvTokenProvider(self::VARIABLE))->getToken());
    }

    public function testEnvProviderFailsWhenVariableIsMissing(): void
    {
        putenv(self::VARIABLE);

        $this->expectException(AuthenticationException::class);

        (new EnvTokenProvider(self::VARIABLE))->getToken();
    }

    public function testEnvProviderFailsWhenVariableIsEmpty(): void
    {
        putenv(self::VARIABLE . '=');

        $this->expectException(AuthenticationException::class);

        (new EnvTokenProvider(self::VARIABLE))->getToken();
    }

    public function testFileProviderTrimsTheTrailingNewline(): void
    {
        $path = $this->tempFile("from-file\n");

        self::assertSame('from-file', (new FileTokenProvider($path))->getToken());
    }

    public function testFileProviderFailsForMissingFile(): void
    {
        $this->expectException(AuthenticationException::class);

        (new FileTokenProvider(sys_get_temp_dir() . '/vk-api-client-no-such-file'))->getToken();
    }

    public function testFileProviderFailsForEmptyFile(): void
    {
        $path = $this->tempFile("\n");

        $this->expectException(AuthenticationException::class);

        (new FileTokenProvider($path))->getToken();
    }

    private function tempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vkt');
        self::assertIsString($path);
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }
}
