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

namespace spamtroll\phpbb\tests\listener;

use PHPUnit\Framework\TestCase;
use spamtroll\phpbb\event\main_listener;
use spamtroll\phpbb\service\client_factory;
use spamtroll\phpbb\service\logger;
use spamtroll\phpbb\service\scanner;
use Spamtroll\Sdk\Http\HttpClientInterface;
use Spamtroll\Sdk\Http\HttpResponse;

/**
 * @covers \spamtroll\phpbb\event\main_listener
 */
final class registration_test extends TestCase
{
    public function test_blocked_registration_appends_error(): void
    {
        $listener = $this->build_listener($this->fake_http(0.95, 'blocked'));

        $event = new \phpbb\event\data([
            'user_row' => [
                'username' => 'spammer',
                'user_email' => 'spammer@bad.tld',
            ],
            'error' => [],
        ]);

        $listener->check_registration($event);
        $errors = $event['error'];

        self::assertIsArray($errors);
        self::assertCount(1, $errors);
        // No \phpbb\user is wired in for unit tests, so the listener
        // falls back to its built-in English copy.
        self::assertStringContainsString('blocked as spam', $errors[0]);
    }

    public function test_safe_registration_leaves_event_untouched(): void
    {
        $listener = $this->build_listener($this->fake_http(0.0, 'safe'));

        $event = new \phpbb\event\data([
            'user_row' => [
                'username' => 'realuser',
                'user_email' => 'real@user.tld',
            ],
            'error' => [],
        ]);

        $listener->check_registration($event);

        self::assertSame([], $event['error']);
    }

    public function test_disabled_check_is_a_no_op(): void
    {
        $listener = $this->build_listener($this->fake_http(0.95, 'blocked'), [
            'spamtroll_check_registration' => 0,
        ]);

        $event = new \phpbb\event\data([
            'user_row' => [
                'username' => 'spammer',
                'user_email' => 'spammer@bad.tld',
            ],
            'error' => [],
        ]);

        $listener->check_registration($event);

        self::assertSame([], $event['error']);
    }

    /**
     * @param array<string, mixed> $config_overrides
     */
    private function build_listener(HttpClientInterface $http, array $config_overrides = []): main_listener
    {
        $config = new \phpbb\config\config(array_merge([
            'spamtroll_api_key' => 'test-key',
            'spamtroll_api_url' => 'https://api.spamtroll.io/api/v1',
            'spamtroll_timeout' => 5,
            'spamtroll_spam_threshold' => '0.7',
            'spamtroll_suspicious_threshold' => '0.4',
            'spamtroll_check_post' => 1,
            'spamtroll_check_pm' => 1,
            'spamtroll_check_registration' => 1,
        ], $config_overrides));

        $factory = new client_factory($config, $http);
        $logger = new class () extends logger {
            public function __construct()
            {
            }
            public function log(array $entry): void
            {
            }
        };
        $scanner = new scanner($factory, $config, $logger, null);

        return new main_listener($scanner, $config, null, null);
    }

    private function fake_http(float $normalised_score, string $status): HttpClientInterface
    {
        $raw = $normalised_score * 30.0;
        $payload = json_encode([
            'success' => true,
            'data' => [
                'status' => $status,
                'spam_score' => $raw,
                'symbols' => [],
            ],
        ]);

        return new class ($payload) implements HttpClientInterface {
            public function __construct(private string $payload)
            {
            }
            public function send(string $method, string $url, array $headers, ?string $body, int $timeout): HttpResponse
            {
                return new HttpResponse(200, $this->payload, []);
            }
        };
    }
}
