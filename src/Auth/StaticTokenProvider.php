<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Auth;

use Mpv7\VkApi\Exception\AuthenticationException;
use SensitiveParameter;

/**
 * Holds a token that the application already has in memory
 * (for example, loaded from a secret manager).
 */
final class StaticTokenProvider implements TokenProviderInterface
{
    private readonly string $token;

    public function __construct(#[SensitiveParameter] string $token)
    {
        $token = trim($token);

        if ($token === '') {
            throw new AuthenticationException('The VK access token must not be empty.');
        }

        $this->token = $token;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Keeps the token out of var_dump() / print_r() output and debug logs.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['token' => '***'];
    }
}
