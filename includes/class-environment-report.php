<?php
/**
 * Collects information about the server, WordPress and the active code base.
 *
 * @package WDOD\SiteToolkit
 */

namespace WDOD\SiteToolkit;

defined( 'ABSPATH' ) || exit;

/**
 * Class Environment_Report.
 */
class Environment_Report {

	/**
	 * Cached report.
	 *
	 * @var array|null
	 */
	private $report = null;

	/**
	 * Builds the report as an array of sections.
	 *
	 * Each section is an associative array of item id => array( 'label', 'value' ).
	 * The array is filterable through wdod_site_toolkit_environment_report.
	 *
	 * @return array<string, array{label: string, items: array}>
	 */
	public function to_array() {
		if ( null !== $this->report ) {
			return $this->report;
		}

		$report = array(
			'server'    => array(
				'label' => __( 'Server', 'wdod-site-toolkit' ),
				'items' => $this->server_items(),
			),
			'wordpress' => array(
				'label' => __( 'WordPress', 'wdod-site-toolkit' ),
				'items' => $this->wordpress_items(),
			),
			'debug'     => array(
				'label' => __( 'Debugging', 'wdod-site-toolkit' ),
				'items' => $this->debug_items(),
			),
			'cron'      => array(
				'label' => __( 'Cron & cache', 'wdod-site-toolkit' ),
				'items' => $this->cron_items(),
			),
			'theme'     => array(
				'label' => __( 'Theme', 'wdod-site-toolkit' ),
				'items' => $this->theme_items(),
			),
			'plugins'   => array(
				'label' => __( 'Active plugins', 'wdod-site-toolkit' ),
				'items' => $this->plugin_items(),
			),
		);

		/**
		 * Filters the environment report before it is rendered or exported.
		 *
		 * @param array $report Sections of label/items pairs.
		 */
		$filtered = apply_filters( 'wdod_site_toolkit_environment_report', $report );

		$this->report = is_array( $filtered ) ? $filtered : $report;

		return $this->report;
	}

