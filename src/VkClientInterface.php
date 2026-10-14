<?php

declare(strict_types=1);

namespace Mpv7\VkApi;

use InvalidArgumentException;
use Mpv7\VkApi\Exception\VkException;
use Mpv7\VkApi\Model\DialogCollection;
use Mpv7\VkApi\Model\User;

/**
 * Public contract of the library. Depend on this interface in your code
 * so the client can be replaced with a test double.
 */
interface VkClientInterface
{
    /**
     * Sends a text message and returns its id (messages.send).
     *
     * A random_id is generated once per call and reused on every retry,
     * so a retried request can never deliver the message twice. Pass
     * "random_id" in $options to control it yourself.
     *
     * @param int                  $userId  recipient id; for group chats use 2000000000 + chat id
     * @param string               $message text, 1 to 4096 characters
     * @param array<string, mixed> $options extra messages.send parameters, e.g. ['reply_to' => 123]
     *
     * @throws InvalidArgumentException on invalid arguments (no request is made)
     * @throws VkException              on transport, HTTP or VK API errors
     */
    public function sendMessage(int $userId, string $message, array $options = []): int;

    /**
     * Returns a page of dialogs (messages.getConversations).
     *
     * @param int $offset offset from the start of the list, 0 or more
     * @param int $count  page size, 1 to 200
     *
     * @throws InvalidArgumentException on invalid arguments (no request is made)
     * @throws VkException              on transport, HTTP or VK API errors
     */
    public function getDialogs(int $offset = 0, int $count = 10): DialogCollection;

    /**
     * Returns a user's name and avatar (users.get).
     *
     * @throws InvalidArgumentException on invalid arguments (no request is made)
     * @throws \Mpv7\VkApi\Exception\UserNotFoundException when VK returns no such user
     * @throws VkException                                 on transport, HTTP or VK API errors
     */
    public function getUser(int $userId): User;
}
