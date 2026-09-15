<?php
/**
 * Uninstall routine for WDOD Site Toolkit.
 *
 * Removes every option and transient the plugin created. Runs only when the
 * plugin is deleted from the WordPress admin, never on deactivation.
 *
 * @package WDOD\SiteToolkit
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wdod_site_toolkit_settings' );
delete_option( 'wdod_site_toolkit_blocked_mail_count' );

// Remove failed-login rate-limit transients and anything else the plugin cached.
global $wpdb;

$wdod_site_toolkit_like         = $wpdb->esc_like( '_transient_wdod_site_toolkit_' ) . '%';
$wdod_site_toolkit_like_timeout = $wpdb->esc_like( '_transient_timeout_wdod_site_toolkit_' ) . '%';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off cleanup on uninstall.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wdod_site_toolkit_like,
		$wdod_site_toolkit_like_timeout
	)
);

if ( is_multisite() ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off cleanup on uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
			$wpdb->esc_like( '_site_transient_wdod_site_toolkit_' ) . '%',
			$wpdb->esc_like( '_site_transient_timeout_wdod_site_toolkit_' ) . '%'
		)
	);
}

wp_cache_flush();
