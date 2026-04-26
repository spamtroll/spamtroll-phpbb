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

// Stand-in for phpBB's namespaced classes so unit tests can run without
// a full phpBB install. Only the surface our scanner / listener touches
// is stubbed; functional behaviour is exercised through real
// implementations in the unit tests via plain PHP arrays / objects.

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
    eval('namespace phpbb\\event { class data implements \\ArrayAccess {
        /** @var array<string, mixed> */
        public array $data;
        public function __construct(array $data = []) { $this->data = $data; }
        public function get_data(): array { return $this->data; }
        public function set_data(array $data): void { $this->data = $data; }
        public function offsetExists($key): bool { return array_key_exists($key, $this->data); }
        public function offsetGet($key): mixed { return $this->data[$key] ?? null; }
        public function offsetSet($key, $value): void { $this->data[(string) $key] = $value; }
        public function offsetUnset($key): void { unset($this->data[$key]); }
    } }');
}
