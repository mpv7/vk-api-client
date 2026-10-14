<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Tests\Unit;

use Mpv7\VkApi\Exception\AccessDeniedException;
use Mpv7\VkApi\Exception\ApiException;
use Mpv7\VkApi\Exception\AuthorizationFailedException;
use Mpv7\VkApi\Exception\HttpException;
use Mpv7\VkApi\Exception\InvalidResponseException;
use Mpv7\VkApi\Exception\NetworkException;
use Mpv7\VkApi\Exception\RateLimitException;
use Mpv7\VkApi\Exception\ServerErrorException;
use Mpv7\VkApi\Retry\RetryPolicy;
use Mpv7\VkApi\Tests\Support\Responses;
use Mpv7\VkApi\Tests\Support\VkClientTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Network failures, access errors, retries and malformed answers.
 * getUser() is used as the vehicle because it exercises the shared request pipeline.
 */
final class ErrorHandlingTest extends VkClientTestCase
{
    private const USER_PAYLOAD = [['id' => 1, 'first_name' => 'Ivan', 'last_name' => 'Petrov']];

    // ---- network errors -------------------------------------------------

    public function testNetworkErrorIsRetriedAndThenSucceeds(): void
    {
        $this->http->enqueue(new NetworkException('timeout'), Responses::ok(self::USER_PAYLOAD));

        $user = $this->makeClient()->getUser(1);

        self::assertSame('Ivan', $user->firstName);
        self::assertCount(2, $this->http->requests);
        self::assertSame([0.5], $this->sleeper->delays);
    }

    public function testNetworkErrorIsThrownWhenAttemptsAreExhausted(): void
    {
        $this->http->enqueue(
            new NetworkException('timeout 1'),
            new NetworkException('timeout 2'),
            new NetworkException('timeout 3'),
        );

        try {
            $this->makeClient()->getUser(1);
            self::fail('Expected NetworkException.');
        } catch (NetworkException $exception) {
            self::assertSame('timeout 3', $exception->getMessage());
        }

        self::assertCount(3, $this->http->requests);
        self::assertSame([0.5, 1.0], $this->sleeper->delays);
    }

    public function testNothingIsRetriedWhenRetriesAreDisabled(): void
    {
        $this->http->enqueue(new NetworkException('down'));

        try {
            $this->makeClient(RetryPolicy::none())->getUser(1);
            self::fail('Expected NetworkException.');
        } catch (NetworkException) {
            self::assertCount(1, $this->http->requests);
            self::assertSame([], $this->sleeper->delays);
        }
    }

    // ---- access errors --------------------------------------------------

    /**
     * @return iterable<string, array{int}>
     */
    public static function accessDeniedCodes(): iterable
    {
        yield 'access denied (15)' => [15];
        yield 'permission denied (7)' => [7];
        yield 'access denied from the assignment (188)' => [188];
    }

    #[DataProvider('accessDeniedCodes')]
    public function testAccessDeniedIsTranslatedAndNotRetried(int $code): void
    {
        $this->http->enqueue(Responses::apiError($code, 'Access denied'));

        try {
            $this->makeClient()->getUser(1);
            self::fail('Expected AccessDeniedException.');
        } catch (AccessDeniedException $exception) {
            self::assertSame($code, $exception->getVkCode());
            self::assertSame('Access denied', $exception->getVkMessage());
            self::assertFalse($exception->isTransient());
        }

        self::assertCount(1, $this->http->requests);
        self::assertSame([], $this->sleeper->delays);
    }

    public function testInvalidTokenIsReportedAsAuthorizationFailure(): void
    {
        $this->http->enqueue(Responses::apiError(5, 'User authorization failed: invalid access_token'));

        try {
            $this->makeClient()->getUser(1);
            self::fail('Expected AuthorizationFailedException.');
        } catch (AuthorizationFailedException $exception) {
            self::assertSame(5, $exception->getVkCode());
            self::assertStringNotContainsString(self::TOKEN, $exception->getMessage());
        }

        self::assertCount(1, $this->http->requests);
    }

    public function testUnknownVkErrorKeepsCodeAndMessage(): void
    {
        $this->http->enqueue(Responses::apiError(999, 'Brand new error'));

        try {
            $this->makeClient()->getUser(1);
            self::fail('Expected ApiException.');
        } catch (ApiException $exception) {
            self::assertSame(ApiException::class, $exception::class);
            self::assertSame(999, $exception->getVkCode());
            self::assertSame('Brand new error', $exception->getVkMessage());
            self::assertNull($exception->getVkErrorCode());
        }
    }

