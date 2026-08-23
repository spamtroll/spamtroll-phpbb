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
use spamtroll\phpbb\tests\support\fake_message_parser;
use spamtroll\phpbb\tests\support\fake_request;
use spamtroll\phpbb\tests\support\listener_builder;

/**
 * Private message scan path (audit K3).
 *
 * @covers \spamtroll\phpbb\event\main_listener
 */
final class pm_test extends TestCase
{
    public function test_subscribes_to_a_pm_event_that_exists(): void
    {
        $events = main_listener::getSubscribedEvents();

        self::assertArrayHasKey('core.ucp_pm_compose_modify_parse_before', $events);
        self::assertSame('check_pm', $events['core.ucp_pm_compose_modify_parse_before']);

        // There is no such event anywhere in phpBB 3.3.x, so the handler
        // was simply never called.
        self::assertArrayNotHasKey('core.ucp_pm_compose_modify_parsed_text', $events);
    }

    public function test_blocked_pm_appends_to_the_error_array(): void
    {
        $http = listener_builder::verdict_http(0.95, 'blocked');
        $listener = listener_builder::build($http);

        $result = event_harness::trigger(
            event_harness::ucp_pm_compose_modify_parse_before(),
            static fn ($event) => $listener->check_pm($event)
        );

        self::assertSame(1, $http->call_count());
        self::assertSame('Buy cheap pills now!', $http->payload()['content'] ?? null);
        self::assertCount(1, $result['error']);
        self::assertStringContainsString('blocked as spam', $result['error'][0]);
    }

    public function test_verdict_does_not_go_into_warn_msg(): void
    {
        // ucp_pm_compose.php:876-888 drains $message_parser->warn_msg into
        // $error *before* either parse_* event, so anything written there
        // from a listener is discarded.
        $parser = new fake_message_parser('Buy cheap pills now!');
        $listener = listener_builder::build(listener_builder::verdict_http(0.95, 'blocked'));

        event_harness::trigger(
            event_harness::ucp_pm_compose_modify_parse_before(['message_parser' => $parser]),
            static fn ($event) => $listener->check_pm($event)
        );

        self::assertSame([], $parser->warn_msg);
    }

    public function test_message_is_decoded_before_being_scanned(): void
    {
        $http = listener_builder::verdict_http(0.0, 'safe');
        $listener = listener_builder::build($http);

        event_harness::trigger(
            event_harness::ucp_pm_compose_modify_parse_before([
                'message_parser' => new fake_message_parser('Tom & Jerry say "hi"'),
            ]),
            static fn ($event) => $listener->check_pm($event)
        );

        self::assertSame('Tom & Jerry say "hi"', $http->payload()['content'] ?? null);
    }

    public function test_falls_back_to_the_request_field_without_a_usable_parser(): void
    {
        $http = listener_builder::verdict_http(0.0, 'safe');
        $listener = listener_builder::build($http, new fake_request([
            'message' => 'from the request',
        ]));

        event_harness::trigger(
            event_harness::ucp_pm_compose_modify_parse_before(['message_parser' => null]),
            static fn ($event) => $listener->check_pm($event)
        );

        self::assertSame('from the request', $http->payload()['content'] ?? null);
    }

    public function test_safe_pm_leaves_the_error_array_alone(): void
    {
        $listener = listener_builder::build(listener_builder::verdict_http(0.05, 'safe'));

        $result = event_harness::trigger(
            event_harness::ucp_pm_compose_modify_parse_before(),
            static fn ($event) => $listener->check_pm($event)
        );

        self::assertSame([], $result['error']);
    }

    public function test_preview_is_not_scanned(): void
    {
        $http = listener_builder::verdict_http(0.95, 'blocked');
        $listener = listener_builder::build($http);

        $result = event_harness::trigger(
            event_harness::ucp_pm_compose_modify_parse_before([
                'submit' => false,
                'preview' => true,
            ]),
            static fn ($event) => $listener->check_pm($event)
        );

        self::assertSame(0, $http->call_count());
        self::assertSame([], $result['error']);
    }

    public function test_disabled_check_is_a_no_op(): void
    {
        $http = listener_builder::verdict_http(0.95, 'blocked');
        $listener = listener_builder::build($http, null, ['spamtroll_check_pm' => 0]);

        $result = event_harness::trigger(
            event_harness::ucp_pm_compose_modify_parse_before(),
            static fn ($event) => $listener->check_pm($event)
        );

        self::assertSame(0, $http->call_count());
        self::assertSame([], $result['error']);
    }
}
