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
 * Reproduces \phpbb\event\dispatcher::trigger_event() end to end.
 *
 * A test that pokes at the \phpbb\event\data object directly proves
 * nothing: phpBB never hands that object back to the calling code. It does
 *
 *     $event = new \phpbb\event\data($data);
 *     $this->dispatch($eventName, $event);
 *     return $event->get_data_filtered(array_keys($data));
 *
 * (phpbb/event/dispatcher.php:43-48), and the caller then extract()s the
 * returned array over its local variables. Keys the listener invented are
 * dropped by the array_intersect_key in get_data_filtered().
 *
 * So the harness takes the event's real variable list, runs the handler,
 * and returns only what phpBB would actually have seen.
 */
final class event_harness
{
    /**
     * @param array<string, mixed> $vars The event's documented @var list,
     *                                   with realistic values.
     * @param callable(\phpbb\event\data): void $handler
     * @return array<string, mixed> What phpBB extract()s back into scope.
     */
    public static function trigger(array $vars, callable $handler): array
    {
        $event = new \phpbb\event\data($vars);
        $handler($event);

        return $event->get_data_filtered(array_keys($vars));
    }

    /**
     * The variable list of core.ucp_register_data_after.
     * includes/ucp/ucp_register.php:329-338.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function ucp_register_data_after(array $overrides = []): array
    {
        return array_merge([
            'submit' => true,
            'data' => [
                'username' => 'spammer',
                'new_password' => 'hunter22',
                'password_confirm' => 'hunter22',
                'email' => 'spammer@bad.tld',
                'lang' => 'en',
                'tz' => 'UTC',
            ],
            'cp_data' => [],
            'error' => [],
        ], $overrides);
    }

    /**
     * The variable list of core.posting_modify_submission_errors.
     * posting.php:1403-1428.
     *
     * Note what post_data does *not* contain: posting.php:642-645 moves
     * `post_text` into the message parser and unsets it, and only writes
     * it back at :1807 — long after this event. There is no `message` key
     * either, in any code path.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function posting_modify_submission_errors(array $overrides = []): array
    {
        return array_merge([
            'post_data' => [
                'topic_id' => 12,
                'forum_id' => 3,
                'post_subject' => 'Great offer',
                'username' => '',
                'topic_type' => 0,
                'enable_bbcode' => true,
                'enable_smilies' => true,
                'enable_urls' => true,
            ],
            'poll' => [],
            'mode' => 'reply',
            'post_id' => 0,
            'topic_id' => 12,
            'forum_id' => 3,
            'submit' => true,
            'error' => [],
        ], $overrides);
    }

    /**
     * The variable list of core.ucp_pm_compose_modify_parse_before.
     * includes/ucp/ucp_pm_compose.php:845-868.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function ucp_pm_compose_modify_parse_before(array $overrides = []): array
    {
        return array_merge([
            'enable_bbcode' => true,
            'enable_smilies' => true,
            'enable_urls' => 1,
            'enable_sig' => false,
            'subject' => 'Hi',
            'message_parser' => new fake_message_parser('Buy cheap pills now!'),
            'submit' => true,
            'preview' => false,
            'error' => [],
        ], $overrides);
    }
}
