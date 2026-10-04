# phpBB Customisation Database publication preparation

Checked 2026-10-04. The audited extension already exists at `2af60ad`; this task
prepares its versioned distribution and directory submission prerequisites.
No accepted phpbb.com entry or publisher dashboard submission has been verified.
Public search cannot establish that there is no existing entry.

## Distribution

- Source: https://github.com/spamtroll/spamtroll-phpbb
- Candidate release: https://github.com/spamtroll/spamtroll-phpbb/releases/tag/v0.1.1
- Installable asset: `spamtroll_phpbb_0.1.1.zip`; use the plugin asset, not GitHub's source ZIP.
- Checksum asset: `spamtroll_phpbb_0.1.1.zip.sha256`.
- Archive root: `spamtroll/phpbb/`; extract into the forum's `ext/` directory.
- Bundled runtime SDK: locked v0.9.3, with MIT license; extension `license.txt`
  contains the GPL-2.0-only text. Composer's license is also retained.

The build copies explicit runtime/doc/metadata paths and installs production-only
locked dependencies in an isolated staging directory. It excludes development
tests/configuration, CI files, repository data and local credentials. Package
verification compares runtime bytes to source and checks namespace, metadata,
license, SDK and archive SHA-256 together.

Run `bash build/build-package.sh`, `python3 build/verify-package.py` and
`python3 build/check-epv.py PATH_TO_EPV`. The official validator is pinned to
`phpbb/epv@cc230d4a0253d7f6dc0d0c526c2db84ccd831f35` in CI. The wrapper verifies
its actual pass marker because some validator exceptions return process exit 0.

## Prepared directory fields

| Field | Value |
| --- | --- |
| Name | Spamtroll Anti-Spam |
| Extension identity | spamtroll/phpbb |
| Version | 0.1.1 |
| Type | phpBB extension |
| Target | phpBB 3.3.x; PHP 8.2+, curl and JSON |
| License | GPL-2.0-only |
| Source / download page | https://github.com/spamtroll/spamtroll-phpbb/releases/tag/v0.1.1 |
| Documentation | https://github.com/spamtroll/spamtroll-phpbb/blob/main/README.md |
| Support | https://github.com/spamtroll/spamtroll-phpbb/issues |
| Required service | Spamtroll account and platform API key; service plan limits apply |

Suggested description:

> Spamtroll Anti-Spam checks submitted forum posts, private messages and new user
> registrations through the Spamtroll API. Administrators can configure the API
> connection, normalized score thresholds, per-source switches and local log
> retention. Posts in the blocked or suspicious zone are rejected with a
> localized error. Registrations and private messages are rejected only in the
> blocked zone; suspicious submissions are not placed in an approval queue.
>
> Preview and refresh actions do not consume scans. API failures and exhausted
> service quota allow submissions to proceed. Scanned content, username and IP
> are transmitted to the configured service; registration checks also include
> email. Private-message scanning starts enabled and can be disabled. The local
> audit table retains a content excerpt, author metadata, verdict and detection
> symbols according to its configured retention.
>
> The extension is GPL-2.0-only and includes its production SDK. A Spamtroll
> account and platform API key are required; service access is governed by
> separate terms and plan limits. No phpBB 4 compatibility or official phpBB
> endorsement is claimed.

## Remaining directory steps

- Inspect the authorized publisher dashboard to check for an existing entry.
- Review the current contribution form, media requirements and SaaS disclosures.
- An explicit submission instruction and accessible authorized session are needed
  before sending; no forum post, email or directory submission has been sent.
- Supply the verified installable ZIP/checksum and describe the PHP 8.2 minimum.
- Record submission/revision ID, validator findings and accepted entry URL when
  actual directory publication is requested and completed.

Official sources: [validation policy](https://www.phpbb.com/extensions/rules-and-policies/validation-policy/)
requires installable extensions, accurate PHP requirements and GPL metadata.
[EPV instructions](https://www.phpbb.com/extensions/epv/) describe directory
prevalidation; [official validator source](https://github.com/phpbb/epv) enforces
the vendor/name layout and lowercase GPL license file. Passing these checks is
preparation evidence and does not establish directory approval.
