<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Exception;

/**
 * Errors 900, 901 and 902: the recipient cannot receive the message
 * (blacklist, messages not allowed, privacy settings).
 */
final class MessageNotAllowedException extends ApiException
{
}
