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

- phpBB 3.3 or newer
- PHP 8.0+
- The `curl` and `json` PHP extensions
- A Spamtroll API key (sign up at https://spamtroll.io)

## Installation

### Via Composer (recommended)

```bash
cd /path/to/phpbb/ext/
mkdir -p spamtroll && cd spamtroll
composer create-project spamtroll/phpbb phpbb --no-dev
```

Then enable the extension in the ACP under
**Customise → Manage extensions → Spamtroll Anti-Spam → Enable**.

### Manual

1. Copy the extension into `ext/spamtroll/phpbb/` inside your phpBB install.
2. Run `composer install --no-dev` from inside that directory to fetch the
   Spamtroll PHP SDK.
3. Enable the extension in the ACP.

## Configuration

The settings live under **ACP → General → Spamtroll Settings**:

| Setting | Default | Description |
|---|---|---|
| API key | _(empty)_ | Your Spamtroll API key. Required. |
| API URL | `https://api.spamtroll.io/api/v1` | Endpoint base URL. |
| Timeout (s) | `5` | HTTP timeout per request. |
| Spam threshold | `0.70` | Normalised score (0–1) at and above which content is blocked. |
| Suspicious threshold | `0.40` | Normalised score (0–1) at and above which content is flagged. |
| Check posts | on | Scan new posts. |
| Check PMs | on | Scan private messages. |
| Check registrations | on | Scan new user registrations. |
| Log retention (days) | `30` | How long to keep entries in `phpbb_spamtroll_log`. |

A **Test connection** button verifies the configured API key and URL by
calling `GET /scan/status` through the SDK.

## Fail-open

If Spamtroll is unreachable (timeout, network error, 5xx, malformed
response), the extension lets the content through and writes a warning to
the phpBB error log. **Legitimate traffic is never blocked because of an
outage on our side.**

## License

Released under the GNU General Public License, version 2 only
(GPL-2.0-only) — the license required by phpBB extensions. See `LICENSE`.
