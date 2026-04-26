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

if (!defined('IN_PHPBB')) {
    exit;
}

if (empty($lang) || !is_array($lang)) {
    $lang = [];
}

$lang = array_merge($lang, [
    'SPAMTROLL_BLOCKED' => 'Your submission was blocked by the Spamtroll anti-spam filter. If you believe this is a mistake, please contact the board administrator.',
    'SPAMTROLL_QUEUED' => 'Your submission was flagged by the Spamtroll anti-spam filter and requires moderator review.',
]);
