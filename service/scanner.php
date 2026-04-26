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

use Spamtroll\Sdk\Exception\SpamtrollException;
use Spamtroll\Sdk\Request\CheckSpamRequest;
use Spamtroll\Sdk\Response\CheckSpamResponse;

/**
 * Wraps the Spamtroll SDK with phpBB-specific glue: thresholding,
 * fail-open behaviour, and persistence of the verdict to our local log
 * table.
 */
class scanner
{
    protected client_factory $factory;

    /** @var \phpbb\config\config */
    protected $config;

    protected logger $logger;

    /** @var \phpbb\user */
    protected $user;

    /**
     * @param \phpbb\config\config $config
     * @param \phpbb\user $user
     */
    public function __construct(client_factory $factory, $config, logger $logger, $user)
    {
        $this->factory = $factory;
        $this->config = $config;
        $this->logger = $logger;
        $this->user = $user;
    }

    public function check_post(string $content, ?string $username, ?string $ip): scan_result
    {
        return $this->scan($content, CheckSpamRequest::SOURCE_FORUM, $ip, $username, null, 'post');
    }

    public function check_pm(string $content, ?string $username, ?string $ip): scan_result
    {
        return $this->scan($content, CheckSpamRequest::SOURCE_MESSAGE, $ip, $username, null, 'pm');
    }

    public function check_registration(
        string $username,
        string $email,
        ?string $ip
    ): scan_result {
        $content = trim($username . ' ' . $email);
        return $this->scan($content, CheckSpamRequest::SOURCE_REGISTRATION, $ip, $username, $email, 'registration');
    }

    private function scan(
        string $content,
        string $source,
        ?string $ip,
        ?string $username,
        ?string $email,
        string $log_type
    ): scan_result {
        if (trim($content) === '') {
            return scan_result::allow_default();
        }

        try {
            $client = $this->factory->build();
            if (!$client->isConfigured()) {
                return scan_result::allow_default();
            }

            $response = $client->checkSpam(new CheckSpamRequest(
                $content,
                $source,
                $ip !== null && $ip !== '' ? $ip : null,
                $username !== null && $username !== '' ? $username : null,
                $email !== null && $email !== '' ? $email : null,
            ));

            // Quota exhausted — record locally for the ACP panel and
            // fail open (let the post / registration through).
            if ($response->httpCode === 402) {
                $this->record_quota_skipped($response);
                return scan_result::allow_default();
            }

            if (!$response->success) {
                error_log('Spamtroll: API returned error for ' . $log_type . ' scan: ' . ($response->error ?? '?'));
                return scan_result::allow_default();
            }

            $result = $this->build_result($response);
            $this->safe_log($log_type, $ip, $username, $result, $content);

            return $result;
        } catch (SpamtrollException $e) {
            // Fail-open: never block legitimate traffic on an outage.
            error_log('Spamtroll: API exception during ' . $log_type . ' scan: ' . $e->getMessage());
            return scan_result::allow_default();
        } catch (\Throwable $e) {
            error_log('Spamtroll: Unexpected error during ' . $log_type . ' scan: ' . $e->getMessage());
            return scan_result::allow_default();
        }
    }

    private function build_result(CheckSpamResponse $response): scan_result
    {
        $score = $response->getSpamScore();
        $spam_threshold = $this->float_config('spamtroll_spam_threshold', 0.70);
        $suspicious_threshold = $this->float_config('spamtroll_suspicious_threshold', 0.40);

        if ($response->isSpam() || $score >= $spam_threshold) {
            $action = scan_result::ACTION_BLOCK;
            $status = 'blocked';
        } elseif ($score >= $suspicious_threshold) {
            $action = scan_result::ACTION_MODERATE;
            $status = 'suspicious';
        } else {
            $action = scan_result::ACTION_ALLOW;
            $status = 'safe';
        }

        return new scan_result(
            $status,
            $action,
            $score,
            $response->getRawSpamScore(),
            $response->getSymbols(),
            true
        );
    }

    private function safe_log(
        string $type,
        ?string $ip,
        ?string $username,
        scan_result $result,
        string $content
    ): void {
        try {
            $this->logger->log([
                'content_type' => $type,
                'ip_address' => $ip,
                'username' => $username,
                'status' => $result->status,
                'spam_score' => $result->score,
                'raw_score' => $result->raw_score,
                'symbols' => $result->symbols,
                'action_taken' => $result->action,
                'content_preview' => $content,
            ]);
        } catch (\Throwable $e) {
            // Logging failures must never block the main flow.
            error_log('Spamtroll: failed to write scan log: ' . $e->getMessage());
        }
    }

    private function float_config(string $key, float $default): float
    {
        $value = $this->config[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        return (float) $value;
    }

    /**
     * Records a quota-exhausted scan in phpBB's config storage. The
     * payload is a JSON blob in the `spamtroll_quota_skipped_log`
     * config key — per-day counter pruned to 30 days plus the latest
     * usage block from the API. \phpbb\config\config handles
     * persistence; nothing extra needs the schema migrator.
     */
    private function record_quota_skipped(CheckSpamResponse $response): void
    {
        $stored = $this->load_quota_log();
        $byDay = isset($stored['days']) && is_array($stored['days']) ? $stored['days'] : [];
        $today = gmdate('Y-m-d');
        $byDay[$today] = (isset($byDay[$today]) ? (int) $byDay[$today] : 0) + 1;

        $cutoff = gmdate('Y-m-d', strtotime('-30 days'));
        foreach (array_keys($byDay) as $day) {
            if (!is_string($day) || $day < $cutoff) {
                unset($byDay[$day]);
            }
        }

        $usage = method_exists($response, 'getQuotaUsage') ? $response->getQuotaUsage() : [];

        $this->config->set('spamtroll_quota_skipped_log', (string) json_encode([
            'days' => $byDay,
            'last_at' => time(),
            'last_usage' => is_array($usage) ? $usage : [],
        ]));
    }

    /**
     * Public accessor for the ACP module that surfaces "X messages
     * were not scanned because daily quota was exhausted, upgrade
     * your plan". Always returns the canonical shape so the template
     * can render without null-checks.
     *
     * @return array{total: int, today: int, days: array<string,int>, last_usage: array<string,mixed>, last_at: int}
     */
    public function get_quota_skipped_stats(int $days = 7): array
    {
        $stored = $this->load_quota_log();
        $byDay = isset($stored['days']) && is_array($stored['days']) ? $stored['days'] : [];
        $cutoff = gmdate('Y-m-d', strtotime('-' . max(1, $days) . ' days'));

        $window = [];
        $total = 0;
        foreach ($byDay as $day => $count) {
            if (!is_string($day) || !is_int($count) || $day < $cutoff) {
                continue;
            }
            $window[$day] = $count;
            $total += $count;
        }
        $today = gmdate('Y-m-d');
        return [
            'total' => $total,
            'today' => isset($byDay[$today]) && is_int($byDay[$today]) ? $byDay[$today] : 0,
            'days' => $window,
            'last_usage' => isset($stored['last_usage']) && is_array($stored['last_usage']) ? $stored['last_usage'] : [],
            'last_at' => isset($stored['last_at']) && is_int($stored['last_at']) ? $stored['last_at'] : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function load_quota_log(): array
    {
        $raw = (string) ($this->config['spamtroll_quota_skipped_log'] ?? '');
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }
}
