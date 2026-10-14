<?php

declare(strict_types=1);

namespace Mpv7\VkApi;

use InvalidArgumentException;
use JsonException;
use Mpv7\VkApi\Auth\TokenProviderInterface;
use Mpv7\VkApi\Exception\ApiException;
use Mpv7\VkApi\Exception\HttpException;
use Mpv7\VkApi\Exception\InvalidResponseException;
use Mpv7\VkApi\Exception\UserNotFoundException;
use Mpv7\VkApi\Exception\VkException;
use Mpv7\VkApi\Http\HttpClientInterface;
use Mpv7\VkApi\Http\HttpResponse;
use Mpv7\VkApi\Model\DialogCollection;
use Mpv7\VkApi\Model\User;
use Mpv7\VkApi\Retry\SleeperInterface;
use Mpv7\VkApi\Retry\UsleepSleeper;
use Mpv7\VkApi\Util\RandomIdGeneratorInterface;
use Mpv7\VkApi\Util\SecureRandomIdGenerator;

/**
 * Client for the VK API methods messages.send, messages.getConversations
 * and users.get.
 *
 * Every collaborator (HTTP transport, token source, settings, sleeper,
 * random_id source) is injected through the constructor.
 */
final class VkClient implements VkClientInterface
{
    private const MAX_MESSAGE_LENGTH = 4096;
    private const MAX_DIALOGS_PER_PAGE = 200;

    /** Parameters the caller must not override through $options. */
    private const RESERVED_PARAMS = ['access_token', 'v', 'peer_id', 'message'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly TokenProviderInterface $tokenProvider,
        private readonly ClientConfig $config = new ClientConfig(),
        private readonly SleeperInterface $sleeper = new UsleepSleeper(),
        private readonly RandomIdGeneratorInterface $randomIds = new SecureRandomIdGenerator(),
    ) {
    }

    public function sendMessage(int $userId, string $message, array $options = []): int
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('userId must be a positive integer.');
        }

        if (trim($message) === '') {
            throw new InvalidArgumentException('The message must not be empty.');
        }

        if (mb_strlen($message, 'UTF-8') > self::MAX_MESSAGE_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('The message must not be longer than %d characters.', self::MAX_MESSAGE_LENGTH)
            );
        }

        if (array_key_exists('random_id', $options) && !is_int($options['random_id'])) {
            throw new InvalidArgumentException('random_id must be an integer.');
        }

        $params = $this->normalizeOptions($options);
        $params['peer_id'] = $userId;
        $params['message'] = $message;
        // Generated once, outside the retry loop: a retry re-sends the same random_id,
        // which is what makes a repeated request idempotent on the VK side.
        $params['random_id'] ??= $this->randomIds->generate();

        $messageId = $this->call('messages.send', $params);

        if (!is_int($messageId)) {
            throw new InvalidResponseException('messages.send returned an unexpected payload.');
        }

        return $messageId;
    }

    public function getDialogs(int $offset = 0, int $count = 10): DialogCollection
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('offset must not be negative.');
        }

        if ($count < 1 || $count > self::MAX_DIALOGS_PER_PAGE) {
            throw new InvalidArgumentException(
                sprintf('count must be between 1 and %d.', self::MAX_DIALOGS_PER_PAGE)
            );
        }

        // messages.getDialogs has been retired by VK; messages.getConversations is its successor.
        $response = $this->call('messages.getConversations', [
            'offset' => $offset,
            'count' => $count,
            'extended' => 1,
        ]);

        return DialogCollection::fromApi($response);
    }

    public function getUser(int $userId): User
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('userId must be a positive integer.');
        }

        $response = $this->call('users.get', [
            'user_ids' => $userId,
            'fields' => 'photo_200',
        ]);

        if (!is_array($response)) {
            throw new InvalidResponseException('users.get returned an unexpected payload.');
        }

        if ($response === []) {
            throw new UserNotFoundException($userId);
        }

        $first = $response[0] ?? null;

        if (!is_array($first)) {
            throw new InvalidResponseException('users.get returned an unexpected payload.');
        }

        return User::fromApi($first);
    }

    /**
     * Calls a VK method with retries and returns the "response" part of the answer.
     *
     * @param array<string, string|int|float> $params
     *
     * @throws VkException
     */
    private function call(string $method, array $params): mixed
    {
        $url = $this->config->methodUrl($method);
        $params['v'] = $this->config->apiVersion;
        $params['access_token'] = $this->tokenProvider->getToken();

        $policy = $this->config->retryPolicy;
        $attempt = 1;

        while (true) {
            try {
                $response = $this->httpClient->post($url, $params, $this->config->timeoutSeconds);

                return $this->parseResponse($response);
            } catch (VkException $exception) {
                if (!$exception->isTransient() || $attempt >= $policy->maxAttempts) {
                    throw $exception;
                }

                $this->sleeper->sleep($policy->delayFor($attempt, $exception->getRetryAfter()));
                ++$attempt;
            }
        }
    }

    /**
     * @throws ApiException
     * @throws HttpException
     * @throws InvalidResponseException
     */
    private function parseResponse(HttpResponse $response): mixed
    {
        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            throw new HttpException(
                $response->statusCode,
                $this->parseRetryAfter($response->header('retry-after'))
            );
        }

        try {
            $data = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidResponseException('VK API returned invalid JSON.', 0, $exception);
        }

        if (!is_array($data)) {
            throw new InvalidResponseException('VK API returned an unexpected payload.');
        }

        if (isset($data['error']) && is_array($data['error'])) {
            throw ApiException::fromVkError(
                (int) ($data['error']['error_code'] ?? 0),
                (string) ($data['error']['error_msg'] ?? 'Unknown error')
            );
        }

        if (!array_key_exists('response', $data)) {
            throw new InvalidResponseException('VK API answer contains neither "response" nor "error".');
        }

        return $data['response'];
    }

    /**
     * Only the "delay in seconds" form of Retry-After is supported.
     */
    private function parseRetryAfter(?string $value): ?float
    {
        return $value !== null && is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, string|int|float>
     */
    private function normalizeOptions(array $options): array
    {
        $params = [];

        foreach ($options as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new InvalidArgumentException('Option names must be non-empty strings.');
            }

            if (in_array($key, self::RESERVED_PARAMS, true)) {
                throw new InvalidArgumentException(sprintf('Option "%s" is managed by the client.', $key));
            }

            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                $value = (int) $value;
            }

            if (!is_scalar($value)) {
                throw new InvalidArgumentException(sprintf('Option "%s" must be a scalar value.', $key));
            }

            $params[$key] = $value;
        }

        return $params;
    }
}
