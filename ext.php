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

namespace spamtroll\phpbb;

/**
 * Extension lifecycle hook.
 *
 * Default implementation in \phpbb\extension\base is sufficient: phpBB will
 * auto-discover services.yml, run migrations and register language files.
 * The class only exists so phpBB recognises this directory as an
 * extension and so we can plug in custom enable/disable steps later.
 */
class ext extends \phpbb\extension\base
{
}
