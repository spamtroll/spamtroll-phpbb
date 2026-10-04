# Spamtroll for phpBB

Real-time spam detection for phpBB 3.3.x forums, powered by the
[Spamtroll](https://spamtroll.io) anti-spam API.

The extension scans:

- new forum posts (replies and topics),
- private messages,
- user registrations.

Verdicts come from the central Spamtroll service over HTTPS. When a piece of
content is flagged as **blocked**, posting (or registration) is refused with
a localised error message. When it falls in the **suspicious** zone, the same
mechanism is used — the rest is left to the board moderators via the regular
phpBB tooling.

## Requirements

- phpBB 3.3.x; phpBB 4 compatibility has not been verified
- PHP 8.2+
- The `curl` and `json` PHP extensions
- A Spamtroll account and platform API key (sign up at https://spamtroll.io and create a platform); service plan limits apply.

## Installation

### From a release archive (recommended)

Download `spamtroll_phpbb_<version>.zip` from the
[releases page](https://github.com/spamtroll/spamtroll-phpbb/releases) and
unpack it into the root of your phpBB install. The archive already contains
`ext/spamtroll/phpbb/` **including its `vendor/` directory**, so no Composer
run is needed on the board.

Then enable the extension in the ACP under
**Customise → Manage extensions → Spamtroll Anti-Spam → Enable**.

### From source

```bash
cd /path/to/phpbb/ext/
mkdir -p spamtroll && cd spamtroll
git clone https://github.com/spamtroll/spamtroll-phpbb.git phpbb
cd phpbb && composer install --no-dev --optimize-autoloader
```

Enabling the extension **before** `composer install` has run will fatal: the
`Spamtroll\Sdk\*` classes come from `vendor/` and phpBB has no way to fetch
them itself.

## Configuration

The settings live under **ACP → General → Spamtroll Settings**:

| Setting | Default | Description |
|---|---|---|
| API key | _(empty)_ | Your Spamtroll platform API key. Required. |
| API URL | `https://api.spamtroll.io/api/v1` | Endpoint base URL. |
| Timeout (s) | `5` | HTTP timeout per request. |
| Spam threshold | `0.70` | Normalised score (0–1) at and above which content is blocked. |
| Suspicious threshold | `0.40` | Normalized score (0–1); rejects posts; registrations/PMs are allowed below the spam threshold. |
| Check posts | on | Scan new posts. |
| Check PMs | on | Scan private messages. |
| Check registrations | on | Scan new user registrations. |
| Log retention (days) | `30` | How long to keep entries in `phpbb_spamtroll_log`. |

A **Test connection** button verifies the configured API key and URL by
calling `GET /scan/status` through the SDK. This checks connectivity/authentication;\na `POST /scan/check` smoke check with a configured platform is still needed to\nverify scanning permissions and available quota.

## Fail-open

If Spamtroll is unreachable (timeout, network error, 5xx, malformed
response), the extension lets the content through and writes a warning to
the phpBB error log. **Legitimate traffic is never blocked because of an
outage on our side.**

## Data flow

The extension sends scanned post/PM content, username and client IP to the
configured API. Registration checks also send email. Private-message scanning
starts enabled; disable it if your forum's data policy requires that.
The local audit table stores a content excerpt, verdict, score, symbols, IP and
username. Raw excerpts and author metadata require appropriate retention and
access controls. The service has its own [privacy policy](https://spamtroll.io/privacy)
and [terms](https://spamtroll.io/terms).

## Building and directory publication

Run `bash build/build-package.sh`, then `python3 build/verify-package.py`.
The build uses the committed Composer lock and creates a ZIP plus SHA-256 sidecar
under ignored `dist/`. Run `python3 build/check-epv.py PATH_TO_OFFICIAL_EPV`
to validate the exact archive layout using phpBB's validator.
Publication evidence and the prepared directory description are in
[PUBLICATION.md](PUBLICATION.md). A GitHub release is separate from phpBB
Customisation Database acceptance.

## License

Released under the GNU General Public License, version 2 only
(GPL-2.0-only) — the license required by phpBB extensions. See `LICENSE` in the source and `license.txt` in the installed ZIP.
