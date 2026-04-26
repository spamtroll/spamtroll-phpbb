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

namespace spamtroll\phpbb\tests\scanner;

use PHPUnit\Framework\TestCase;
use spamtroll\phpbb\service\client_factory;
use spamtroll\phpbb\service\http_client;
use spamtroll\phpbb\service\logger;
use spamtroll\phpbb\service\scan_result;
use spamtroll\phpbb\service\scanner;
use Spamtroll\Sdk\Exception\ConnectionException;
use Spamtroll\Sdk\Http\HttpClientInterface;
use Spamtroll\Sdk\Http\HttpResponse;

/**
 * @covers \spamtroll\phpbb\service\scanner
 */
final class scanner_test extends TestCase
{
    public function test_blocks_when_score_above_spam_threshold(): void
    {
        $scanner = $this->build_scanner($this->fake_http(0.95, 'blocked'));
        $result = $scanner->check_post('Buy cheap pills now!', 'spammer', '1.2.3.4');

        self::assertTrue($result->should_block());
        self::assertSame('blocked', $result->status);
        self::assertGreaterThanOrEqual(0.7, $result->score);
    }

    public function test_moderates_when_score_in_suspicious_band(): void
    {
        $scanner = $this->build_scanner($this->fake_http(0.5, 'suspicious'));
        $result = $scanner->check_post('Borderline content', 'user', '1.2.3.4');

        self::assertTrue($result->should_moderate());
        self::assertFalse($result->should_block());
        self::assertSame('suspicious', $result->status);
    }

    public function test_allows_clean_content(): void
    {
        $scanner = $this->build_scanner($this->fake_http(0.05, 'safe'));
        $result = $scanner->check_post('Regular friendly post', 'user', '1.2.3.4');

        self::assertFalse($result->should_block());
        self::assertFalse($result->should_moderate());
        self::assertSame('safe', $result->status);
    }

    public function test_fails_open_on_connection_error(): void
    {
        $http = new class () implements HttpClientInterface {
            public function send(string $method, string $url, array $headers, ?string $body, int $timeout): HttpResponse
            {
                throw ConnectionException::fromMessage('boom');
            }
        };

        $scanner = $this->build_scanner($http);
        $result = $scanner->check_post('Hello world', 'user', '1.2.3.4');

        self::assertSame(scan_result::ACTION_ALLOW, $result->action);
        self::assertFalse($result->api_ok);
    }

    public function test_fails_open_when_api_key_missing(): void
    {
        $config = new \phpbb\config\config([
            'spamtroll_api_key' => '',
            'spamtroll_api_url' => 'https://api.spamtroll.io/api/v1',
            'spamtroll_timeout' => 5,
            'spamtroll_spam_threshold' => '0.7',
            'spamtroll_suspicious_threshold' => '0.4',
        ]);
        $factory = new client_factory($config, new http_client());
        $scanner = new scanner($factory, $config, $this->null_logger(), null);
        $result = $scanner->check_post('Anything', 'user', null);

        self::assertSame(scan_result::ACTION_ALLOW, $result->action);
    }

    public function test_empty_content_short_circuits(): void
    {
        $scanner = $this->build_scanner($this->failing_http());
        $result = $scanner->check_post('   ', null, null);

        self::assertSame(scan_result::ACTION_ALLOW, $result->action);
    }

    public function test_registration_uses_username_and_email(): void
    {
        $scanner = $this->build_scanner($this->fake_http(0.95, 'blocked'));
        $result = $scanner->check_registration('spammer123', 'a@b.c', '1.2.3.4');

        self::assertTrue($result->should_block());
    }

    public function test_pm_path_is_wired(): void
    {
        $scanner = $this->build_scanner($this->fake_http(0.0, 'safe'));
        $result = $scanner->check_pm('hello there', 'user', '1.2.3.4');

        self::assertSame(scan_result::ACTION_ALLOW, $result->action);
    }

    private function build_scanner(HttpClientInterface $http): scanner
    {
        $config = new \phpbb\config\config([
            'spamtroll_api_key' => 'test-key',
            'spamtroll_api_url' => 'https://api.spamtroll.io/api/v1',
            'spamtroll_timeout' => 5,
            'spamtroll_spam_threshold' => '0.7',
            'spamtroll_suspicious_threshold' => '0.4',
        ]);
        $factory = new client_factory($config, $http);
        return new scanner($factory, $config, $this->null_logger(), null);
    }

    private function null_logger(): logger
    {
        return new class () extends logger {
            public function __construct()
            {
            }
            public function log(array $entry): void
            {
            }
            public function cleanup(int $retention_days): int
            {
                return 0;
            }
            public function table_name(): string
            {
                return 'noop';
            }
        };
    }

    private function fake_http(float $normalised_score, string $status): HttpClientInterface
    {
        // Score denominator default = 30 → multiply normalised score by 30
        // to get a raw value the SDK will renormalise the same way.
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

    private function failing_http(): HttpClientInterface
    {
        return new class () implements HttpClientInterface {
            public function send(string $method, string $url, array $headers, ?string $body, int $timeout): HttpResponse
            {
                throw new \RuntimeException('HTTP must not be called');
            }
        };
    }
}
