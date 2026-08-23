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

require_once __DIR__ . '/../vendor/autoload.php';

// Stand-ins for phpBB's namespaced classes so unit tests can run without a
// full phpBB install. These have to mirror the real classes closely enough
// that a test failure means a production failure — the previous stub of
// \phpbb\event\data did not, and hid the fact that none of the three scan
// paths were reachable (audit W7 / K1).

if (!class_exists('phpbb\\config\\config')) {
    eval('namespace phpbb\\config { class config implements \\ArrayAccess {
        /** @var array<string, mixed> */
        private array $values;
        public function __construct(array $values = []) { $this->values = $values; }
        public function offsetExists($key): bool { return array_key_exists($key, $this->values); }
        public function offsetGet($key): mixed { return $this->values[$key] ?? null; }
        public function offsetSet($key, $value): void { $this->values[(string) $key] = $value; }
        public function offsetUnset($key): void { unset($this->values[$key]); }
        public function set(string $key, string $value): void { $this->values[$key] = $value; }
    } }');
}

if (!class_exists('phpbb\\event\\data')) {
    // Faithful port of phpbb/event/data.php (3.3.x). The important part is
    // get_data_filtered(): phpbb/event/dispatcher.php:47 returns
    //
    //     $event->get_data_filtered(array_keys($data))
    //
    // and phpbb/event/data.php:44 implements it as
    //
    //     array_intersect_key($this->data, array_flip($keys))
    //
    // so any key a listener *adds* — as opposed to one it overwrites — is
    // silently thrown away before phpBB looks at the result. $data is
    // private in the real class too; tests must go through the accessors.
    eval('namespace phpbb\\event { class data implements \\ArrayAccess {
        /** @var array<string, mixed> */
        private array $data;
        public function __construct(array $data = []) { $this->set_data($data); }
        public function set_data(array $data = []): void { $this->data = $data; }
        /** @return array<string, mixed> */
        public function get_data(): array { return $this->data; }
        /**
         * @param array<int, string> $keys
         * @return array<string, mixed>
         */
        public function get_data_filtered($keys): array { return array_intersect_key($this->data, array_flip($keys)); }
        public function offsetExists($offset): bool { return isset($this->data[$offset]); }
        public function offsetGet($offset): mixed { return $this->data[$offset] ?? null; }
        public function offsetSet($offset, $value): void { $this->data[$offset] = $value; }
        public function offsetUnset($offset): void { unset($this->data[$offset]); }
        public function update_subarray($subarray, $key, $value): void { $this->data[$subarray][$key] = $value; }
    } }');
}
