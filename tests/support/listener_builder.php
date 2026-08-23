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

use spamtroll\phpbb\event\main_listener;
use spamtroll\phpbb\service\client_factory;
use spamtroll\phpbb\service\logger;
use spamtroll\phpbb\service\scanner;
use Spamtroll\Sdk\Http\HttpClientInterface;
use Spamtroll\Sdk\Http\HttpResponse;

/**
 * Assembles a real main_listener over a real scanner, with only the HTTP
 * transport and the phpBB collaborators faked out.
 */
final class listener_builder
{
    /**
     * @param array<string, mixed> $config_overrides
     */
    public static function build(
        HttpClientInterface $http,
        ?fake_request $request = null,
        array $config_overrides = []
    ): main_listener {
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

        $scanner = new scanner(
            new client_factory($config, $http),
            $config,
            self::null_logger(),
            null
        );

        return new main_listener($scanner, $config, null, $request ?? new fake_request());
    }

    public static function null_logger(): logger
    {
        return new class () extends logger {
            public function __construct()
            {
            }

            public function log(array $entry): void
            {
            }

            public function cleanup(int $retention_days, int $max_batches = 20): int
            {
                return 0;
            }

            public function table_name(): string
            {
                return 'noop';
            }
        };
    }

    /**
     * An HTTP client that answers with the given verdict and remembers the
     * request bodies it was handed.
     */
    public static function verdict_http(float $normalised_score, string $status): recording_http
    {
        // The SDK normalises with DEFAULT_SCORE_DENOMINATOR = 30.0, so send
        // a raw score it will map back onto the requested value.
        return new recording_http(new HttpResponse(200, (string) json_encode([
            'success' => true,
            'data' => [
                'status' => $status,
                'spam_score' => $normalised_score * 30.0,
                'symbols' => [],
            ],
        ]), []));
    }
}
