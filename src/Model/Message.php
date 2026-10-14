<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Model;

use DateTimeImmutable;
use Mpv7\VkApi\Exception\InvalidResponseException;

/**
 * Last message of a dialog.
 */
final class Message
{
    public function __construct(
        public readonly int $id,
        public readonly int $peerId,
        public readonly int $fromId,
        public readonly DateTimeImmutable $date,
        public readonly string $text,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws InvalidResponseException when required fields are missing
     */
    public static function fromApi(array $data): self
    {
        if (!isset($data['id'], $data['date'])) {
            throw new InvalidResponseException('Message payload is missing required fields (id, date).');
        }

        return new self(
            (int) $data['id'],
            (int) ($data['peer_id'] ?? 0),
            (int) ($data['from_id'] ?? 0),
            new DateTimeImmutable('@' . (int) $data['date']),
            (string) ($data['text'] ?? ''),
        );
    }
}