    // ---- temporary limits -----------------------------------------------

    public function testTooManyRequestsPerSecondIsRetried(): void
    {
        $this->http->enqueue(
            Responses::apiError(6, 'Too many requests per second'),
            Responses::ok(self::USER_PAYLOAD),
        );

        $user = $this->makeClient()->getUser(1);

        self::assertSame(1, $user->id);
        self::assertCount(2, $this->http->requests);
        self::assertSame([0.5], $this->sleeper->delays);
    }

    public function testRateLimitIsThrownWhenAttemptsAreExhausted(): void
    {
        $this->http->enqueue(
            Responses::apiError(6),
            Responses::apiError(6),
            Responses::apiError(6),
        );

        $this->expectException(RateLimitException::class);

        $this->makeClient()->getUser(1);
    }

    public function testMethodRateLimitIsNotRetried(): void
    {
        $this->http->enqueue(Responses::apiError(29, 'Rate limit reached'));

        try {
            $this->makeClient()->getUser(1);
            self::fail('Expected RateLimitException.');
        } catch (RateLimitException $exception) {
            self::assertFalse($exception->isTransient());
        }

        self::assertCount(1, $this->http->requests);
    }

    public function testServerErrorIsRetried(): void
    {
        $this->http->enqueue(Responses::apiError(10, 'Internal server error'), Responses::ok(self::USER_PAYLOAD));

        $this->makeClient()->getUser(1);

        self::assertCount(2, $this->http->requests);
    }

    public function testServerErrorIsThrownWhenAttemptsAreExhausted(): void
    {
        $this->http->enqueue(Responses::apiError(10), Responses::apiError(10), Responses::apiError(10));

        $this->expectException(ServerErrorException::class);

        $this->makeClient()->getUser(1);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function transientHttpStatuses(): iterable
    {
        yield '420' => [420];
        yield '429' => [429];
        yield '500' => [500];
        yield '502' => [502];
        yield '503' => [503];
        yield '504' => [504];
    }

    #[DataProvider('transientHttpStatuses')]
    public function testTransientHttpStatusIsRetried(int $status): void
    {
        $this->http->enqueue(Responses::http($status), Responses::ok(self::USER_PAYLOAD));

        $this->makeClient()->getUser(1);

        self::assertCount(2, $this->http->requests);
        self::assertSame([0.5], $this->sleeper->delays);
    }

    public function testRetryAfterHeaderOverridesBackoff(): void
    {
        $this->http->enqueue(Responses::http(429, ['Retry-After' => '2']), Responses::ok(self::USER_PAYLOAD));

        $this->makeClient()->getUser(1);

        self::assertSame([2.0], $this->sleeper->delays);
    }

    public function testRetryAfterIsCappedByMaxDelay(): void
    {
        $this->http->enqueue(Responses::http(429, ['Retry-After' => '3600']), Responses::ok(self::USER_PAYLOAD));

        $this->makeClient()->getUser(1);

        self::assertSame([4.0], $this->sleeper->delays);
    }

    public function testPermanentHttpStatusIsNotRetried(): void
    {
        $this->http->enqueue(Responses::http(403));

        try {
            $this->makeClient()->getUser(1);
            self::fail('Expected HttpException.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertFalse($exception->isTransient());
        }

        self::assertCount(1, $this->http->requests);
    }

    public function testHttpErrorIsThrownWhenAttemptsAreExhausted(): void
    {
        $this->http->enqueue(Responses::http(503), Responses::http(503), Responses::http(503));

        try {
            $this->makeClient()->getUser(1);
            self::fail('Expected HttpException.');
        } catch (HttpException $exception) {
            self::assertSame(503, $exception->getStatusCode());
        }

        self::assertCount(3, $this->http->requests);
    }

    // ---- malformed answers ----------------------------------------------

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedBodies(): iterable
    {
        yield 'not json' => ['<html>Bad gateway</html>'];
        yield 'empty body' => [''];
        yield 'json null' => ['null'];
        yield 'json scalar' => ['42'];
        yield 'neither response nor error' => ['{"foo": "bar"}'];
    }

    #[DataProvider('malformedBodies')]
    public function testMalformedAnswerIsReportedAndNotRetried(string $body): void
    {
        $this->http->enqueue(Responses::raw($body));

        try {
            $this->makeClient()->getUser(1);
            self::fail('Expected InvalidResponseException.');
        } catch (InvalidResponseException) {
            self::assertCount(1, $this->http->requests);
            self::assertSame([], $this->sleeper->delays);
        }
    }
}
