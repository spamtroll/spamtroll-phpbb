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

namespace spamtroll\phpbb\acp;

class main_info
{
    /**
     * @return array<string, mixed>
     */
    public function module(): array
    {
        return [
            'filename' => '\\spamtroll\\phpbb\\acp\\main_module',
            'title' => 'ACP_SPAMTROLL_TITLE',
            'modes' => [
                'settings' => [
                    'title' => 'ACP_SPAMTROLL_SETTINGS',
                    'auth' => 'ext_spamtroll/phpbb && acl_a_board',
                    'cat' => ['ACP_SPAMTROLL_TITLE'],
                ],
            ],
        ];
    }
}
