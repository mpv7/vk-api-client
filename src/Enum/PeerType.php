<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Enum;

/**
 * Type of the conversation partner (peer) as reported by VK.
 */
enum PeerType: string
{
    case User = 'user';
    case Chat = 'chat';
    case Group = 'group';
    case Email = 'email';
    case Unknown = 'unknown';

    public static function fromVk(string $value): self
    {
        return self::tryFrom($value) ?? self::Unknown;
    }
}
