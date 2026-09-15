<?php
/**
 * WP-CLI commands: wp wdod env | log | staging | perf.
 *
 * This file is only loaded when WP-CLI registers the command, so it is safe
 * to reference WP_CLI classes here.
 *
 * @package WDOD\SiteToolkit
 */

namespace WDOD\SiteToolkit\CLI;

use WDOD\SiteToolkit\Debug_Log;
use WDOD\SiteToolkit\Environment_Report;
use WDOD\SiteToolkit\Modules\Performance;
use WDOD\SiteToolkit\Modules\Staging_Mode;
use WDOD\SiteToolkit\Plugin;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Maintenance helpers from WDOD Site Toolkit.
 */
class Command {

	/**
	 * Prints the environment report.
	 *
	 * ## OPTIONS
	 *
	 * [--section=<section>]
	 * : Only print one section (server, WordPress, debug, cron, theme, plugins).
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 *   - text
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wdod env
	 *     wp wdod env --section=plugins --format=json
	 *     wp wdod env --format=text > environment.txt
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function env( $args, $assoc_args ) {
		unset( $args );

		$report = new Environment_Report();
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		if ( 'text' === $format ) {
			WP_CLI::line( $report->to_text() );
			return;
		}

		$rows = $report->to_rows();

		if ( ! empty( $assoc_args['section'] ) ) {
			$section = sanitize_key( $assoc_args['section'] );
			$rows    = array_values(
				array_filter(
					$rows,
					static function ( $row ) use ( $section ) {
						return $row['section'] === $section;
					}
				)
			);

			if ( empty( $rows ) ) {
				WP_CLI::error( sprintf( 'Unknown section "%s".', $section ) );
			}
		}

		WP_CLI\Utils\format_items( $format, $rows, array( 'section', 'key', 'value' ) );
	}

	/**
	 * Shows or clears the debug log.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : What to do.
	 * ---
	 * options:
	 *   - tail
	 *   - clear
	 *   - path
	 * ---
	 *
	 * [--lines=<lines>]
	 * : Number of lines to print with "tail".
	 * ---
	 * default: 50
	 * ---
	 *
	 * [--yes]
	 * : Skip the confirmation for "clear".
	 *
	 * ## EXAMPLES
	 *
	 *     wp wdod log tail --lines=200
	 *     wp wdod log clear --yes
	 *     wp wdod log path
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function log( $args, $assoc_args ) {
		$action = isset( $args[0] ) ? $args[0] : 'tail';
		$log    = new Debug_Log();

		switch ( $action ) {
			case 'path':
				WP_CLI::line( $log->get_path() );
				break;

			case 'clear':
				if ( ! $log->exists() ) {
					WP_CLI::success( 'No log file to clear.' );
					return;
				}

				WP_CLI::confirm( sprintf( 'Clear %s (%s)?', $log->get_path(), size_format( $log->size() ) ), $assoc_args );

				if ( $log->clear() ) {
					WP_CLI::success( 'Debug log cleared.' );
				} else {
					WP_CLI::error( 'Could not clear the log: file is not writable.' );
				}
				break;

			case 'tail':
			default:
				if ( ! $log->exists() ) {
					WP_CLI::warning( sprintf( 'No log file at %s.', $log->get_path() ) );
					return;
				}

				$lines = isset( $assoc_args['lines'] ) ? (int) $assoc_args['lines'] : 50;

				foreach ( $log->tail_lines( $lines ) as $line ) {
					WP_CLI::line( $line );
				}
				break;
		}
	}

	/**
	 * Turns staging mode on or off.
	 *
	 * ## OPTIONS
	 *
	 * [<state>]
	 * : "on", "off" or "status" (default).
	 * ---
	 * default: status
	 * options:
	 *   - on
	 *   - off
	 *   - status
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wdod staging on
	 *     wp wdod staging off
	 *     wp wdod staging
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function staging( $args, $assoc_args ) {
		unset( $assoc_args );

		$state    = isset( $args[0] ) ? strtolower( $args[0] ) : 'status';
		$settings = Plugin::instance()->settings();

		if ( 'on' === $state || 'off' === $state ) {
			$settings->update( array( 'staging' => array( 'enabled' => 'on' === $state ) ) );
			WP_CLI::success( 'on' === $state ? 'Staging mode enabled: mail blocked, noindex on.' : 'Staging mode disabled.' );
			return;
		}

		$enabled = (bool) $settings->get( 'staging.enabled' );

		WP_CLI::line( sprintf( 'Staging mode: %s', $enabled ? 'ON' : 'OFF' ) );
		WP_CLI::line( sprintf( 'Blocked emails: %d', Staging_Mode::blocked_count() ) );
	}

	/**
	 * Lists or toggles performance tweaks.
	 *
	 * ## OPTIONS
	 *
	 * [<action>]
	 * : "list" (default), "enable" or "disable".
	 * ---
	 * default: list
	 * options:
	 *   - list
	 *   - enable
	 *   - disable
	 * ---
	 *
	 * [<toggle>]
	 * : Toggle key for enable/disable (disable_emojis, disable_embeds, disable_xmlrpc, limit_heartbeat, limit_revisions).
	 *
	 * [--format=<format>]
	 * : Output format for "list".
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wdod perf list
	 *     wp wdod perf enable disable_emojis
	 *     wp wdod perf disable limit_heartbeat
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function perf( $args, $assoc_args ) {
		$action   = isset( $args[0] ) ? strtolower( $args[0] ) : 'list';
		$settings = Plugin::instance()->settings();
		$toggles  = Performance::toggles();

		if ( 'enable' === $action || 'disable' === $action ) {
			$toggle = isset( $args[1] ) ? sanitize_key( $args[1] ) : '';

			if ( ! isset( $toggles[ $toggle ] ) ) {
				WP_CLI::error( sprintf( 'Unknown toggle. Valid keys: %s', implode( ', ', array_keys( $toggles ) ) ) );
			}

			$settings->update( array( 'performance' => array( $toggle => 'enable' === $action ) ) );
			WP_CLI::success( sprintf( '%s %s.', $toggle, 'enable' === $action ? 'enabled' : 'disabled' ) );
			return;
		}

		$values = (array) $settings->get( 'performance', array() );
		$rows   = array();

		foreach ( $toggles as $key => $label ) {
			$detail = '';

			if ( 'limit_heartbeat' === $key ) {
				$detail = sprintf( '%ds', (int) $values['heartbeat_interval'] );
			} elseif ( 'limit_revisions' === $key ) {
				$detail = sprintf( '%d revisions', (int) $values['revisions_to_keep'] );
			}

			$rows[] = array(
				'toggle'      => $key,
				'enabled'     => ! empty( $values[ $key ] ) ? 'yes' : 'no',
				'value'       => $detail,
				'description' => $label,
			);
		}

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		WP_CLI\Utils\format_items( $format, $rows, array( 'toggle', 'enabled', 'value', 'description' ) );
	}
}
