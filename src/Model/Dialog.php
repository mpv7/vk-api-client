<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Model;

use Mpv7\VkApi\Enum\PeerType;
use Mpv7\VkApi\Exception\InvalidResponseException;

/**
 * One conversation from the dialog list.
 */
final class Dialog
{
    public function __construct(
        public readonly int $peerId,
        public readonly PeerType $peerType,
        public readonly ?string $title,
        public readonly int $unreadCount,
        public readonly ?Message $lastMessage,
    ) {
    }

    /**
     * @param array<string, mixed> $item   one element of messages.getConversations "items"
     * @param array<int, string>   $names  display names of profiles and groups, keyed by peer id
     *
     * @throws InvalidResponseException when the item has an unexpected shape
     */
    public static function fromApi(array $item, array $names = []): self
    {
        $conversation = $item['conversation'] ?? null;

        if (
            !is_array($conversation)
            || !isset($conversation['peer'])
            || !is_array($conversation['peer'])
            || !isset($conversation['peer']['id'], $conversation['peer']['type'])
        ) {
            throw new InvalidResponseException('Dialog payload is missing conversation.peer (id, type).');
        }

        $peer = $conversation['peer'];
        $peerId = (int) $peer['id'];

        $title = null;
        $chatSettings = $conversation['chat_settings'] ?? null;

        if (is_array($chatSettings) && isset($chatSettings['title']) && is_string($chatSettings['title'])) {
            $title = $chatSettings['title'];
        } elseif (($names[$peerId] ?? '') !== '') {
            $title = $names[$peerId];
        }

        $lastMessage = isset($item['last_message']) && is_array($item['last_message'])
            ? Message::fromApi($item['last_message'])
            : null;

        return new self(
            $peerId,
            PeerType::fromVk((string) $peer['type']),
            $title,
            (int) ($conversation['unread_count'] ?? 0),
            $lastMessage,
        );
    }
}
