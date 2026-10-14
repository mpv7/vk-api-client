<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Model;

use Mpv7\VkApi\Exception\InvalidResponseException;

/**
 * VK user as returned by users.get.
 */
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $avatarUrl = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data one element of the users.get response
     *
     * @throws InvalidResponseException when required fields are missing
     */
    public static function fromApi(array $data): self
    {
        if (!isset($data['id'], $data['first_name'], $data['last_name'])) {
            throw new InvalidResponseException(
                'User payload is missing required fields (id, first_name, last_name).'
            );
        }

        $avatar = $data['photo_200'] ?? null;

        return new self(
            (int) $data['id'],
            (string) $data['first_name'],
            (string) $data['last_name'],
            is_string($avatar) && $avatar !== '' ? $avatar : null,
        );
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }
}
