<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Exception;

/**
 * The access token is missing or unreadable on the client side.
 * (A token rejected by VK is reported as AuthorizationFailedException.)
 */
final class AuthenticationException extends VkException
{
}
