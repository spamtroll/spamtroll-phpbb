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

namespace spamtroll\phpbb\migrations\v10x;

class m2_default_config extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\\spamtroll\\phpbb\\migrations\\v10x\\m1_initial_schema'];
    }

    public function effectively_installed()
    {
        return isset($this->config['spamtroll_api_url']);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function update_data()
    {
        return [
            ['config.add', ['spamtroll_api_key', '']],
            ['config.add', ['spamtroll_api_url', 'https://api.spamtroll.io/api/v1']],
            ['config.add', ['spamtroll_timeout', 5]],
            ['config.add', ['spamtroll_spam_threshold', '0.7']],
            ['config.add', ['spamtroll_suspicious_threshold', '0.4']],
            ['config.add', ['spamtroll_check_post', 1]],
            ['config.add', ['spamtroll_check_pm', 1]],
            ['config.add', ['spamtroll_check_registration', 1]],
            ['config.add', ['spamtroll_log_retention_days', 30]],
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function revert_data()
    {
        return [
            ['config.remove', ['spamtroll_api_key']],
            ['config.remove', ['spamtroll_api_url']],
            ['config.remove', ['spamtroll_timeout']],
            ['config.remove', ['spamtroll_spam_threshold']],
            ['config.remove', ['spamtroll_suspicious_threshold']],
            ['config.remove', ['spamtroll_check_post']],
            ['config.remove', ['spamtroll_check_pm']],
            ['config.remove', ['spamtroll_check_registration']],
            ['config.remove', ['spamtroll_log_retention_days']],
        ];
    }
}
