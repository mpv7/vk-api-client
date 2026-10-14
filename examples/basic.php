<?php

declare(strict_types=1);

use Mpv7\VkApi\Auth\EnvTokenProvider;
use Mpv7\VkApi\Exception\AccessDeniedException;
use Mpv7\VkApi\Exception\VkException;
use Mpv7\VkApi\Http\CurlHttpClient;
use Mpv7\VkApi\VkClient;

// Usage:
//   export VK_ACCESS_TOKEN=...    (PowerShell: $env:VK_ACCESS_TOKEN = "...")
//   php examples/basic.php 123456789 "Hello from PHP"

require __DIR__ . '/../vendor/autoload.php';

$userId = (int) ($argv[1] ?? 0);
$text = $argv[2] ?? 'Hello from PHP';

$client = new VkClient(new CurlHttpClient(), new EnvTokenProvider());

try {
    $user = $client->getUser($userId);
    echo 'User: ' . $user->getFullName() . ' (' . ($user->avatarUrl ?? 'no avatar') . ')' . PHP_EOL;

    $messageId = $client->sendMessage($userId, $text);
    echo 'Sent message #' . $messageId . PHP_EOL;

    $dialogs = $client->getDialogs(0, 5);
    echo 'Dialogs on the account: ' . $dialogs->totalCount . PHP_EOL;

    foreach ($dialogs as $dialog) {
        echo sprintf(
            '- %s [%s] unread: %d, last: %s' . PHP_EOL,
            $dialog->title ?? ('peer ' . $dialog->peerId),
            $dialog->peerType->value,
            $dialog->unreadCount,
            $dialog->lastMessage?->text ?? '-'
        );
    }
} catch (AccessDeniedException $exception) {
    fwrite(STDERR, 'The token has no access: ' . $exception->getVkMessage() . PHP_EOL);
    exit(1);
} catch (VkException $exception) {
    fwrite(STDERR, 'VK request failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
