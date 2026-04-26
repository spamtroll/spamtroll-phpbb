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
    'ACP_SPAMTROLL_SETTINGS' => 'Spamtroll Settings',
    'ACP_SPAMTROLL_SETTINGS_EXPLAIN' => 'Configure how the Spamtroll API is used to scan posts, private messages and registrations on this board. All checks fail open: if the API is unreachable, content is allowed through and a warning is written to the PHP error log.',

    'SPAMTROLL_API_SETTINGS' => 'API connection',
    'SPAMTROLL_API_KEY' => 'API key',
    'SPAMTROLL_API_KEY_EXPLAIN' => 'Your Spamtroll API key. Sign up at https://spamtroll.io to get one.',
    'SPAMTROLL_API_URL' => 'API base URL',
    'SPAMTROLL_TIMEOUT' => 'HTTP timeout (seconds)',

    'SPAMTROLL_THRESHOLDS' => 'Score thresholds',
    'SPAMTROLL_SPAM_THRESHOLD' => 'Spam threshold',
    'SPAMTROLL_SPAM_THRESHOLD_EXPLAIN' => 'Normalised score (0.0–1.0) at and above which content is treated as spam and rejected. Default: 0.7.',
    'SPAMTROLL_SUSPICIOUS_THRESHOLD' => 'Suspicious threshold',
    'SPAMTROLL_SUSPICIOUS_THRESHOLD_EXPLAIN' => 'Normalised score (0.0–1.0) at and above which content is treated as suspicious. Must be lower than the spam threshold. Default: 0.4.',

    'SPAMTROLL_SOURCES' => 'What to scan',
    'SPAMTROLL_CHECK_POST' => 'Scan new forum posts',
    'SPAMTROLL_CHECK_PM' => 'Scan private messages',
    'SPAMTROLL_CHECK_REGISTRATION' => 'Scan new user registrations',

    'SPAMTROLL_LOGGING' => 'Logging',
    'SPAMTROLL_LOG_RETENTION_DAYS' => 'Log retention (days)',

    'SPAMTROLL_TEST_CONNECTION' => 'Test connection',
    'SPAMTROLL_TEST_OK' => 'Connection OK',
    'SPAMTROLL_TEST_FAIL' => 'Connection failed',

    'TEST_NO_KEY' => 'Save an API key first.',
    'TEST_OK' => 'The Spamtroll API responded successfully.',
    'TEST_FAIL' => 'Could not contact the Spamtroll API',
    'CONFIG_UPDATED' => 'Settings saved.',
    'FORM_INVALID' => 'Form security token is invalid. Please try again.',
]);