	/**
	 * Renders the report as plain text suitable for copy & paste into a ticket.
	 *
	 * @return string
	 */
	public function to_text() {
		$lines = array();

		$lines[] = '### ' . get_bloginfo( 'name' ) . ' - ' . gmdate( 'Y-m-d H:i' ) . ' UTC';

		foreach ( $this->to_array() as $section ) {
			$lines[] = '';
			$lines[] = '== ' . $section['label'] . ' ==';

			foreach ( $section['items'] as $item ) {
				$lines[] = $item['label'] . ': ' . $item['value'];
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Returns a flat list of rows, handy for WP-CLI tables.
	 *
	 * @return array<int, array{section: string, key: string, value: string}>
	 */
	public function to_rows() {
		$rows = array();

		foreach ( $this->to_array() as $slug => $section ) {
			foreach ( $section['items'] as $item ) {
				$rows[] = array(
					'section' => $slug,
					'key'     => $item['label'],
					'value'   => $item['value'],
				);
			}
		}

		return $rows;
	}

	/**
	 * PHP / web server information.
	 *
	 * @return array
	 */
	private function server_items() {
		global $wpdb;

		$server_software = '';

		if ( isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
			$server_software = sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) );
		}

		$db_version = '';

		if ( method_exists( $wpdb, 'db_server_info' ) ) {
			$db_version = (string) $wpdb->db_server_info();
		}

		if ( '' === $db_version ) {
			$db_version = (string) $wpdb->db_version();
		}

		$extensions = array( 'curl', 'mbstring', 'intl', 'gd', 'imagick', 'zip', 'openssl', 'json', 'dom', 'exif' );
		$loaded     = array();
		$missing    = array();

		foreach ( $extensions as $extension ) {
			if ( extension_loaded( $extension ) ) {
				$loaded[] = $extension;
			} else {
				$missing[] = $extension;
			}
		}

		return array(
			'php_version'         => $this->item( __( 'PHP version', 'wdod-site-toolkit' ), PHP_VERSION ),
			'php_sapi'            => $this->item( __( 'PHP SAPI', 'wdod-site-toolkit' ), PHP_SAPI ),
			'server_software'     => $this->item( __( 'Web server', 'wdod-site-toolkit' ), $server_software ),
			'db_version'          => $this->item( __( 'Database server', 'wdod-site-toolkit' ), $db_version ),
			'memory_limit'        => $this->item( __( 'PHP memory_limit', 'wdod-site-toolkit' ), (string) ini_get( 'memory_limit' ) ),
			'wp_memory_limit'     => $this->item( __( 'WP_MEMORY_LIMIT', 'wdod-site-toolkit' ), $this->constant( 'WP_MEMORY_LIMIT' ) ),
			'wp_max_memory_limit' => $this->item( __( 'WP_MAX_MEMORY_LIMIT', 'wdod-site-toolkit' ), $this->constant( 'WP_MAX_MEMORY_LIMIT' ) ),
			'max_execution_time'  => $this->item( __( 'max_execution_time', 'wdod-site-toolkit' ), (string) ini_get( 'max_execution_time' ) ),
			'upload_max_filesize' => $this->item( __( 'upload_max_filesize', 'wdod-site-toolkit' ), (string) ini_get( 'upload_max_filesize' ) ),
			'post_max_size'       => $this->item( __( 'post_max_size', 'wdod-site-toolkit' ), (string) ini_get( 'post_max_size' ) ),
			'wp_max_upload'       => $this->item( __( 'Effective max upload', 'wdod-site-toolkit' ), size_format( wp_max_upload_size() ) ),
			'extensions_loaded'   => $this->item( __( 'PHP extensions', 'wdod-site-toolkit' ), implode( ', ', $loaded ) ),
			'extensions_missing'  => $this->item( __( 'Missing extensions', 'wdod-site-toolkit' ), $missing ? implode( ', ', $missing ) : __( 'none', 'wdod-site-toolkit' ) ),
		);
	}

	/**
	 * Core information.
	 *
	 * @return array
	 */
	private function wordpress_items() {
		return array(
			'wp_version'  => $this->item( __( 'WordPress version', 'wdod-site-toolkit' ), get_bloginfo( 'version' ) ),
			'site_url'    => $this->item( __( 'Site URL', 'wdod-site-toolkit' ), site_url() ),
			'home_url'    => $this->item( __( 'Home URL', 'wdod-site-toolkit' ), home_url() ),
			'is_ssl'      => $this->item( __( 'HTTPS', 'wdod-site-toolkit' ), $this->yes_no( is_ssl() ) ),
			'multisite'   => $this->item( __( 'Multisite', 'wdod-site-toolkit' ), $this->yes_no( is_multisite() ) ),
			'locale'      => $this->item( __( 'Locale', 'wdod-site-toolkit' ), get_locale() ),
			'timezone'    => $this->item( __( 'Timezone', 'wdod-site-toolkit' ), wp_timezone_string() ),
			'permalinks'  => $this->item( __( 'Permalink structure', 'wdod-site-toolkit' ), (string) get_option( 'permalink_structure' ) ? (string) get_option( 'permalink_structure' ) : __( 'Plain', 'wdod-site-toolkit' ) ),
			'blog_public' => $this->item( __( 'Search engine visibility', 'wdod-site-toolkit' ), '1' === (string) get_option( 'blog_public' ) ? __( 'Visible', 'wdod-site-toolkit' ) : __( 'Discouraged (noindex)', 'wdod-site-toolkit' ) ),
			'environment' => $this->item( __( 'Environment type', 'wdod-site-toolkit' ), function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : '' ),
		);
	}

	/**
	 * Debug constants.
	 *
	 * @return array
	 */
	private function debug_items() {
		$debug_log = new Debug_Log();

		return array(
			'wp_debug'         => $this->item( 'WP_DEBUG', $this->constant_flag( 'WP_DEBUG' ) ),
			'wp_debug_log'     => $this->item( 'WP_DEBUG_LOG', $this->constant_flag( 'WP_DEBUG_LOG' ) ),
			'wp_debug_display' => $this->item( 'WP_DEBUG_DISPLAY', $this->constant_flag( 'WP_DEBUG_DISPLAY' ) ),
			'script_debug'     => $this->item( 'SCRIPT_DEBUG', $this->constant_flag( 'SCRIPT_DEBUG' ) ),
			'savequeries'      => $this->item( 'SAVEQUERIES', $this->constant_flag( 'SAVEQUERIES' ) ),
			'display_errors'   => $this->item( __( 'PHP display_errors', 'wdod-site-toolkit' ), (string) ini_get( 'display_errors' ) ),
			'log_path'         => $this->item( __( 'Debug log path', 'wdod-site-toolkit' ), $debug_log->get_path() ),
			'log_size'         => $this->item( __( 'Debug log size', 'wdod-site-toolkit' ), $debug_log->exists() ? size_format( $debug_log->size() ) : __( 'not created', 'wdod-site-toolkit' ) ),
		);
	}

	/**
	 * Cron and object cache information.
	 *
	 * @return array
	 */
	private function cron_items() {
		$next = $this->next_cron_event();

		return array(
			'disable_wp_cron' => $this->item( 'DISABLE_WP_CRON', $this->constant_flag( 'DISABLE_WP_CRON' ) ),
			'alternate_cron'  => $this->item( 'ALTERNATE_WP_CRON', $this->constant_flag( 'ALTERNATE_WP_CRON' ) ),
			'next_cron'       => $this->item( __( 'Next scheduled event', 'wdod-site-toolkit' ), $next ),
			'object_cache'    => $this->item( __( 'External object cache', 'wdod-site-toolkit' ), $this->yes_no( wp_using_ext_object_cache() ) ),
			'opcache'         => $this->item( __( 'OPcache', 'wdod-site-toolkit' ), $this->yes_no( function_exists( 'opcache_get_status' ) && ini_get( 'opcache.enable' ) ) ),
			'wp_cache_const'  => $this->item( 'WP_CACHE', $this->constant_flag( 'WP_CACHE' ) ),
		);
	}

	/**
	 * Active theme information.
	 *
	 * @return array
	 */
	private function theme_items() {
		$theme  = wp_get_theme();
		$parent = $theme->parent();

		$items = array(
			'theme'       => $this->item( __( 'Active theme', 'wdod-site-toolkit' ), $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ),
			'theme_slug'  => $this->item( __( 'Theme directory', 'wdod-site-toolkit' ), $theme->get_stylesheet() ),
			'block_theme' => $this->item( __( 'Block theme', 'wdod-site-toolkit' ), $this->yes_no( method_exists( $theme, 'is_block_theme' ) && $theme->is_block_theme() ) ),
		);

		if ( $parent instanceof \WP_Theme ) {
			$items['parent_theme'] = $this->item( __( 'Parent theme', 'wdod-site-toolkit' ), $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' ) );
		} else {
			$items['parent_theme'] = $this->item( __( 'Parent theme', 'wdod-site-toolkit' ), __( 'none', 'wdod-site-toolkit' ) );
		}

		return $items;
	}

	/**
	 * Active plugins with versions.
	 *
	 * @return array
	 */
	private function plugin_items() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all    = get_plugins();
		$active = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}

		$items = array();

		foreach ( array_unique( $active ) as $file ) {
			if ( ! isset( $all[ $file ] ) ) {
				continue;
			}

			$slug           = sanitize_key( str_replace( array( '/', '.php' ), array( '_', '' ), $file ) );
			$items[ $slug ] = $this->item( $all[ $file ]['Name'], $all[ $file ]['Version'] );
		}

		$mu = function_exists( 'get_mu_plugins' ) ? get_mu_plugins() : array();

		foreach ( $mu as $file => $data ) {
			$slug           = 'mu_' . sanitize_key( str_replace( '.php', '', $file ) );
			$items[ $slug ] = $this->item(
				/* translators: %s: plugin name */
				sprintf( __( '%s (must-use)', 'wdod-site-toolkit' ), $data['Name'] ? $data['Name'] : $file ),
				$data['Version'] ? $data['Version'] : '-'
			);
		}

		if ( empty( $items ) ) {
			$items['none'] = $this->item( __( 'Plugins', 'wdod-site-toolkit' ), __( 'none active', 'wdod-site-toolkit' ) );
		}

		return $items;
	}

