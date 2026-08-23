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
 * Stand-in for \phpbb\db\driver\driver_interface.
 *
 * Records the rows handed to sql_build_array() and the return_on_error
 * flips, and can be told to behave like phpBB does on a failing statement:
 * sql_error() ends in trigger_error(..., E_USER_ERROR) unless
 * return_on_error is set (phpbb/db/driver/driver.php:989-1031). The fake
 * raises a plain \Error for that, which — like the real fatal — is *not*
 * a \Throwable the extension is allowed to rely on catching; the tests
 * assert the flag is set so it never fires.
 */
final class fake_db
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    /** @var array<int, string> */
    public array $queries = [];

    /** @var array<int, bool> */
    public array $return_on_error_calls = [];

    public bool $return_on_error = false;

    public bool $fail_next_query = false;

    /** @var array<int, int> Row ids handed back by sql_query_limit(). */
    public array $select_ids = [];

    public int $affected = 0;

    public function sql_build_array(string $query, array $assoc_ary): string
    {
        $this->rows[] = $assoc_ary;

        return '(' . implode(', ', array_keys($assoc_ary)) . ') VALUES (…)';
    }

    /**
     * @return mixed
     */
    public function sql_query(string $sql)
    {
        $this->queries[] = $sql;

        if ($this->fail_next_query) {
            $this->fail_next_query = false;

            if (!$this->return_on_error) {
                // phpBB would trigger_error(E_USER_ERROR) here and never
                // return; nothing in userland can catch that.
                throw new \Error('fatal SQL error escaped to the request');
            }

            return false;
        }

        return true;
    }

    /**
     * @return mixed
     */
    public function sql_query_limit(string $sql, int $total, int $offset = 0)
    {
        $this->queries[] = $sql;

        return array_splice($this->select_ids, 0, $total);
    }

    /**
     * @param mixed $result
     * @return array<string, mixed>|false
     */
    public function sql_fetchrow(&$result)
    {
        if (!is_array($result) || $result === []) {
            return false;
        }

        return ['log_id' => (string) array_shift($result)];
    }

    /**
     * @param mixed $result
     */
    public function sql_freeresult($result = false): bool
    {
        return true;
    }

    /**
     * @param array<int, int|string> $values
     */
    public function sql_in_set(string $field, array $values): string
    {
        $this->affected = count($values);

        return $field . ' IN (' . implode(', ', array_map('intval', $values)) . ')';
    }

    public function sql_affectedrows(): int
    {
        return $this->affected;
    }

    public function sql_return_on_error(bool $fail = false): void
    {
        $this->return_on_error_calls[] = $fail;
        $this->return_on_error = $fail;
    }
}
