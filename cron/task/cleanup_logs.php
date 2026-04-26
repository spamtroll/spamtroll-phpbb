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

namespace spamtroll\phpbb\cron\task;

use spamtroll\phpbb\service\logger;

/**
 * Daily cron task that prunes the local Spamtroll scan log according to
 * the configured retention window. Without this, busy boards would grow
 * the table unbounded.
 */
class cleanup_logs extends \phpbb\cron\task\base
{
    /** @var \phpbb\config\config */
    protected $config;

    protected logger $logger;

    /**
     * @param \phpbb\config\config $config
     */
    public function __construct($config, logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function run()
    {
        $retention = (int) ($this->config['spamtroll_log_retention_days'] ?? 30);
        if ($retention < 1) {
            $retention = 30;
        }
        $this->logger->cleanup($retention);

        $this->config->set('spamtroll_last_log_cleanup', (string) time());
    }

    public function should_run()
    {
        $last = (int) ($this->config['spamtroll_last_log_cleanup'] ?? 0);
        return ($last + 86400) < time();
    }
}
