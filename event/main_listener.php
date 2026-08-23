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

namespace spamtroll\phpbb\event;

use spamtroll\phpbb\service\scan_result;
use spamtroll\phpbb\service\scanner;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Wires the Spamtroll scanner into phpBB's posting / PM / registration
 * lifecycles via the core event dispatcher.
 *
 * The handlers all follow the same pattern:
 *  1. Bail out cheaply if the relevant per-source toggle is off, or if the
 *     form was not actually submitted (preview / refresh must not burn a
 *     scan from the daily quota, and must not raise a blocking error).
 *  2. Pull the user-supplied content + identity from the event payload.
 *  3. Ask the scanner for a verdict (which is fail-open by construction).
 *  4. On a "block" verdict, append a localised string to the event's
 *     `error` array — phpBB aborts the submission on a non-empty `error`.
 *  5. On an "allow" verdict, leave the event untouched.
 *
 * Only keys that phpBB itself put into the event survive the round trip:
 * \phpbb\event\dispatcher::trigger_event() returns
 * get_data_filtered(array_keys($data)), which is an array_intersect_key
 * against the original variable list (phpbb/event/dispatcher.php:47,
 * phpbb/event/data.php:44). Every event subscribed to below therefore has
 * to expose `error` in its own @var list — all three do.
 */
class main_listener implements EventSubscriberInterface
{
    protected scanner $scanner;

    /** @var \phpbb\config\config */
    protected $config;

    /** @var \phpbb\user */
    protected $user;

    /** @var \phpbb\request\request_interface */
    protected $request;

