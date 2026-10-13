<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Auth;

use Mpv7\VkApi\Exception\AuthenticationException;

/**
 * Reads the token from a file (for example, a Docker/Kubernetes secret).
 * Surrounding whitespace, including the trailing newline, is ignored.
 */
final class FileTokenProvider implements TokenProviderInterface
{
    public function __construct(private readonly string $path)
    {
    }

    public function getToken(): string
    {
        if (!is_file($this->path) || !is_readable($this->path)) {
            throw new AuthenticationException(
                sprintf('Token file "%s" does not exist or is not readable.', $this->path)
            );
        }

        $content = file_get_contents($this->path);

        if ($content === false || trim($content) === '') {
            throw new AuthenticationException(sprintf('Token file "%s" is empty.', $this->path));
        }

        return trim($content);
    }
}
