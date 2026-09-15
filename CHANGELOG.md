# Changelog

All notable changes to this project are documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to
[Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-09-14

### Added

- Tools > WDOD Toolkit admin screen with Environment, Debug Log, Staging, Performance and Security tabs.
- Environment report (PHP, database, WordPress, debug constants, cron, cache, theme, plugins) with plain-text export and "Copy report" button.
- Debug log viewer that tails the last 256 KB of `debug.log`, auto-refreshes over AJAX, and can clear or download the file.
- Staging mode: blocks outgoing mail (`pre_wp_mail` + PHPMailer safety net), forces noindex/nofollow, discourages search engines and shows a red STAGING badge in the admin bar. Allowlist via `wdod_site_toolkit_mail_allowlist`.
- Performance tweaks: disable emojis, disable embeds, disable XML-RPC, Heartbeat interval (15-120 s), post revision limit.
- Login hardening: generic login errors, `?author=N` enumeration block, hidden REST users endpoint, rate-limited failed-login logging.
- WP-CLI commands `wp wdod env`, `wp wdod log tail|clear|path`, `wp wdod staging on|off|status`, `wp wdod perf list|enable|disable`.
- Optional must-use loader drop-in (`mu-loader/wdod-site-toolkit-loader.php`).
