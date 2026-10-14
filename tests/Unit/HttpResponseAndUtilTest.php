<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Tests\Unit;

use Mpv7\VkApi\Enum\PeerType;
use Mpv7\VkApi\Http\HttpResponse;
use Mpv7\VkApi\Util\SecureRandomIdGenerator;
use PHPUnit\Framework\TestCase;

final class HttpResponseAndUtilTest extends TestCase
{
    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $response = new HttpResponse(429, '', ['Retry-After' => '3']);

        self::assertSame('3', $response->header('retry-after'));
        self::assertSame('3', $response->header('RETRY-AFTER'));
        self::assertNull($response->header('x-missing'));
    }

    public function testRandomIdsStayInsideTheInt32Range(): void
    {
        $generator = new SecureRandomIdGenerator();

        for ($i = 0; $i < 200; ++$i) {
            $id = $generator->generate();

            self::assertGreaterThanOrEqual(1, $id);
            self::assertLessThanOrEqual(2147483647, $id);
        }
    }

    public function testPeerTypeParsing(): void
    {
        self::assertSame(PeerType::User, PeerType::fromVk('user'));
        self::assertSame(PeerType::Chat, PeerType::fromVk('chat'));
        self::assertSame(PeerType::Unknown, PeerType::fromVk('something-new'));
    }
}
