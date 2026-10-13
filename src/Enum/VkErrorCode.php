<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Enum;

/**
 * Subset of VK API error codes known to the library.
 *
 * Unknown codes are still reported (as a generic ApiException),
 * so this list does not need to be exhaustive.
 *
 * @see https://dev.vk.com/ru/reference/errors
 */
enum VkErrorCode: int
{
    case UnknownError = 1;
    case AuthorizationFailed = 5;
    case TooManyRequests = 6;
    case PermissionDenied = 7;
    case InvalidRequest = 8;
    case FloodControl = 9;
    case InternalServerError = 10;
    case CaptchaNeeded = 14;
    case AccessDenied = 15;
    case UserDeletedOrBanned = 18;
    case RateLimitReached = 29;
    case InvalidParameter = 100;
    case InvalidUserId = 113;
    case MessageBlacklisted = 900;
    case MessageNotAllowedByUser = 901;
    case MessagePrivacySettings = 902;

    /**
     * Code 188 is named "Access denied" in the assignment. It is handled
     * exactly like code 15.
     */
    case AccessDeniedExtended = 188;
}
