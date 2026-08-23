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
use spamtroll\phpbb\tests\support\listener_builder;

/**
 * Registration scan path (audit K1).
 *
 * @covers \spamtroll\phpbb\event\main_listener
 */
final class registration_test extends TestCase
{
    public function test_subscribes_to_an_event_that_can_still_reject_the_form(): void
    {
        $events = main_listener::getSubscribedEvents();

        self::assertArrayHasKey('core.ucp_register_data_after', $events);
        self::assertSame('check_registration', $events['core.ucp_register_data_after']);

        // core.user_add_modify_data fires from inside user_add()
        // (includes/functions_user.php:290) with vars user_row, cp_data,
        // sql_ary, notifications_data. There is no `error` to write to,
        // and registration has already been accepted by then.
        self::assertArrayNotHasKey('core.user_add_modify_data', $events);
    }

    public function test_blocked_registration_error_survives_event_data_filtering(): void
    {
        $http = listener_builder::verdict_http(0.95, 'blocked');
        $listener = listener_builder::build($http);

        $result = event_harness::trigger(
            event_harness::ucp_register_data_after(),
            static fn ($event) => $listener->check_registration($event)
        );

        self::assertSame(1, $http->call_count());
        self::assertIsArray($result['error']);
        self::assertCount(1, $result['error']);
        // No \phpbb\user is wired in for unit tests, so the listener falls
        // back to its built-in English copy.
        self::assertStringContainsString('blocked as spam', $result['error'][0]);
    }

    public function test_username_and_email_are_read_from_the_data_array(): void
    {
        $http = listener_builder::verdict_http(0.0, 'safe');
        $listener = listener_builder::build($http);

        event_harness::trigger(
            event_harness::ucp_register_data_after([
                'data' => ['username' => 'pillsguy', 'email' => 'pills@bad.tld'],
            ]),
            static fn ($event) => $listener->check_registration($event)
        );

        $payload = $http->payload();
        self::assertSame('pillsguy', $payload['username'] ?? null);
        self::assertSame('pills@bad.tld', $payload['email'] ?? null);
        self::assertSame('registration', $payload['source'] ?? null);
    }

    public function test_safe_registration_leaves_the_error_array_alone(): void
    {
        $listener = listener_builder::build(listener_builder::verdict_http(0.0, 'safe'));

        $result = event_harness::trigger(
            event_harness::ucp_register_data_after(),
            static fn ($event) => $listener->check_registration($event)
        );

        self::assertSame([], $result['error']);
    }

    public function test_existing_errors_are_preserved(): void
    {
        $listener = listener_builder::build(listener_builder::verdict_http(0.95, 'blocked'));

        $result = event_harness::trigger(
            event_harness::ucp_register_data_after(['error' => ['Passwords do not match']]),
            static fn ($event) => $listener->check_registration($event)
        );

        self::assertCount(2, $result['error']);
        self::assertSame('Passwords do not match', $result['error'][0]);
    }

    public function test_disabled_check_is_a_no_op(): void
    {
        $http = listener_builder::verdict_http(0.95, 'blocked');
        $listener = listener_builder::build($http, null, ['spamtroll_check_registration' => 0]);

        $result = event_harness::trigger(
            event_harness::ucp_register_data_after(),
            static fn ($event) => $listener->check_registration($event)
        );

        self::assertSame(0, $http->call_count());
        self::assertSame([], $result['error']);
    }

    public function test_unsubmitted_form_is_not_scanned(): void
    {
        $http = listener_builder::verdict_http(0.95, 'blocked');
        $listener = listener_builder::build($http);

        $result = event_harness::trigger(
            event_harness::ucp_register_data_after(['submit' => false]),
            static fn ($event) => $listener->check_registration($event)
        );

        self::assertSame(0, $http->call_count());
        self::assertSame([], $result['error']);
    }
}
