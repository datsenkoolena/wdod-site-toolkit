<?php
/**
 * Plugin Name:       WDOD Site Toolkit
 * Plugin URI:        https://github.com/datsenkoolena/wdod-site-toolkit
 * Description:       Maintenance and troubleshooting toolkit for WordPress: environment report, debug log viewer, staging mode (blocks outgoing mail, noindex), performance tweaks, login hardening and WP-CLI commands.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Tested up to:      7.1
 * Author:            Olena Datsenko
 * Author URI:        https://github.com/datsenkoolena
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wdod-site-toolkit
 * Domain Path:       /languages
 *
 * @package WDOD\SiteToolkit
 */

defined( 'ABSPATH' ) || exit;

// Guard against a double load (e.g. regular activation + mu-loader drop-in).
if ( defined( 'WDOD_SITE_TOOLKIT_VERSION' ) ) {
	return;
}

define( 'WDOD_SITE_TOOLKIT_VERSION', '1.0.0' );
define( 'WDOD_SITE_TOOLKIT_FILE', __FILE__ );
define( 'WDOD_SITE_TOOLKIT_DIR', plugin_dir_path( __FILE__ ) );
define( 'WDOD_SITE_TOOLKIT_URL', plugin_dir_url( __FILE__ ) );

require_once WDOD_SITE_TOOLKIT_DIR . 'includes/class-autoloader.php';

\WDOD\SiteToolkit\Autoloader::register();

/**
 * Returns the shared plugin instance.
 *
 * @return \WDOD\SiteToolkit\Plugin
 */
function wdod_site_toolkit() {
	return \WDOD\SiteToolkit\Plugin::instance();
}

register_activation_hook( __FILE__, array( '\WDOD\SiteToolkit\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\WDOD\SiteToolkit\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( wdod_site_toolkit(), 'init' ) );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'wdod', '\WDOD\SiteToolkit\CLI\Command' );
}
