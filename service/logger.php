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
        $symbols = $entry['symbols'] ?? [];
        $preview = $entry['content_preview'] ?? '';
        if (strlen($preview) > 500) {
            $preview = substr($preview, 0, 500);
        }

        $row = [
            'log_time' => time(),
            'content_type' => substr($entry['content_type'], 0, 32),
            'ip_address' => substr((string) ($entry['ip_address'] ?? ''), 0, 45),
            'username' => substr((string) ($entry['username'] ?? ''), 0, 255),
            'status' => substr($entry['status'], 0, 16),
            'spam_score' => (float) $entry['spam_score'],
            'raw_score' => (float) ($entry['raw_score'] ?? 0.0),
            'symbols' => substr((string) json_encode(array_values($symbols)), 0, 1024),
            'action_taken' => substr($entry['action_taken'], 0, 16),
            'content_preview' => $preview,
        ];

        $sql = 'INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', $row);
        $this->db->sql_query($sql);
    }

    /**
     * Drop entries older than the given number of days.
     *
     * @return int Number of rows removed.
     */
    public function cleanup(int $retention_days): int
    {
        if ($retention_days < 1) {
            return 0;
        }

        $cutoff = time() - ($retention_days * 86400);
        $sql = 'DELETE FROM ' . $this->table . ' WHERE log_time < ' . (int) $cutoff;
        $this->db->sql_query($sql);

        return (int) $this->db->sql_affectedrows();
    }

    public function table_name(): string
    {
        return $this->table;
    }
}
