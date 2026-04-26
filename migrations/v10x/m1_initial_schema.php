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

class m1_initial_schema extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\\phpbb\\db\\migration\\data\\v330\\v330'];
    }

    /**
     * Tables present after migration → effectively_installed.
     */
    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'spamtroll_log');
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'spamtroll_log' => [
                    'COLUMNS' => [
                        'log_id' => ['UINT', null, 'auto_increment'],
                        'log_time' => ['TIMESTAMP', 0],
                        'content_type' => ['VCHAR:32', ''],
                        'ip_address' => ['VCHAR:45', ''],
                        'username' => ['VCHAR:255', ''],
                        'status' => ['VCHAR:16', ''],
                        'spam_score' => ['DECIMAL:5,4', 0],
                        'raw_score' => ['DECIMAL:8,4', 0],
                        'symbols' => ['VCHAR:1024', ''],
                        'action_taken' => ['VCHAR:16', ''],
                        'content_preview' => ['TEXT', ''],
                    ],
                    'PRIMARY_KEY' => 'log_id',
                    'KEYS' => [
                        'log_time' => ['INDEX', 'log_time'],
                        'content_type' => ['INDEX', 'content_type'],
                        'status' => ['INDEX', 'status'],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'spamtroll_log',
            ],
        ];
    }

    /**
     * Register the ACP module under the General tab.
     *
     * @return array<int, array<int, mixed>>
     */
    public function update_data()
    {
        return [
            ['module.add', [
                'acp',
                'ACP_CAT_DOT_MODS',
                'ACP_SPAMTROLL_TITLE',
            ]],
            ['module.add', [
                'acp',
                'ACP_SPAMTROLL_TITLE',
                [
                    'module_basename' => '\\spamtroll\\phpbb\\acp\\main_module',
                    'modes' => ['settings'],
                ],
            ]],
        ];
    }
}
