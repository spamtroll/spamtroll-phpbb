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

namespace spamtroll\phpbb\service;

/**
 * Spamtroll verdict for a single piece of content.
 *
 * Returned by every public method on {@see scanner}; lets the listener
 * decide whether to block, moderate or pass the content through without
 * having to know about the SDK response types.
 */
final class scan_result
{
    public const ACTION_BLOCK = 'block';
    public const ACTION_MODERATE = 'moderate';
    public const ACTION_ALLOW = 'allow';

    public string $status;
    public string $action;
    public float $score;
    public float $raw_score;
    /** @var array<int, string> */
    public array $symbols;
    public bool $api_ok;

    /**
     * @param array<int, string> $symbols
     */
    public function __construct(
        string $status,
        string $action,
        float $score,
        float $raw_score,
        array $symbols,
        bool $api_ok
    ) {
        $this->status = $status;
        $this->action = $action;
        $this->score = $score;
        $this->raw_score = $raw_score;
        $this->symbols = $symbols;
        $this->api_ok = $api_ok;
    }

    public static function allow_default(): self
    {
        return new self('safe', self::ACTION_ALLOW, 0.0, 0.0, [], false);
    }

    public function should_block(): bool
    {
        return $this->action === self::ACTION_BLOCK;
    }

    public function should_moderate(): bool
    {
        return $this->action === self::ACTION_MODERATE;
    }
}
