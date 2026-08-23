# Changelog

All notable changes to the Spamtroll phpBB extension will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Fixed
- **Registration scanning now actually runs.** The listener was subscribed to `core.user_add_modify_data`, which fires inside `user_add()` (`includes/functions_user.php:290`) with no `error` variable, and phpBB discards keys a listener adds — `dispatcher::trigger_event()` returns `get_data_filtered(array_keys($data))` (`phpbb/event/dispatcher.php:47`), i.e. `array_intersect_key` (`phpbb/event/data.php:44`). Moved to `core.ucp_register_data_after` (`includes/ucp/ucp_register.php:329-338`), which exposes `submit`, `data`, `cp_data` and `error` while the form can still be rejected.
- **Post scanning now actually runs.** `$post_data['message']` does not exist in `posting.php` in any code path: `:642-645` moves the text into the message parser and unsets `post_text`, restoring it only at `:1807` — after the event at `:1428`. Content is now read from `$request->variable('message', '', true)`, the same field phpBB reads at `posting.php:941`.
- **Private message scanning now actually runs.** `core.ucp_pm_compose_modify_parsed_text` does not exist anywhere in phpBB 3.3.x, so `check_pm()` was never invoked. Moved to `core.ucp_pm_compose_modify_parse_before` (`includes/ucp/ucp_pm_compose.php:845-868`), and the verdict is appended to `$error` instead of `$message_parser->warn_msg`, which phpBB drains at `:876-888`, before either `parse_*` event.
- **ACP module no longer fatals.** Opening ACP → Spamtroll Settings threw `ArgumentCountError`: phpBB builds modules with a bare `new $class_name($this)` (`includes/functions_module.php:598-600`) and never consults the DI container. The module now declares no constructor and resolves `$config`, `$language`, `$request`, `$template`, `$user` and its two extension services from the global scope inside `main()`, like phpBB's own bundled modules. The dead `spamtroll.phpbb.acp.module` service definition was removed.
- **The audit log can no longer kill a request.** `\phpbb\db\driver\driver::sql_error()` ends in `trigger_error(..., E_USER_ERROR)` (`driver.php:1028-1031`), which no `catch` can intercept, so a failed log write took the user's post down with it. The `INSERT` now runs between `sql_return_on_error(true)`/`(false)`.
- **UTF-8 safe truncation in the log.** `substr()` cut by bytes, leaving a broken multibyte sequence that MySQL in strict mode rejects on a `utf8mb4` column ("Incorrect string value") — the same fatal as above, easily triggered by a Polish or German post. All string columns are now cut with `utf8_substr()` (falling back to `mb_substr()`).
- **`symbols` column always holds parseable JSON.** Previously the encoded document was truncated mid-string; whole entries are now dropped until it fits.
- **Log retention works.** `cron/task/cleanup_logs.php` had no entry in `config/services.yml` and no `{ name: cron.task }` tag, so the class was never instantiated: the ACP "Log retention (days)" setting did nothing and personal data was kept indefinitely. Registered, with a `set_name` call. `logger::cleanup()` now deletes in batches of 500 instead of one unbounded `DELETE`.
- **Preview and refresh no longer trigger a scan.** `posting.php:937` and the PM equivalent run the event block for preview and refresh as well as submit; every preview click consumed a scan from the daily quota and could raise a blocking error on a button that does not post.
- Content is `html_entity_decode()`d before being sent to the API. phpBB's request layer `htmlspecialchars()`es every string variable (`phpbb/request/type_cast_helper.php:46`), so the scanner was being asked to classify `&amp;` and `&quot;`.
- **The extension is installable again.** `composer.json` declared a `path` repository pointing at a sibling SDK checkout (`../spamtroll-php-sdk`, pinned to 0.9.2); without that directory `composer install` aborts outright. The SDK is on Packagist, so the block is gone and the constraint is `^0.9.3`.
- **CI runs for the first time.** `composer validate --strict` failed on the `version` field, the first step of the QA workflow — PHPStan and PHPUnit were `skipped` on every run and had never executed. Field removed.
- A PHPStan ignore rule (`'#given\\.$#i'`) was dead: NEON single quotes are literal, so the pattern looked for a backslash. Replaced with a targeted rule, which let `tests/` return to the analysis set.

### Changed
- Minimum PHP raised to **8.2**; CI matrix moved to 8.2/8.3/8.4.
- PHPUnit raised to ^10.5 (9.6 is EOL and unusable on PHP 8.4); `phpunit.xml.dist` migrated to the 10.5 schema.
- `composer.lock` is now committed so release artefacts are reproducible.
- Installation documentation rewritten: the `composer create-project` recipe in the README could not have worked.

### Added
- Release workflow (`.github/workflows/release.yml`) building `spamtroll_phpbb_<version>.zip` with `ext/spamtroll/phpbb/` **including a production `vendor/` tree**, which is what the phpBB Extension Database expects. Without it the unpacked extension fatals on enable, since `Spamtroll\Sdk\*` has no other source. The job asserts `vendor/autoload.php` and the SDK are present before zipping.
- Test doubles under `tests/support/` and an event harness reproducing `\phpbb\event\dispatcher::trigger_event()`, including its `get_data_filtered()` key filtering. The previous `\phpbb\event\data` stub did not filter, which is why the test suite passed against a registration path that was a no-op in production.
- Regression coverage for all four broken paths (`tests/listener/{registration,post,pm}_test.php`, `tests/logger/logger_test.php`, `tests/acp/main_module_test.php`): 19 failures + 4 errors out of 43 against the pre-fix code, 43/43 green after.

### Added
- **Quota-aware fail-open** on HTTP 402 / `QUOTA_EXCEEDED`. Scanner records the event in `\phpbb\config\config` under `spamtroll_quota_skipped_log` and returns `scan_result::allow_default()` so posts and registrations go through unscanned instead of being blocked because the user's plan ran out of daily scans.
- `scanner::get_quota_skipped_stats($days)` returns the trailing-7-day count plus the latest API usage block for the ACP module to render an upgrade CTA. Storage is a single config row, JSON-encoded, pruned to 30 days on every write — no schema migrator needed.
- ACP settings template renders a quota-exhausted warning panel with the trailing-7-day count, the last reading from the API (`current/limit/plan`), and an "Upgrade your plan" CTA linking to `https://spamtroll.io/dashboard/billing`. The panel is only emitted when at least one event was recorded in the window so a healthy account doesn't see it. `main_module` gains a constructor dependency on the existing `scanner` service (`spamtroll.phpbb.scanner`).

## [0.1.0] - 2026-04-25

### Added
- Initial release of the Spamtroll extension for phpBB 3.3.x.
- Real-time spam scanning for new forum posts via `core.posting_modify_submission_errors`. _(Never functional — see Unreleased.)_
- Real-time spam scanning for private messages via `core.ucp_pm_compose_modify_parsed_text`. _(Never functional; that event does not exist — see Unreleased.)_
- Real-time spam scanning for user registrations via `core.user_add_modify_data`. _(Never functional — see Unreleased.)_
- ACP module ("Spamtroll Settings") under the General tab with:
  API key/URL, timeout, spam and suspicious thresholds, per-source toggles,
  log retention, and a "Test connection" action.
- Local scan log table (`phpbb_spamtroll_log`) and a daily cron task that
  prunes entries older than the configured retention window. _(The cron task
  was never registered — see Unreleased.)_
- Custom HTTP adapter implementing `Spamtroll\Sdk\Http\HttpClientInterface`.
- Fail-open behaviour on every API failure (timeout, connection refused,
  malformed response, server error) so legitimate traffic is never blocked
  when Spamtroll is unreachable.
