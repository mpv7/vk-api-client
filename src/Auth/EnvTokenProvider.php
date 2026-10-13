<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Auth;

use Mpv7\VkApi\Exception\AuthenticationException;

/**
 * Reads the token from an environment variable (VK_ACCESS_TOKEN by default).
 */
final class EnvTokenProvider implements TokenProviderInterface
{
    public function __construct(private readonly string $variableName = 'VK_ACCESS_TOKEN')
    {
    }

    public function getToken(): string
    {
        $value = getenv($this->variableName);

        if ($value === false) {
            $value = $_ENV[$this->variableName] ?? $_SERVER[$this->variableName] ?? false;
        }

        if (!is_string($value) || trim($value) === '') {
            throw new AuthenticationException(
                sprintf('Environment variable "%s" is not set or empty.', $this->variableName)
            );
        }

        return trim($value);
    }
}
