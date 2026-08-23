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
 * Minimal stand-in for \phpbb\request\request.
 *
 * variable() applies the same htmlspecialchars($value, ENT_COMPAT, 'UTF-8')
 * the real request layer does (phpbb/request/type_cast_helper.php:46), so
 * tests exercise the decoding the listener has to perform.
 */
final class fake_request
{
    /** @var array<string, mixed> */
    private array $vars;

    /** @var array<string, string> */
    private array $server_vars;

    /**
     * @param array<string, mixed> $vars
     * @param array<string, string> $server_vars
     */
    public function __construct(array $vars = [], array $server_vars = ['REMOTE_ADDR' => '198.51.100.7'])
    {
        $this->vars = $vars;
        $this->server_vars = $server_vars;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function variable(string $name, $default, bool $multibyte = false, ?int $super_global = null)
    {
        $value = $this->vars[$name] ?? $default;

        return is_string($value) ? htmlspecialchars($value, ENT_COMPAT, 'UTF-8') : $value;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function server(string $name, $default = '')
    {
        return $this->server_vars[$name] ?? $default;
    }

    public function is_set_post(string $name): bool
    {
        return isset($this->vars[$name]);
    }
}
