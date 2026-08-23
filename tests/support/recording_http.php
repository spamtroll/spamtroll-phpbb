<?php

declare(strict_types=1);

/**
 *
 * Spamtroll Anti-Spam extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Spamtroll
 * @license   GPL-2.0-only
 *
 */

namespace spamtroll\phpbb\tests\support;

use Spamtroll\Sdk\Http\HttpClientInterface;
use Spamtroll\Sdk\Http\HttpResponse;

/**
 * Canned-response HTTP client that keeps every request body it saw, so a
 * test can assert both "was the API called at all" and "what content did
 * the listener actually send".
 */
final class recording_http implements HttpClientInterface
{
    private HttpResponse $response;

    /** @var array<int, array<string, mixed>> */
    public array $requests = [];

    public function __construct(HttpResponse $response)
    {
        $this->response = $response;
    }

    public function send(string $method, string $url, array $headers, ?string $body, int $timeout): HttpResponse
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'body' => $body,
        ];

        return $this->response;
    }

    public function call_count(): int
    {
        return count($this->requests);
    }

    /**
     * Decoded payload of the nth request.
     *
     * @return array<string, mixed>
     */
    public function payload(int $index = 0): array
    {
        $body = $this->requests[$index]['body'] ?? '';
        $decoded = is_string($body) && $body !== '' ? json_decode($body, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
