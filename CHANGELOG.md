# Changelog

All notable changes to the Spamtroll phpBB extension will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- **Quota-aware fail-open** on HTTP 402 / `QUOTA_EXCEEDED`. Scanner records the event in `\phpbb\config\config` under `spamtroll_quota_skipped_log` and returns `scan_result::allow_default()` so posts and registrations go through unscanned instead of being blocked because the user's plan ran out of daily scans.
- `scanner::get_quota_skipped_stats($days)` returns the trailing-7-day count plus the latest API usage block for the ACP module to render an upgrade CTA. Storage is a single config row, JSON-encoded, pruned to 30 days on every write — no schema migrator needed.
- ACP settings template renders a quota-exhausted warning panel with the trailing-7-day count, the last reading from the API (`current/limit/plan`), and an "Upgrade your plan" CTA linking to `https://spamtroll.io/dashboard/billing`. The panel is only emitted when at least one event was recorded in the window so a healthy account doesn't see it. `main_module` gains a constructor dependency on the existing `scanner` service (`spamtroll.phpbb.scanner`).

## [0.1.0] - 2026-04-25

### Added
- Initial release of the Spamtroll extension for phpBB 3.3.x.
- Real-time spam scanning for new forum posts via `core.posting_modify_submission_errors`.
- Real-time spam scanning for private messages via `core.ucp_pm_compose_modify_parsed_text`.
- Real-time spam scanning for user registrations via `core.user_add_modify_data`.
- ACP module ("Spamtroll Settings") under the General tab with:
  API key/URL, timeout, spam and suspicious thresholds, per-source toggles,
  log retention, and a "Test connection" action.
- Local scan log table (`phpbb_spamtroll_log`) and a daily cron task that
  prunes entries older than the configured retention window.
- Custom HTTP adapter implementing `Spamtroll\Sdk\Http\HttpClientInterface`.
- Fail-open behaviour on every API failure (timeout, connection refused,
  malformed response, server error) so legitimate traffic is never blocked
  when Spamtroll is unreachable.