	/**
	 * Formats the next scheduled cron event.
	 *
	 * @return string
	 */
	private function next_cron_event() {
		$crons = function_exists( '_get_cron_array' ) ? _get_cron_array() : array();

		if ( empty( $crons ) || ! is_array( $crons ) ) {
			return __( 'nothing scheduled', 'wdod-site-toolkit' );
		}

		$timestamps = array_filter( array_keys( $crons ), 'is_int' );

		if ( empty( $timestamps ) ) {
			return __( 'nothing scheduled', 'wdod-site-toolkit' );
		}

		$next  = min( $timestamps );
		$hooks = array_keys( (array) $crons[ $next ] );
		$hook  = $hooks ? $hooks[0] : '';
		$when  = wp_date( 'Y-m-d H:i:s', $next );

		if ( $next < time() ) {
			/* translators: 1: date/time, 2: hook name */
			return sprintf( __( '%1$s (%2$s) - overdue', 'wdod-site-toolkit' ), $when, $hook );
		}

		/* translators: 1: date/time, 2: hook name */
		return sprintf( __( '%1$s (%2$s)', 'wdod-site-toolkit' ), $when, $hook );
	}

	/**
	 * Builds one report item.
	 *
	 * @param string $label Human readable label.
	 * @param string $value Value.
	 * @return array{label: string, value: string}
	 */
	private function item( $label, $value ) {
		return array(
			'label' => (string) $label,
			'value' => '' === (string) $value ? '-' : (string) $value,
		);
	}

	/**
	 * Returns a constant as string or "undefined".
	 *
	 * @param string $name Constant name.
	 * @return string
	 */
	private function constant( $name ) {
		if ( ! defined( $name ) ) {
			return __( 'undefined', 'wdod-site-toolkit' );
		}

		$value = constant( $name );

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		return (string) $value;
	}

	/**
	 * Returns a boolean-ish constant as "true"/"false"/"undefined" (strings are kept).
	 *
	 * @param string $name Constant name.
	 * @return string
	 */
	private function constant_flag( $name ) {
		if ( ! defined( $name ) ) {
			return __( 'undefined', 'wdod-site-toolkit' );
		}

		$value = constant( $name );

		if ( is_string( $value ) && ! in_array( strtolower( $value ), array( '', '0', '1', 'true', 'false' ), true ) ) {
			return $value;
		}

		return $value ? 'true' : 'false';
	}

	/**
	 * Yes/no label.
	 *
	 * @param bool $flag Flag.
	 * @return string
	 */
	private function yes_no( $flag ) {
		return $flag ? __( 'yes', 'wdod-site-toolkit' ) : __( 'no', 'wdod-site-toolkit' );
	}
}
