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

namespace spamtroll\phpbb\service;

/**
 * Local audit log for every Spamtroll verdict.
 *
 * Stored in the board's own database so administrators can audit
 * decisions without depending on the Spamtroll dashboard. Pruned by
 * {@see \spamtroll\phpbb\cron\task\cleanup_logs} once a day.
 */
class logger
{
    public const TABLE_SUFFIX = 'spamtroll_log';

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    protected string $table;

    /**
     * @param \phpbb\db\driver\driver_interface $db
     */
    public function __construct($db, string $table_prefix)
    {
        $this->db = $db;
        $this->table = $table_prefix . self::TABLE_SUFFIX;
    }

    /**
     * @param array{
     *     content_type: string,
     *     ip_address?: ?string,
     *     username?: ?string,
     *     status: string,
     *     spam_score: float,
     *     raw_score?: float,
     *     symbols?: array<int, string>,
     *     action_taken: string,
     *     content_preview?: string
     * } $entry
     */
    public function log(array $entry): void
    {
        $row = [
            'log_time' => time(),
            'content_type' => self::truncate((string) $entry['content_type'], 32),
            'ip_address' => self::truncate((string) ($entry['ip_address'] ?? ''), 45),
            'username' => self::truncate((string) ($entry['username'] ?? ''), 255),
            'status' => self::truncate((string) $entry['status'], 16),
            'spam_score' => (float) $entry['spam_score'],
            'raw_score' => (float) ($entry['raw_score'] ?? 0.0),
            'symbols' => self::encode_symbols($entry['symbols'] ?? [], 1024),
            'action_taken' => self::truncate((string) $entry['action_taken'], 16),
            'content_preview' => self::truncate((string) ($entry['content_preview'] ?? ''), 500),
        ];

        $sql = 'INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', $row);
        $this->write($sql);
    }

    /**
     * Runs a statement with phpBB's fatal SQL error handler disabled.
     *
     * phpBB does not throw on SQL errors: \phpbb\db\driver\driver::sql_error()
     * ends in trigger_error($message, E_USER_ERROR) (driver.php:1028-1031),
     * which goes through msg_handler() into exit_handler() and kills the
     * request outright. No catch block can intercept that, so an audit-log
     * failure — a missing table after a half-applied migration, a column
     * length mismatch — would take the user's post down with it, which is
     * exactly the fail-closed behaviour the extension must not have.
     *
     * sql_return_on_error(true) makes sql_error() return the error array
     * instead (the `if (!$this->return_on_error)` guard at driver.php:989),
     * so the failure stays local to this method.
     */
    private function write(string $sql): void
    {
        $can_suppress = is_object($this->db) && method_exists($this->db, 'sql_return_on_error');

        if ($can_suppress) {
            $this->db->sql_return_on_error(true);
        }

        try {
            $this->db->sql_query($sql);
        } finally {
            if ($can_suppress) {
                $this->db->sql_return_on_error(false);
            }
        }
    }

    /**
     * Character-safe truncation.
     *
     * substr() cuts by bytes, so a multibyte character straddling the limit
     * is left as a broken byte sequence. MySQL in strict mode rejects that
     * on a utf8mb4 column with "Incorrect string value", which under phpBB
     * means a fatal (see {@see self::write()}). Polish, German or any
     * non-ASCII post hits this routinely.
     */
    private static function truncate(string $value, int $length): string
    {
        if ($value === '') {
            return '';
        }

        // phpBB always loads includes/utf/utf_tools.php (common.php:87).
        if (function_exists('utf8_substr')) {
            return (string) utf8_substr($value, 0, $length);
        }
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length, 'UTF-8');
        }

        // Last resort: byte cut, then drop a trailing partial sequence.
        $cut = substr($value, 0, $length);
        return (string) preg_replace('/(?:[\xC0-\xFF][\x80-\xBF]*)$/', '', $cut);
    }

    /**
     * JSON-encodes the symbol list, dropping whole symbols until it fits.
     *
     * Truncating the encoded JSON instead would store a syntactically
     * broken document and could still split an escape sequence.
     *
     * @param mixed $symbols
     */
    private static function encode_symbols($symbols, int $length): string
    {
        $list = is_array($symbols) ? array_values($symbols) : [];

        while ($list !== []) {
            $encoded = json_encode($list);
            if (is_string($encoded) && strlen($encoded) <= $length) {
                return $encoded;
            }
            array_pop($list);
        }

        return '[]';
    }

    /**
     * Number of rows removed per DELETE in {@see self::cleanup()}.
     */
    public const CLEANUP_BATCH = 500;

    /**
     * Drop entries older than the given number of days.
     *
     * Deletes in batches: the first run of the cron task on a board that
     * has been logging without retention holds a long row lock otherwise.
     * The ID round trip keeps this portable — MySQL supports
     * `DELETE ... LIMIT`, PostgreSQL does not.
     *
     * @param int $max_batches Safety valve so one cron run cannot spin for
     *                         ever on a huge backlog; the next run resumes.
     * @return int Number of rows removed.
     */
    public function cleanup(int $retention_days, int $max_batches = 20): int
    {
        if ($retention_days < 1) {
            return 0;
        }

        $cutoff = time() - ($retention_days * 86400);
        $removed = 0;

        for ($batch = 0; $batch < $max_batches; $batch++) {
            $sql = 'SELECT log_id FROM ' . $this->table . ' WHERE log_time < ' . (int) $cutoff;
            $result = $this->db->sql_query_limit($sql, self::CLEANUP_BATCH);

            $ids = [];
            while ($row = $this->db->sql_fetchrow($result)) {
                $ids[] = (int) $row['log_id'];
            }
            $this->db->sql_freeresult($result);

            if ($ids === []) {
                break;
            }

            $this->db->sql_query(
                'DELETE FROM ' . $this->table . ' WHERE ' . $this->db->sql_in_set('log_id', $ids)
            );
            $removed += (int) $this->db->sql_affectedrows();

            if (count($ids) < self::CLEANUP_BATCH) {
                break;
            }
        }

        return $removed;
    }

    public function table_name(): string
    {
        return $this->table;
    }
}
