<?php

declare(strict_types=1);

namespace Mpv7\VkApi\Tests\Support;

use LogicException;
use Mpv7\VkApi\Http\HttpClientInterface;
use Mpv7\VkApi\Http\HttpResponse;
use Throwable;

/**
 * Scripted HTTP transport: returns (or throws) queued items in order and
 * records every request it receives.
 */
final class FakeHttpClient implements HttpClientInterface
{
    /** @var list<HttpResponse|Throwable> */
    private array $queue = [];

    /** @var list<array{url: string, params: array<string, string|int|float>, timeout: float}> */
    public array $requests = [];

    public function enqueue(HttpResponse|Throwable ...$items): void
    {
        foreach ($items as $item) {
            $this->queue[] = $item;
        }
    }

    public function post(string $url, array $params, float $timeoutSeconds): HttpResponse
    {
        $this->requests[] = ['url' => $url, 'params' => $params, 'timeout' => $timeoutSeconds];

        $next = array_shift($this->queue);

        if ($next === null) {
            throw new LogicException('FakeHttpClient: no response queued for this request.');
        }

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }
}
