<?php
/**
 * Main plugin container.
 *
 * @package WDOD\SiteToolkit
 */

namespace WDOD\SiteToolkit;

use WDOD\SiteToolkit\Admin\Admin_Page;
use WDOD\SiteToolkit\Modules\Login_Hardening;
use WDOD\SiteToolkit\Modules\Performance;
use WDOD\SiteToolkit\Modules\Staging_Mode;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin.
 *
 * Singleton that wires settings, the admin page and only the modules that are
 * currently enabled in the settings.
 */
final class Plugin {

	/**
	 * Shared instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Registered module instances keyed by slug.
	 *
	 * @var array<string, object>
	 */
	private $modules = array();

	/**
	 * Whether init() already ran.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Returns the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor is private: use instance().
	 */
	private function __construct() {
		$this->settings = new Settings();
	}

	/**
	 * Boots the plugin. Hooked to plugins_loaded.
	 *
	 * @return void
	 */
	public function init() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );

		$this->settings->register();

		if ( is_admin() ) {
			$admin = new Admin_Page( $this->settings );
			$admin->register();
		}

		$this->load_modules();
	}

	/**
	 * Loads the plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'wdod-site-toolkit',
			false,
			dirname( plugin_basename( WDOD_SITE_TOOLKIT_FILE ) ) . '/languages'
		);
	}

	/**
	 * Instantiates and registers only the modules that are enabled.
	 *
	 * @return void
	 */
	private function load_modules() {
		if ( $this->settings->get( 'staging.enabled' ) ) {
			$this->modules['staging'] = new Staging_Mode( $this->settings );
		}

		if ( $this->settings->has_enabled_toggle( 'performance' ) ) {
			$this->modules['performance'] = new Performance( $this->settings );
		}

		if ( $this->settings->has_enabled_toggle( 'security' ) ) {
			$this->modules['security'] = new Login_Hardening( $this->settings );
		}

		foreach ( $this->modules as $module ) {
			$module->register();
		}
	}

	/**
	 * Returns the settings repository.
	 *
	 * @return Settings
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Returns a loaded module or null.
	 *
	 * @param string $slug Module slug (staging|performance|security).
	 * @return object|null
	 */
	public function module( $slug ) {
		return isset( $this->modules[ $slug ] ) ? $this->modules[ $slug ] : null;
	}

	/**
	 * Activation hook: stores default settings without overwriting existing ones.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( plugin_basename( WDOD_SITE_TOOLKIT_FILE ) );
			wp_die(
				esc_html__( 'WDOD Site Toolkit requires PHP 7.4 or newer.', 'wdod-site-toolkit' ),
				esc_html__( 'Plugin activation error', 'wdod-site-toolkit' ),
				array( 'back_link' => true )
			);
		}

		$settings = new Settings();

		if ( false === get_option( Settings::OPTION_NAME, false ) ) {
			add_option( Settings::OPTION_NAME, $settings->defaults(), '', 'yes' );
		}

		if ( false === get_option( Staging_Mode::COUNTER_OPTION, false ) ) {
			add_option( Staging_Mode::COUNTER_OPTION, 0, '', 'no' );
		}
	}

	/**
	 * Deactivation hook: nothing persistent is removed (see uninstall.php).
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
