<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Model;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Mpv7\VkApi\Exception\InvalidResponseException;
use Traversable;

/**
 * A page of dialogs plus the total number of dialogs on the account.
 *
 * @implements IteratorAggregate<int, Dialog>
 */
final class DialogCollection implements Countable, IteratorAggregate
{
    /**
     * @param list<Dialog> $dialogs    dialogs of the requested page
     * @param int          $totalCount total number of dialogs reported by VK
     */
    public function __construct(
        private readonly array $dialogs,
        public readonly int $totalCount,
    ) {
    }

    /**
     * Builds the collection from the "response" part of messages.getConversations.
     *
     * @throws InvalidResponseException when the payload has an unexpected shape
     */
    public static function fromApi(mixed $response): self
    {
        if (!is_array($response) || !isset($response['items']) || !is_array($response['items'])) {
            throw new InvalidResponseException('messages.getConversations returned no "items" list.');
        }

        $names = self::collectNames($response);
        $dialogs = [];

        foreach ($response['items'] as $item) {
            if (!is_array($item)) {
                throw new InvalidResponseException('Dialog item must be an object.');
            }

            $dialogs[] = Dialog::fromApi($item, $names);
        }

        $total = isset($response['count']) ? (int) $response['count'] : count($dialogs);

        return new self($dialogs, $total);
    }

    /**
     * @return list<Dialog>
     */
    public function all(): array
    {
        return $this->dialogs;
    }

    public function isEmpty(): bool
    {
        return $this->dialogs === [];
    }

    /**
     * Number of dialogs in this page (see $totalCount for the account total).
     */
    public function count(): int
    {
        return count($this->dialogs);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->dialogs);
    }

    /**
     * @param array<array-key, mixed> $response
     *
     * @return array<int, string>
     */
    private static function collectNames(array $response): array
    {
        $names = [];
        $profiles = is_array($response['profiles'] ?? null) ? $response['profiles'] : [];
        $groups = is_array($response['groups'] ?? null) ? $response['groups'] : [];

        foreach ($profiles as $profile) {
            if (is_array($profile) && isset($profile['id'])) {
                $names[(int) $profile['id']] = trim(
                    (string) ($profile['first_name'] ?? '') . ' ' . (string) ($profile['last_name'] ?? '')
                );
            }
        }

        foreach ($groups as $group) {
            if (is_array($group) && isset($group['id'])) {
                // Communities have negative peer ids in dialogs.
                $names[-(int) $group['id']] = (string) ($group['name'] ?? '');
            }
        }

        return $names;
    }
}