    /**
     * @param \phpbb\config\config $config
     * @param \phpbb\user $user
     * @param \phpbb\request\request_interface $request
     */
    public function __construct(scanner $scanner, $config, $user, $request)
    {
        $this->scanner = $scanner;
        $this->config = $config;
        $this->user = $user;
        $this->request = $request;
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // includes/ucp/ucp_register.php:329-338
            // vars: submit, data, cp_data, error — fires while the form is
            // still rejectable, unlike core.user_add_modify_data which runs
            // inside user_add() (includes/functions_user.php:290, vars:
            // user_row, cp_data, sql_ary, notifications_data — no `error`).
            'core.ucp_register_data_after' => 'check_registration',

            // posting.php:1403-1428
            // vars: post_data, poll, mode, post_id, topic_id, forum_id,
            // submit, error.
            'core.posting_modify_submission_errors' => 'check_post',

            // includes/ucp/ucp_pm_compose.php:845-868
            // vars: enable_bbcode, enable_smilies, enable_urls, enable_sig,
            // subject, message_parser, submit, preview, error.
            // `..._modify_parsed_text` (previously subscribed here) does not
            // exist anywhere in phpBB 3.3.x.
            'core.ucp_pm_compose_modify_parse_before' => 'check_pm',
        ];
    }

    /**
     * Registration check, on core.ucp_register_data_after.
     *
     * @param \phpbb\event\data $event
     */
    public function check_registration($event): void
    {
        if (!$this->bool_config('spamtroll_check_registration', true)) {
            return;
        }

        if (!$this->is_submitted($event)) {
            return;
        }

        $data = $event['data'];
        if (!is_array($data)) {
            return;
        }

        $username = $this->plain_text($data['username'] ?? '');
        $email = $this->plain_text($data['email'] ?? '');
        if ($username === '' && $email === '') {
            return;
        }

        $result = $this->scanner->check_registration($username, $email, $this->client_ip());
        if ($result->should_block()) {
            $this->add_error($event, $result);
        }
    }

    /**
     * Post check, on core.posting_modify_submission_errors.
     *
     * @param \phpbb\event\data $event
     */
    public function check_post($event): void
    {
        if (!$this->bool_config('spamtroll_check_post', true)) {
            return;
        }

        if (!$this->is_submitted($event)) {
            return;
        }

        // The event's `post_data` carries no message: posting.php:642-645
        // rebinds it into the parser (`$message_parser->message =
        // &$post_data['post_text']; unset($post_data['post_text']);`) and
        // only writes it back at posting.php:1807, long after this event.
        // The parser object is not in the variable list either, so we read
        // the same request field phpBB itself reads at posting.php:941.
        $content = $this->request_message();
        if ($content === '') {
            return;
        }

        $username = $this->current_username();
        $result = $this->scanner->check_post($content, $username, $this->client_ip());
        if ($this->should_intervene($result)) {
            $this->add_error($event, $result);
        }
    }

    /**
     * Private message check, on core.ucp_pm_compose_modify_parse_before.
     *
     * Deliberately the *before* variant: at `..._parse_after` the message
     * has already gone through parse_message::parse(), which replaces
     * $message_parser->message with the s9e TextFormatter XML
     * representation (includes/message_parser.php:1251) — useless as
     * scanner input. `error` is present in both variants, and
     * ucp_pm_compose.php:947 (`if (!count($error) && $submit)`) is what
     * actually stops the PM.
     *
     * @param \phpbb\event\data $event
     */
    public function check_pm($event): void
    {
        if (!$this->bool_config('spamtroll_check_pm', true)) {
            return;
        }

        if (!$this->is_submitted($event)) {
            return;
        }

        $message_parser = $event['message_parser'];
        $content = '';
        if (is_object($message_parser) && property_exists($message_parser, 'message') && is_string($message_parser->message)) {
            $content = $this->plain_text($message_parser->message);
        }
        if ($content === '') {
            // Fall back to the request field the parser was filled from
            // (ucp_pm_compose.php:833) if the parser is not usable.
            $content = $this->request_message();
        }
        if ($content === '') {
            return;
        }

        $username = $this->current_username();
        $result = $this->scanner->check_pm($content, $username, $this->client_ip());
        if ($result->should_block()) {
            $this->add_error($event, $result);
        }
    }

    private function should_intervene(scan_result $result): bool
    {
        // For posts we currently surface both block and moderate as a
        // hard error. Routing "moderate" into phpBB's approval queue
        // instead is tracked separately (audit S3).
        return $result->should_block() || $result->should_moderate();
    }

    /**
     * True unless the event explicitly says the form was not submitted.
     * All three subscribed events expose `submit`; the registration one
     * only ever fires under `if ($submit)` anyway.
     *
     * @param \phpbb\event\data $event
     */
    private function is_submitted($event): bool
    {
        $submit = $event['submit'];
        return $submit === null || (bool) $submit;
    }

    /**
     * Appends a localised error string to the event's `error` array.
     *
     * Uses offsetSet on the existing `error` key rather than set_data()
     * with a fresh key, because anything not already in the event's
     * variable list is discarded by get_data_filtered().
     *
     * @param \phpbb\event\data $event
     */
    private function add_error($event, scan_result $result): void
    {
        $error = $event['error'];
        if (!is_array($error)) {
            $error = [];
        }
        $error[] = $this->translate(
            $result->should_block() ? 'SPAMTROLL_BLOCKED' : 'SPAMTROLL_QUEUED'
        );
        $event['error'] = $error;
    }

    /**
     * The message field as submitted, decoded back to plain text.
     *
     * phpBB's request layer htmlspecialchars()es every string variable
     * (phpbb/request/type_cast_helper.php:46), so the raw value contains
     * entities the scanner has no business seeing.
     */
    private function request_message(): string
    {
        if (!is_object($this->request) || !method_exists($this->request, 'variable')) {
            return '';
        }
        $message = $this->request->variable('message', '', true);
        return is_string($message) ? $this->plain_text($message) : '';
    }

    /**
     * @param mixed $value
     */
    private function plain_text($value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }
        // Same decoding phpBB applies before parsing a message
        // (includes/message_parser.php:1251).
        return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    }

    private function bool_config(string $key, bool $default): bool
    {
        $value = $this->config[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        return (bool) (int) $value;
    }

    private function client_ip(): ?string
    {
        if (!is_object($this->request) || !method_exists($this->request, 'server')) {
            return null;
        }
        $ip = (string) $this->request->server('REMOTE_ADDR', '');
        return $ip !== '' ? $ip : null;
    }

    private function current_username(): ?string
    {
        if (!is_object($this->user) || !property_exists($this->user, 'data')) {
            return null;
        }
        $data = $this->user->data;
        if (!is_array($data)) {
            return null;
        }
        return isset($data['username']) && is_string($data['username']) ? $data['username'] : null;
    }

    private function translate(string $key): string
    {
        if (is_object($this->user) && method_exists($this->user, 'lang')) {
            $translated = $this->user->lang($key);
            if (is_string($translated) && $translated !== '' && $translated !== $key) {
                return $translated;
            }
        }
        // Sane fallback for the case where the language file isn't loaded
        // (unit tests, partially booted phpBB, etc.).
        return $key === 'SPAMTROLL_BLOCKED'
            ? 'Spamtroll: this submission was blocked as spam.'
            : 'Spamtroll: this submission was flagged for review.';
    }
}
