<?php
/**
 * Must-use loader for WDOD Site Toolkit.
 *
 * Copy this file to wp-content/mu-plugins/wdod-site-toolkit-loader.php when
 * the toolkit must always be active (e.g. on every staging copy) and cannot
 * be deactivated from the Plugins screen. The plugin folder itself stays in
 * wp-content/plugins/wdod-site-toolkit so it can be updated normally.
 *
 * Do not also activate the plugin from the Plugins screen: the main file
 * guards against a double load, but activating it twice is pointless.
 *
 * @package WDOD\SiteToolkit
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WDOD_SITE_TOOLKIT_VERSION' ) ) {
	$wdod_site_toolkit_main = WP_PLUGIN_DIR . '/wdod-site-toolkit/wdod-site-toolkit.php';

	if ( is_readable( $wdod_site_toolkit_main ) ) {
		require_once $wdod_site_toolkit_main;

		// Activation hooks never fire for mu-plugins, so seed the options once.
		if ( false === get_option( 'wdod_site_toolkit_settings', false ) ) {
			\WDOD\SiteToolkit\Plugin::activate();
		}
	}

	unset( $wdod_site_toolkit_main );
}
