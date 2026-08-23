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

/**
 * Stand-in for phpBB's parse_message, as it looks at
 * core.ucp_pm_compose_modify_parse_before: `message` still holds the raw
 * request value assigned at includes/ucp/ucp_pm_compose.php:833 (hence
 * HTML-escaped), and `warn_msg` is whatever attachment handling left
 * behind.
 */
final class fake_message_parser
{
    public string $message = '';

    /** @var array<int, string> */
    public array $warn_msg = [];

    public function __construct(string $plain_message = '')
    {
        // ucp_pm_compose.php:833 fills this from
        // $request->variable('message', '', true), which escapes.
        $this->message = htmlspecialchars($plain_message, ENT_COMPAT, 'UTF-8');
    }
}
