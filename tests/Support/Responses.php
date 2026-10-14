<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Tests\Support;

use Mpv7\VkApi\Http\HttpResponse;

/**
 * Builders for typical VK answers.
 */
final class Responses
{
    private function __construct()
    {
    }

    public static function ok(mixed $payload): HttpResponse
    {
        return new HttpResponse(200, json_encode(['response' => $payload], JSON_THROW_ON_ERROR));
    }

    public static function apiError(int $code, string $message = 'Something went wrong'): HttpResponse
    {
        $body = ['error' => ['error_code' => $code, 'error_msg' => $message, 'request_params' => []]];

        return new HttpResponse(200, json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, string> $headers
     */
    public static function http(int $status, array $headers = [], string $body = ''): HttpResponse
    {
        return new HttpResponse($status, $body, $headers);
    }

    public static function raw(string $body, int $status = 200): HttpResponse
    {
        return new HttpResponse($status, $body);
    }
}
