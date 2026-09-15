# WDOD Site Toolkit

Maintenance and troubleshooting toolkit for WordPress. One small plugin that replaces the handful of snippets and single-purpose plugins a developer usually installs on every site: an environment report, a debug-log viewer, a staging switch, a few safe performance tweaks, login hardening and WP-CLI commands.

- Requires PHP 7.4+, WordPress 6.4+ (tested up to 7.1)
- No build step, no runtime Composer dependencies, zero external requests
- Everything is opt-in: activating the plugin changes nothing until a toggle is switched on

## What it does

| Tab / feature | Summary |
| --- | --- |
| **Environment** | PHP, database, WordPress, debug constants, cron, object cache, theme and active plugins with versions. One click copies a plain-text report for tickets and hand-overs. |
| **Debug Log** | Tails the last 256 KB of `debug.log` (honours a custom `WP_DEBUG_LOG` path) without loading the whole file, auto-refreshes every 10 seconds, and lets you clear or download it. |
| **Staging** | Blocks all outgoing mail (`pre_wp_mail` plus a PHPMailer safety net), forces `noindex, nofollow`, switches "Discourage search engines" on and shows a red **STAGING** badge in the admin bar. Counts blocked emails. |
| **Performance** | Disable emojis, disable embeds, disable XML-RPC / pingbacks, slow the Heartbeat API (15-120 s), cap post revisions. |
| **Security** | Generic login error message, block `?author=N` user enumeration, hide `/wp/v2/users` from visitors, log failed logins with IP (rate-limited). |
| **WP-CLI** | `wp wdod env`, `wp wdod log`, `wp wdod staging`, `wp wdod perf`. |

## Screenshots

_Screenshots will be added here (Environment tab, Debug Log tab, Staging badge)._

## Requirements

- WordPress 6.4 or newer
- PHP 7.4 or newer
- `manage_options` capability to use the admin screen or any action

## Installation

1. Copy the `wdod-site-toolkit` folder to `wp-content/plugins/` (or upload the zip in **Plugins > Add New**).
2. Activate **WDOD Site Toolkit**.
3. Open **Tools > WDOD Toolkit**.

### Optional: make it a must-use plugin

For staging servers where the toolkit must never be deactivated, copy `mu-loader/wdod-site-toolkit-loader.php` into `wp-content/mu-plugins/`. The plugin folder stays in `wp-content/plugins/wdod-site-toolkit`, so it can still be updated; the loader simply requires it on every request and seeds the default options once.

## Usage

### Environment

Shows six cards: Server, WordPress, Debugging, Cron & cache, Theme and Active plugins. Below them is the same information as plain text. **Copy report** puts it on the clipboard (with an `execCommand` fallback for plain-http local sites). Add your own rows with the `wdod_site_toolkit_environment_report` filter.

### Debug Log

- Shows the resolved log path, whether the file exists and its size.
- Only the last 256 KB are read (via `fseek`), so a multi-gigabyte log does not exhaust memory.
- **Auto-refresh** polls `admin-ajax.php` every 10 seconds (nonce + capability checked) and sticks to the bottom like `tail -f`.
- **Clear log** truncates the file in place (keeps the inode PHP is writing to). Disabled when the file is not writable.
- **Download log** streams the file as a `text/plain` attachment.

### Staging

Tick **Enable staging mode** and save. From then on:

- `wp_mail()` returns `false` before anything is sent and the blocked-mail counter increases.
- Code that bypasses `wp_mail()` but still goes through `phpmailer_init` gets its recipients stripped.
- Addresses returned by the `wdod_site_toolkit_mail_allowlist` filter still receive mail.
- `wp_robots` outputs `noindex, nofollow`; `blog_public` is forced to `0`.
- A red **STAGING** badge is added to the admin bar for administrators.

### Performance

Each toggle is independent and documented inline. The Heartbeat interval (15-120 s) and revision limit (0-100) only apply while their toggle is on.

### Security

- **Generic login errors** - the same message for wrong user and wrong password.
- **Block author enumeration** - `?author=1` from a logged-out visitor is redirected to the home page before the canonical redirect can reveal the username.
- **Hide REST users** - `/wp-json/wp/v2/users` is removed for anonymous requests; logged-in users (editors, the block editor) are unaffected.
- **Log failed logins** - writes `[WDOD Site Toolkit] Failed login for "user" from 1.2.3.4 (invalid_username)` to the PHP error log, at most 10 lines per IP every 15 minutes.

## WP-CLI commands

```bash
wp wdod env                              # table of every report row
wp wdod env --section=plugins --format=json
wp wdod env --format=text                # same text as the "Copy report" button

wp wdod log tail --lines=200             # last 200 lines of debug.log
wp wdod log clear --yes                  # truncate the log
wp wdod log path                         # print the resolved path

wp wdod staging                          # status + blocked-mail count
wp wdod staging on
wp wdod staging off

wp wdod perf list                        # table of performance toggles
wp wdod perf enable disable_emojis
wp wdod perf disable limit_heartbeat
```

## Hooks & Filters

| Hook | Type | Arguments | Description |
| --- | --- | --- | --- |
| `wdod_site_toolkit_mail_allowlist` | filter | `string[] $emails` | Email addresses that still receive mail while staging mode is on. Default `array()`. |
| `wdod_site_toolkit_login_error_message` | filter | `string $message` | Text shown on failed logins when "generic login errors" is on. |
| `wdod_site_toolkit_environment_report` | filter | `array $report` | Sections of the environment report (`slug => array( 'label', 'items' )`). Add or remove rows. |
| `wdod_site_toolkit_settings_defaults` | filter | `array $defaults` | Default settings grouped by section (`staging`, `performance`, `security`). |

Example - keep receiving mail on a staging copy:

```php
add_filter( 'wdod_site_toolkit_mail_allowlist', function ( $emails ) {
	$emails[] = 'dev@example.com';
	return $emails;
} );
```

Example - add a row to the environment report:

```php
add_filter( 'wdod_site_toolkit_environment_report', function ( $report ) {
	$report['server']['items']['redis'] = array(
		'label' => 'Redis',
		'value' => class_exists( 'Redis' ) ? 'available' : 'missing',
	);
	return $report;
} );
```

## Development

```bash
composer install       # installs PHP_CodeSniffer + WordPress Coding Standards
composer lint          # php -l on every file
composer phpcs         # WordPress + PHPCompatibilityWP (PHP 7.4+) rules
composer phpcbf        # auto-fix what can be fixed
```

Project layout:

```
wdod-site-toolkit.php            bootstrap, constants, WP-CLI registration
uninstall.php                    removes options and transients
includes/class-autoloader.php    maps WDOD\SiteToolkit\* to class-*.php files
includes/class-plugin.php        singleton; loads only enabled modules
includes/class-settings.php      single option, defaults, sanitiser
includes/class-environment-report.php
includes/class-debug-log.php
includes/admin/                  Tools > WDOD Toolkit screen + views
includes/modules/                staging mode, performance, login hardening
includes/cli/class-command.php   wp wdod ...
mu-loader/                       optional must-use drop-in
assets/                          admin.css, admin.js (vanilla)
languages/                       wdod-site-toolkit.pot
```

Settings are stored in a single option, `wdod_site_toolkit_settings`; the blocked-mail counter in `wdod_site_toolkit_blocked_mail_count`. Deleting the plugin removes both plus any `wdod_site_toolkit_*` transients.

## Changelog

### 1.0.0

- Initial release. See [CHANGELOG.md](CHANGELOG.md).

## License

GPL-2.0-or-later.
