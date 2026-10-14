<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Tests\Unit;

use InvalidArgumentException;
use Mpv7\VkApi\Enum\PeerType;
use Mpv7\VkApi\Exception\AccessDeniedException;
use Mpv7\VkApi\Exception\InvalidResponseException;
use Mpv7\VkApi\Model\Dialog;
use Mpv7\VkApi\Model\Message;
use Mpv7\VkApi\Tests\Support\Responses;
use Mpv7\VkApi\Tests\Support\VkClientTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class GetDialogsTest extends VkClientTestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function conversations(): array
    {
        return [
            'count' => 42,
            'items' => [
                [
                    'conversation' => [
                        'peer' => ['id' => 100, 'type' => 'user', 'local_id' => 100],
                        'unread_count' => 3,
                    ],
                    'last_message' => [
                        'id' => 11,
                        'date' => 1700000000,
                        'from_id' => 100,
                        'peer_id' => 100,
                        'text' => 'Привет!',
                    ],
                ],
                [
                    'conversation' => [
                        'peer' => ['id' => 2000000005, 'type' => 'chat', 'local_id' => 5],
                        'chat_settings' => ['title' => 'Команда'],
                    ],
                    'last_message' => [
                        'id' => 12,
                        'date' => 1700000100,
                        'from_id' => 100,
                        'peer_id' => 2000000005,
                        'text' => 'Релиз завтра',
                    ],
                ],
                [
                    'conversation' => [
                        'peer' => ['id' => -777, 'type' => 'group', 'local_id' => 777],
                    ],
                ],
            ],
            'profiles' => [
                ['id' => 100, 'first_name' => 'Иван', 'last_name' => 'Петров'],
            ],
            'groups' => [
                ['id' => 777, 'name' => 'Новости'],
            ],
        ];
    }

    public function testReturnsTypedDialogObjects(): void
    {
        $this->http->enqueue(Responses::ok(self::conversations()));

        $dialogs = $this->makeClient()->getDialogs();

        self::assertSame(42, $dialogs->totalCount);
        self::assertCount(3, $dialogs);
        self::assertFalse($dialogs->isEmpty());

        foreach ($dialogs as $dialog) {
            self::assertInstanceOf(Dialog::class, $dialog);
        }

        [$user, $chat, $group] = $dialogs->all();

        self::assertSame(100, $user->peerId);
        self::assertSame(PeerType::User, $user->peerType);
        self::assertSame('Иван Петров', $user->title);
        self::assertSame(3, $user->unreadCount);
        self::assertInstanceOf(Message::class, $user->lastMessage);
        self::assertSame(11, $user->lastMessage->id);
        self::assertSame('Привет!', $user->lastMessage->text);
        self::assertSame(1700000000, $user->lastMessage->date->getTimestamp());

        self::assertSame(2000000005, $chat->peerId);
        self::assertSame(PeerType::Chat, $chat->peerType);
        self::assertSame('Команда', $chat->title);
        self::assertSame(0, $chat->unreadCount);

        self::assertSame(-777, $group->peerId);
        self::assertSame(PeerType::Group, $group->peerType);
        self::assertSame('Новости', $group->title);
        self::assertNull($group->lastMessage);
    }

    public function testSendsPagingParametersToMessagesGetConversations(): void
    {
        $this->http->enqueue(Responses::ok(['count' => 0, 'items' => []]));

        $this->makeClient()->getDialogs(20, 5);

        $request = $this->http->requests[0];

        self::assertSame('https://api.vk.com/method/messages.getConversations', $request['url']);
        self::assertSame(20, $request['params']['offset']);
        self::assertSame(5, $request['params']['count']);
        self::assertSame(1, $request['params']['extended']);
        self::assertSame(self::TOKEN, $request['params']['access_token']);
    }

    public function testDefaultsAreFirstTenDialogs(): void
    {
        $this->http->enqueue(Responses::ok(['count' => 0, 'items' => []]));

        $this->makeClient()->getDialogs();

        self::assertSame(0, $this->http->requests[0]['params']['offset']);
        self::assertSame(10, $this->http->requests[0]['params']['count']);
    }

    public function testEmptyPageIsAnEmptyCollection(): void
    {
        $this->http->enqueue(Responses::ok(['count' => 0, 'items' => []]));

        $dialogs = $this->makeClient()->getDialogs();

        self::assertTrue($dialogs->isEmpty());
        self::assertCount(0, $dialogs);
        self::assertSame([], $dialogs->all());
        self::assertSame(0, $dialogs->totalCount);
    }

    public function testUnknownPeerTypeFallsBackToUnknown(): void
    {
        $payload = ['count' => 1, 'items' => [['conversation' => ['peer' => ['id' => 1, 'type' => 'robot']]]]];
        $this->http->enqueue(Responses::ok($payload));

        $dialogs = $this->makeClient()->getDialogs();

        self::assertSame(PeerType::Unknown, $dialogs->all()[0]->peerType);
        self::assertNull($dialogs->all()[0]->title);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function malformedPayloads(): iterable
    {
        yield 'no items key' => [['count' => 1]];
        yield 'items is not a list' => [['items' => 'oops']];
        yield 'response is a scalar' => [5];
        yield 'item is not an object' => [['items' => ['oops']]];
        yield 'item without conversation' => [['items' => [['last_message' => []]]]];
        yield 'conversation without peer type' => [['items' => [['conversation' => ['peer' => ['id' => 1]]]]]];
        yield 'last message without id' => [
            [
                'items' => [
                    [
                        'conversation' => ['peer' => ['id' => 1, 'type' => 'user']],
                        'last_message' => ['date' => 1],
                    ],
                ],
            ],
        ];
    }

    #[DataProvider('malformedPayloads')]
    public function testMalformedPayloadIsReported(mixed $payload): void
    {
        $this->http->enqueue(Responses::ok($payload));

        $this->expectException(InvalidResponseException::class);

        $this->makeClient()->getDialogs();
    }

    public function testAccessErrorIsTranslated(): void
    {
        $this->http->enqueue(Responses::apiError(15, 'Access denied: no access to messages'));

        $this->expectException(AccessDeniedException::class);

        $this->makeClient()->getDialogs();
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidPaging(): iterable
    {
        yield 'negative offset' => [-1, 10];
        yield 'zero count' => [0, 0];
        yield 'count above the limit' => [0, 201];
    }

    #[DataProvider('invalidPaging')]
    public function testInvalidPagingIsRejectedWithoutRequest(int $offset, int $count): void
    {
        try {
            $this->makeClient()->getDialogs($offset, $count);
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            self::assertCount(0, $this->http->requests);
        }
    }

    public function testMaximumPageSizeIsAccepted(): void
    {
        $this->http->enqueue(Responses::ok(['count' => 0, 'items' => []]));

        $this->makeClient()->getDialogs(0, 200);

        self::assertSame(200, $this->http->requests[0]['params']['count']);
    }
}
