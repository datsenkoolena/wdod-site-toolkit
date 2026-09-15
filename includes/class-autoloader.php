<?php
/**
 * PSR-4-ish autoloader that maps the WDOD\SiteToolkit namespace onto
 * WordPress-Coding-Standards style file names (class-foo-bar.php).
 *
 * @package WDOD\SiteToolkit
 */

namespace WDOD\SiteToolkit;

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoloader.
 */
final class Autoloader {

	/**
	 * Root namespace handled by this loader.
	 *
	 * @var string
	 */
	const PREFIX = 'WDOD\\SiteToolkit\\';

	/**
	 * Registers the loader with SPL.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Resolves a fully-qualified class name to a file and requires it.
	 *
	 * WDOD\SiteToolkit\Admin\Admin_Page => includes/admin/class-admin-page.php
	 * WDOD\SiteToolkit\Modules\Staging_Mode => includes/modules/class-staging-mode.php
	 *
	 * @param string $class_name Fully-qualified class name.
	 * @return void
	 */
	public static function load( $class_name ) {
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( self::PREFIX ) );
		$parts    = explode( '\\', $relative );
		$name     = array_pop( $parts );

		$path = WDOD_SITE_TOOLKIT_DIR . 'includes/';

		foreach ( $parts as $part ) {
			$path .= strtolower( str_replace( '_', '-', $part ) ) . '/';
		}

		$path .= 'class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
