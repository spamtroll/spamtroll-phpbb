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

namespace spamtroll\phpbb\tests\listener;

use PHPUnit\Framework\TestCase;
use spamtroll\phpbb\event\main_listener;
use spamtroll\phpbb\tests\support\event_harness;
use spamtroll\phpbb\tests\support\fake_request;
use spamtroll\phpbb\tests\support\listener_builder;

/**
 * Post scan path (audit K2).
 *
 * @covers \spamtroll\phpbb\event\main_listener
 */
final class post_test extends TestCase
{
    public function test_subscribes_post_scan_to_the_submission_errors_event(): void
    {
        $events = main_listener::getSubscribedEvents();

        self::assertArrayHasKey('core.posting_modify_submission_errors', $events);
        self::assertSame('check_post', $events['core.posting_modify_submission_errors']);
    }

    public function test_blocked_post_is_scanned_even_though_post_data_carries_no_message(): void
    {
        $http = listener_builder::verdict_http(0.95, 'blocked');
        $vars = event_harness::posting_modify_submission_errors();

        // Guards the premise of the whole fix: posting.php strips the text
        // out of post_data (:642-645) and does not put it back until :1807.
        self::assertArrayNotHasKey('message', $vars['post_data']);
        self::assertArrayNotHasKey('post_text', $vars['post_data']);

        $listener = listener_builder::build($http, new fake_request([
            'message' => 'Buy cheap pills now!',
        ]));

        $result = event_harness::trigger(
            $vars,
            static fn ($event) => $listener->check_post($event)
        );

        self::assertSame(1, $http->call_count());
        self::assertSame('Buy cheap pills now!', $http->payload()['content'] ?? null);
        self::assertSame('forum', $http->payload()['source'] ?? null);
        self::assertCount(1, $result['error']);
        self::assertStringContainsString('blocked as spam', $result['error'][0]);
    }

    public function test_suspicious_post_is_also_surfaced_as_an_error(): void
    {
        $listener = listener_builder::build(
            listener_builder::verdict_http(0.5, 'suspicious'),
            new fake_request(['message' => 'Borderline content'])
        );

        $result = event_harness::trigger(
            event_harness::posting_modify_submission_errors(),
            static fn ($event) => $listener->check_post($event)
        );

        self::assertCount(1, $result['error']);
        self::assertStringContainsString('flagged for review', $result['error'][0]);
    }

    public function test_safe_post_leaves_the_error_array_alone(): void
    {
        $listener = listener_builder::build(
            listener_builder::verdict_http(0.05, 'safe'),
            new fake_request(['message' => 'Regular friendly post'])
        );

        $result = event_harness::trigger(
            event_harness::posting_modify_submission_errors(),
            static fn ($event) => $listener->check_post($event)
        );

        self::assertSame([], $result['error']);
    }

    public function test_html_entities_are_decoded_before_the_content_is_sent(): void
    {
        // phpBB's request layer htmlspecialchars()es every string variable
        // (phpbb/request/type_cast_helper.php:46); the scanner must not be
        // asked to classify "&quot;" and "&amp;".
        $http = listener_builder::verdict_http(0.0, 'safe');
        $listener = listener_builder::build($http, new fake_request([
            'message' => 'Tom & Jerry <b>say</b> "hello"',
        ]));

        event_harness::trigger(
            event_harness::posting_modify_submission_errors(),
            static fn ($event) => $listener->check_post($event)
        );

        self::assertSame('Tom & Jerry <b>say</b> "hello"', $http->payload()['content'] ?? null);
    }

    public function test_client_ip_is_forwarded(): void
    {
        $http = listener_builder::verdict_http(0.0, 'safe');
        $listener = listener_builder::build($http, new fake_request(
            ['message' => 'hello'],
            ['REMOTE_ADDR' => '203.0.113.9']
        ));

        event_harness::trigger(
            event_harness::posting_modify_submission_errors(),
            static fn ($event) => $listener->check_post($event)
        );

        self::assertSame('203.0.113.9', $http->payload()['ip_address'] ?? null);
    }

    public function test_preview_is_not_scanned(): void
    {
        // posting.php:937 runs the whole block — including the event at
        // :1428 — for submit, preview and refresh alike. Scanning a
        // preview would burn a scan from the daily quota and, worse, hand
        // the user a blocking error on a button that does not post.
        $http = listener_builder::verdict_http(0.95, 'blocked');
        $listener = listener_builder::build($http, new fake_request([
            'message' => 'Buy cheap pills now!',
        ]));

        $result = event_harness::trigger(
            event_harness::posting_modify_submission_errors(['submit' => false]),
            static fn ($event) => $listener->check_post($event)
        );

        self::assertSame(0, $http->call_count());
        self::assertSame([], $result['error']);
    }

    public function test_empty_message_short_circuits(): void
    {
        $http = listener_builder::verdict_http(0.95, 'blocked');
        $listener = listener_builder::build($http, new fake_request(['message' => '   ']));

        $result = event_harness::trigger(
            event_harness::posting_modify_submission_errors(),
            static fn ($event) => $listener->check_post($event)
        );

        self::assertSame(0, $http->call_count());
        self::assertSame([], $result['error']);
    }

    public function test_disabled_check_is_a_no_op(): void
    {
        $http = listener_builder::verdict_http(0.95, 'blocked');
        $listener = listener_builder::build(
            $http,
            new fake_request(['message' => 'Buy cheap pills now!']),
            ['spamtroll_check_post' => 0]
        );

        $result = event_harness::trigger(
            event_harness::posting_modify_submission_errors(),
            static fn ($event) => $listener->check_post($event)
        );

        self::assertSame(0, $http->call_count());
        self::assertSame([], $result['error']);
    }
}
