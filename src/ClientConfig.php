<?php

declare(strict_types=1);

namespace Mpv7\VkApi;

use InvalidArgumentException;
use Mpv7\VkApi\Retry\RetryPolicy;

/**
 * Immutable client settings. All values have safe defaults.
 */
final class ClientConfig
{
    public const DEFAULT_API_VERSION = '5.199';
    public const DEFAULT_BASE_URL = 'https://api.vk.com/method';

    /**
     * @param string      $apiVersion     VK API version sent as the "v" parameter
     * @param string      $baseUrl        base URL of the method endpoints; must use HTTPS
     * @param float       $timeoutSeconds connect and total timeout of a single HTTP request
     * @param RetryPolicy $retryPolicy    behaviour on network errors and temporary limits
     */
    public function __construct(
        public readonly string $apiVersion = self::DEFAULT_API_VERSION,
        public readonly string $baseUrl = self::DEFAULT_BASE_URL,
        public readonly float $timeoutSeconds = 10.0,
        public readonly RetryPolicy $retryPolicy = new RetryPolicy(),
    ) {
        if (preg_match('/^\d+\.\d+$/', $apiVersion) !== 1) {
            throw new InvalidArgumentException('apiVersion must look like "5.199".');
        }

        if (!str_starts_with($baseUrl, 'https://')) {
            throw new InvalidArgumentException('baseUrl must use HTTPS: the access token is sent with every request.');
        }

        if ($timeoutSeconds <= 0) {
            throw new InvalidArgumentException('timeoutSeconds must be greater than zero.');
        }
    }

    public function methodUrl(string $method): string
    {
        return rtrim($this->baseUrl, '/') . '/' . $method;
    }
}
