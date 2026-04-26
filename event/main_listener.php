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
 *  1. Bail out cheaply if the relevant per-source toggle is off.
 *  2. Pull the user-supplied content + identity from the event payload.
 *  3. Ask the scanner for a verdict (which is fail-open by construction).
 *  4. On a "block" verdict, push a localised error into the event's
 *     error/warning array — phpBB does the rest.
 *  5. On a "moderate" or "allow" verdict, leave the event untouched.
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
            'core.user_add_modify_data' => 'check_registration',
            'core.posting_modify_submission_errors' => 'check_post',
            'core.ucp_pm_compose_modify_parsed_text' => 'check_pm',
        ];
    }

    /**
     * @param \phpbb\event\data $event
     */
    public function check_registration($event): void
    {
        if (!$this->bool_config('spamtroll_check_registration', true)) {
            return;
        }

        $user_row = $event['user_row'];
        if (!is_array($user_row)) {
            return;
        }

        $username = isset($user_row['username']) && is_string($user_row['username']) ? $user_row['username'] : '';
        $email = isset($user_row['user_email']) && is_string($user_row['user_email']) ? $user_row['user_email'] : '';
        if ($username === '' && $email === '') {
            return;
        }

        $result = $this->scanner->check_registration($username, $email, $this->client_ip());
        if ($result->should_block()) {
            $cdata = $event->get_data();
            $error = isset($cdata['error']) && is_array($cdata['error']) ? $cdata['error'] : [];
            $error[] = $this->translate('SPAMTROLL_BLOCKED');
            $cdata['error'] = $error;
            $event->set_data($cdata);
        }
    }

    /**
     * @param \phpbb\event\data $event
     */
    public function check_post($event): void
    {
        if (!$this->bool_config('spamtroll_check_post', true)) {
            return;
        }

        $post_data = $event['post_data'];
        $content = '';
        if (is_array($post_data) && isset($post_data['message']) && is_string($post_data['message'])) {
            $content = $post_data['message'];
        }
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
     * @param \phpbb\event\data $event
     */
    public function check_pm($event): void
    {
        if (!$this->bool_config('spamtroll_check_pm', true)) {
            return;
        }

        $message_parser = $event['message_parser'];
        $content = '';
        if (is_object($message_parser) && property_exists($message_parser, 'message') && is_string($message_parser->message)) {
            $content = $message_parser->message;
        }
        if ($content === '') {
            return;
        }

        $username = $this->current_username();
        $result = $this->scanner->check_pm($content, $username, $this->client_ip());
        if ($result->should_block()) {
            $cdata = $event->get_data();
            // phpBB exposes the parser; pushing into ->warn_msg lets the
            // PM compose UI render the error without crashing the request.
            if (isset($cdata['message_parser']) && is_object($cdata['message_parser']) && property_exists($cdata['message_parser'], 'warn_msg')) {
                $warn = is_array($cdata['message_parser']->warn_msg) ? $cdata['message_parser']->warn_msg : [];
                $warn[] = $this->translate('SPAMTROLL_BLOCKED');
                $cdata['message_parser']->warn_msg = $warn;
                $event->set_data($cdata);
            }
        }
    }

    private function should_intervene(scan_result $result): bool
    {
        // For posts we currently surface both block and moderate as a
        // hard error — phpBB doesn't have a built-in "queue this post for
        // approval" hook on the submission-error event, and silently
        // letting suspicious content through would defeat the point.
        return $result->should_block() || $result->should_moderate();
    }

    /**
     * @param \phpbb\event\data $event
     */
    private function add_error($event, scan_result $result): void
    {
        $cdata = $event->get_data();
        $error = isset($cdata['error']) && is_array($cdata['error']) ? $cdata['error'] : [];
        $error[] = $this->translate(
            $result->should_block() ? 'SPAMTROLL_BLOCKED' : 'SPAMTROLL_QUEUED'
        );
        $cdata['error'] = $error;
        $event->set_data($cdata);
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
