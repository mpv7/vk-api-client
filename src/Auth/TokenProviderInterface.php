<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Auth;

use Mpv7\VkApi\Exception\AuthenticationException;

/**
 * Supplies the VK access token at request time, so the token never has to
 * live inside the client or in the source code.
 */
interface TokenProviderInterface
{
    /**
     * @throws AuthenticationException when no token is available
     */
    public function getToken(): string;
}
