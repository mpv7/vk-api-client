<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Exception;

use Mpv7\VkApi\Enum\VkErrorCode;
use Throwable;

/**
 * An error object returned by the VK API ({"error": {...}}).
 *
 * Well-known codes are mapped to dedicated subclasses; everything else
 * is reported as this generic class with the original code and message.
 */
class ApiException extends VkException
{
    public function __construct(
        private readonly int $vkCode,
        private readonly string $vkMessage,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('VK API error %d: %s', $vkCode, $vkMessage),
            $vkCode,
            $previous
        );
    }

    /**
     * Translates a VK error code into the matching PHP exception.
     */
    public static function fromVkError(int $code, string $message): self
    {
        return match (VkErrorCode::tryFrom($code)) {
            VkErrorCode::AuthorizationFailed => new AuthorizationFailedException($code, $message),
            VkErrorCode::PermissionDenied,
            VkErrorCode::AccessDenied,
            VkErrorCode::AccessDeniedExtended => new AccessDeniedException($code, $message),
            VkErrorCode::TooManyRequests,
            VkErrorCode::FloodControl,
            VkErrorCode::RateLimitReached => new RateLimitException($code, $message),
            VkErrorCode::InvalidParameter,
            VkErrorCode::InvalidUserId => new InvalidParameterException($code, $message),
            VkErrorCode::MessageBlacklisted,
            VkErrorCode::MessageNotAllowedByUser,
            VkErrorCode::MessagePrivacySettings => new MessageNotAllowedException($code, $message),
            VkErrorCode::InternalServerError => new ServerErrorException($code, $message),
            default => new self($code, $message),
        };
    }

    public function getVkCode(): int
    {
        return $this->vkCode;
    }

    public function getVkMessage(): string
    {
        return $this->vkMessage;
    }

    public function getVkErrorCode(): ?VkErrorCode
    {
        return VkErrorCode::tryFrom($this->vkCode);
    }
}
