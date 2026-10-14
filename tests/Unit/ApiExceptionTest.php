<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Tests\Unit;

use Mpv7\VkApi\Enum\VkErrorCode;
use Mpv7\VkApi\Exception\AccessDeniedException;
use Mpv7\VkApi\Exception\ApiException;
use Mpv7\VkApi\Exception\AuthorizationFailedException;
use Mpv7\VkApi\Exception\InvalidParameterException;
use Mpv7\VkApi\Exception\MessageNotAllowedException;
use Mpv7\VkApi\Exception\RateLimitException;
use Mpv7\VkApi\Exception\ServerErrorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApiExceptionTest extends TestCase
{
    /**
     * @return iterable<string, array{int, class-string<ApiException>, bool}>
     */
    public static function mapping(): iterable
    {
        yield '5 authorization failed' => [5, AuthorizationFailedException::class, false];
        yield '6 too many requests' => [6, RateLimitException::class, true];
        yield '7 permission denied' => [7, AccessDeniedException::class, false];
        yield '9 flood control' => [9, RateLimitException::class, false];
        yield '10 internal server error' => [10, ServerErrorException::class, true];
        yield '15 access denied' => [15, AccessDeniedException::class, false];
        yield '29 rate limit reached' => [29, RateLimitException::class, false];
        yield '100 invalid parameter' => [100, InvalidParameterException::class, false];
        yield '113 invalid user id' => [113, InvalidParameterException::class, false];
        yield '188 access denied (assignment)' => [188, AccessDeniedException::class, false];
        yield '900 blacklisted' => [900, MessageNotAllowedException::class, false];
        yield '901 not allowed' => [901, MessageNotAllowedException::class, false];
        yield '902 privacy settings' => [902, MessageNotAllowedException::class, false];
        yield '14 captcha (generic)' => [14, ApiException::class, false];
        yield '999 unknown (generic)' => [999, ApiException::class, false];
    }

    /**
     * @param class-string<ApiException> $expectedClass
     */
    #[DataProvider('mapping')]
    public function testVkCodesAreMappedToExceptions(int $code, string $expectedClass, bool $transient): void
    {
        $exception = ApiException::fromVkError($code, 'some message');

        self::assertSame($expectedClass, $exception::class);
        self::assertSame($code, $exception->getVkCode());
        self::assertSame($code, $exception->getCode());
        self::assertSame('some message', $exception->getVkMessage());
        self::assertSame($transient, $exception->isTransient());
        self::assertSame(sprintf('VK API error %d: some message', $code), $exception->getMessage());
    }

    public function testKnownCodeExposesTheEnum(): void
    {
        self::assertSame(VkErrorCode::AccessDenied, ApiException::fromVkError(15, 'x')->getVkErrorCode());
        self::assertNull(ApiException::fromVkError(999, 'x')->getVkErrorCode());
    }

    public function testErrorCodeEnumValues(): void
    {
        self::assertSame(15, VkErrorCode::AccessDenied->value);
        self::assertSame(6, VkErrorCode::TooManyRequests->value);
        self::assertSame(VkErrorCode::AuthorizationFailed, VkErrorCode::from(5));
        self::assertNull(VkErrorCode::tryFrom(123456));
    }
}
