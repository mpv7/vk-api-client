<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Tests\Unit;

use InvalidArgumentException;
use Mpv7\VkApi\Exception\InvalidResponseException;
use Mpv7\VkApi\Exception\NetworkException;
use Mpv7\VkApi\Tests\Support\Responses;
use Mpv7\VkApi\Tests\Support\SequenceRandomIdGenerator;
use Mpv7\VkApi\Tests\Support\VkClientTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SendMessageTest extends VkClientTestCase
{
    public function testSendsMessageAndReturnsItsId(): void
    {
        $this->http->enqueue(Responses::ok(4242));

        $messageId = $this->makeClient()->sendMessage(123, 'Привет, мир!');

        self::assertSame(4242, $messageId);
        self::assertCount(1, $this->http->requests);

        $request = $this->http->requests[0];

        self::assertSame('https://api.vk.com/method/messages.send', $request['url']);
        self::assertSame(123, $request['params']['peer_id']);
        self::assertSame('Привет, мир!', $request['params']['message']);
        self::assertSame(1000, $request['params']['random_id']);
        self::assertSame('5.199', $request['params']['v']);
        self::assertSame(self::TOKEN, $request['params']['access_token']);
        self::assertSame(10.0, $request['timeout']);
    }

    public function testTokenIsSentInTheBodyAndNeverInTheUrl(): void
    {
        $this->http->enqueue(Responses::ok(1));

        $this->makeClient()->sendMessage(1, 'hello');

        self::assertStringNotContainsString(self::TOKEN, $this->http->requests[0]['url']);
    }

    public function testEachCallGetsItsOwnRandomId(): void
    {
        $this->http->enqueue(Responses::ok(1), Responses::ok(2));
        $client = $this->makeClient();

        $client->sendMessage(1, 'same text');
        $client->sendMessage(1, 'same text');

        self::assertSame(1000, $this->http->requests[0]['params']['random_id']);
        self::assertSame(1001, $this->http->requests[1]['params']['random_id']);
    }

    public function testRetryReusesTheSameRandomIdSoTheMessageCannotBeDuplicated(): void
    {
        $ids = new SequenceRandomIdGenerator();
        $this->http->enqueue(new NetworkException('connection reset'), Responses::ok(77));

        $messageId = $this->makeClient(randomIds: $ids)->sendMessage(5, 'hello');

        self::assertSame(77, $messageId);
        self::assertCount(2, $this->http->requests);
        self::assertSame(
            $this->http->requests[0]['params']['random_id'],
            $this->http->requests[1]['params']['random_id']
        );
        self::assertSame(1, $ids->calls);
    }

    public function testCallerCanProvideRandomId(): void
    {
        $ids = new SequenceRandomIdGenerator();
        $this->http->enqueue(Responses::ok(1));

        $this->makeClient(randomIds: $ids)->sendMessage(1, 'hi', ['random_id' => 555]);

        self::assertSame(555, $this->http->requests[0]['params']['random_id']);
        self::assertSame(0, $ids->calls);
    }

    public function testExtraOptionsArePassedThrough(): void
    {
        $this->http->enqueue(Responses::ok(1));

        $this->makeClient()->sendMessage(1, 'hi', [
            'reply_to' => 99,
            'dont_parse_links' => true,
            'ignored' => null,
        ]);

        $params = $this->http->requests[0]['params'];

        self::assertSame(99, $params['reply_to']);
        self::assertSame(1, $params['dont_parse_links']);
        self::assertArrayNotHasKey('ignored', $params);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function reservedOptions(): iterable
    {
        yield 'access_token' => ['access_token'];
        yield 'v' => ['v'];
        yield 'peer_id' => ['peer_id'];
        yield 'message' => ['message'];
    }

    #[DataProvider('reservedOptions')]
    public function testReservedOptionsCannotBeOverridden(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeClient()->sendMessage(1, 'hi', [$name => 'x']);
    }

    public function testNonScalarOptionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeClient()->sendMessage(1, 'hi', ['attachment' => ['photo1_2']]);
    }

    public function testNonIntegerRandomIdIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeClient()->sendMessage(1, 'hi', ['random_id' => '12']);
    }

    public function testInvalidRecipientIsRejectedWithoutRequest(): void
    {
        try {
            $this->makeClient()->sendMessage(0, 'hi');
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            self::assertCount(0, $this->http->requests);
        }
    }

    public function testEmptyMessageIsRejectedWithoutRequest(): void
    {
        try {
            $this->makeClient()->sendMessage(1, "  \n ");
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            self::assertCount(0, $this->http->requests);
        }
    }

    public function testTooLongMessageIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeClient()->sendMessage(1, str_repeat('я', 4097));
    }

    public function testMessageOfMaximumLengthIsAccepted(): void
    {
        $this->http->enqueue(Responses::ok(1));

        $this->makeClient()->sendMessage(1, str_repeat('я', 4096));

        self::assertCount(1, $this->http->requests);
    }

    public function testUnexpectedPayloadIsReported(): void
    {
        $this->http->enqueue(Responses::ok(['unexpected']));

        $this->expectException(InvalidResponseException::class);

        $this->makeClient()->sendMessage(1, 'hi');
    }
}
