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

namespace spamtroll\phpbb\tests\logger;

use PHPUnit\Framework\TestCase;
use spamtroll\phpbb\service\logger;
use spamtroll\phpbb\tests\support\fake_db;

/**
 * Audit-log persistence (audit W1, N1, W4).
 *
 * @covers \spamtroll\phpbb\service\logger
 */
final class logger_test extends TestCase
{
    public function test_content_preview_is_cut_on_character_boundaries(): void
    {
        $db = new fake_db();
        $log = new logger($db, 'phpbb_');

        // 600 Polish characters, 2 bytes each: a byte cut at 500 lands in
        // the middle of the 251st one and produces an invalid sequence,
        // which MySQL in strict mode rejects on a utf8mb4 column.
        $log->log(self::entry(['content_preview' => str_repeat('ą', 600)]));

        $preview = $db->rows[0]['content_preview'];
        self::assertSame(500, mb_strlen($preview, 'UTF-8'));
        self::assertSame($preview, mb_convert_encoding($preview, 'UTF-8', 'UTF-8'));
        self::assertSame(1, preg_match('//u', $preview), 'preview must be valid UTF-8');
    }

    public function test_username_is_cut_on_character_boundaries(): void
    {
        $db = new fake_db();
        $log = new logger($db, 'phpbb_');

        $log->log(self::entry(['username' => str_repeat('ż', 300)]));

        $username = $db->rows[0]['username'];
        self::assertSame(255, mb_strlen($username, 'UTF-8'));
        self::assertSame(1, preg_match('//u', $username));
    }

    public function test_short_values_are_left_alone(): void
    {
        $db = new fake_db();
        $log = new logger($db, 'phpbb_');

        $log->log(self::entry(['content_preview' => 'Zażółć gęślą jaźń']));

        self::assertSame('Zażółć gęślą jaźń', $db->rows[0]['content_preview']);
    }

    public function test_symbols_column_always_holds_valid_json(): void
    {
        $db = new fake_db();
        $log = new logger($db, 'phpbb_');

        // 60 × ~20 bytes comfortably overflows the 1024-char column.
        $symbols = [];
        for ($i = 0; $i < 60; $i++) {
            $symbols[] = 'SYMBOL_NAME_' . str_pad((string) $i, 6, '0', STR_PAD_LEFT);
        }
        $log->log(self::entry(['symbols' => $symbols]));

        $encoded = $db->rows[0]['symbols'];
        self::assertLessThanOrEqual(1024, strlen($encoded));
        $decoded = json_decode($encoded, true);
        self::assertIsArray($decoded, 'symbols must stay parseable JSON');
        self::assertNotEmpty($decoded);
        self::assertSame('SYMBOL_NAME_000000', $decoded[0]);
    }

    public function test_insert_runs_with_the_fatal_sql_handler_disabled(): void
    {
        $db = new fake_db();
        $log = new logger($db, 'phpbb_');

        $log->log(self::entry());

        // On a real board a failing INSERT would otherwise reach
        // trigger_error(E_USER_ERROR) → exit_handler() and take the post
        // down with it (phpbb/db/driver/driver.php:989-1031).
        self::assertSame([true, false], $db->return_on_error_calls);
    }

    public function test_a_failing_insert_does_not_kill_the_request(): void
    {
        $db = new fake_db();
        $db->fail_next_query = true;
        $log = new logger($db, 'phpbb_');

        $log->log(self::entry());

        self::assertSame([true, false], $db->return_on_error_calls);
        self::assertFalse($db->return_on_error, 'the flag must be restored');
    }

    public function test_cleanup_deletes_in_batches(): void
    {
        $db = new fake_db();
        $db->select_ids = range(1, logger::CLEANUP_BATCH + 20);
        $log = new logger($db, 'phpbb_');

        $removed = $log->cleanup(30);

        self::assertSame(logger::CLEANUP_BATCH + 20, $removed);
        $deletes = array_values(array_filter(
            $db->queries,
            static fn (string $sql): bool => str_starts_with($sql, 'DELETE')
        ));
        self::assertCount(2, $deletes);
        self::assertStringContainsString('log_id IN (', $deletes[0]);
    }

    public function test_cleanup_is_a_no_op_for_a_zero_retention_window(): void
    {
        $db = new fake_db();
        $log = new logger($db, 'phpbb_');

        self::assertSame(0, $log->cleanup(0));
        self::assertSame([], $db->queries);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function entry(array $overrides = []): array
    {
        return array_merge([
            'content_type' => 'post',
            'ip_address' => '198.51.100.7',
            'username' => 'someone',
            'status' => 'blocked',
            'spam_score' => 0.91,
            'raw_score' => 27.3,
            'symbols' => ['BAYES_SPAM'],
            'action_taken' => 'block',
            'content_preview' => 'hello',
        ], $overrides);
    }
}
