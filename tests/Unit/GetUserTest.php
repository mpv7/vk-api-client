<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Tests\Unit;

use InvalidArgumentException;
use Mpv7\VkApi\Exception\AccessDeniedException;
use Mpv7\VkApi\Exception\InvalidParameterException;
use Mpv7\VkApi\Exception\InvalidResponseException;
use Mpv7\VkApi\Exception\UserNotFoundException;
use Mpv7\VkApi\Tests\Support\Responses;
use Mpv7\VkApi\Tests\Support\VkClientTestCase;

final class GetUserTest extends VkClientTestCase
{
    public function testReturnsNameAndAvatar(): void
    {
        $this->http->enqueue(Responses::ok([
            [
                'id' => 5,
                'first_name' => 'Анна',
                'last_name' => 'Иванова',
                'photo_200' => 'https://example.com/a.jpg',
                'can_access_closed' => true,
                'is_closed' => false,
            ],
        ]));

        $user = $this->makeClient()->getUser(5);

        self::assertSame(5, $user->id);
        self::assertSame('Анна', $user->firstName);
        self::assertSame('Иванова', $user->lastName);
        self::assertSame('https://example.com/a.jpg', $user->avatarUrl);
        self::assertSame('Анна Иванова', $user->getFullName());
    }

    public function testRequestsTheAvatarField(): void
    {
        $this->http->enqueue(Responses::ok([['id' => 5, 'first_name' => 'A', 'last_name' => 'B']]));

        $this->makeClient()->getUser(5);

        $request = $this->http->requests[0];

        self::assertSame('https://api.vk.com/method/users.get', $request['url']);
        self::assertSame(5, $request['params']['user_ids']);
        self::assertSame('photo_200', $request['params']['fields']);
    }

    public function testMissingAvatarIsNull(): void
    {
        $this->http->enqueue(Responses::ok([['id' => 5, 'first_name' => 'A', 'last_name' => 'B']]));

        self::assertNull($this->makeClient()->getUser(5)->avatarUrl);
    }

    public function testEmptyListMeansUserNotFound(): void
    {
        $this->http->enqueue(Responses::ok([]));

        try {
            $this->makeClient()->getUser(404);
            self::fail('Expected UserNotFoundException.');
        } catch (UserNotFoundException $exception) {
            self::assertSame(404, $exception->getUserId());
        }
    }

    public function testIncompleteUserPayloadIsReported(): void
    {
        $this->http->enqueue(Responses::ok([['id' => 5]]));

        $this->expectException(InvalidResponseException::class);

        $this->makeClient()->getUser(5);
    }

    public function testNonListPayloadIsReported(): void
    {
        $this->http->enqueue(Responses::ok('oops'));

        $this->expectException(InvalidResponseException::class);

        $this->makeClient()->getUser(5);
    }

    public function testVkParameterErrorIsTranslated(): void
    {
        $this->http->enqueue(Responses::apiError(113, 'Invalid user id'));

        $this->expectException(InvalidParameterException::class);

        $this->makeClient()->getUser(5);
    }

    public function testAccessErrorIsTranslated(): void
    {
        $this->http->enqueue(Responses::apiError(15, 'Access denied'));

        $this->expectException(AccessDeniedException::class);

        $this->makeClient()->getUser(5);
    }

    public function testInvalidUserIdIsRejectedWithoutRequest(): void
    {
        try {
            $this->makeClient()->getUser(0);
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            self::assertCount(0, $this->http->requests);
        }
    }
}
