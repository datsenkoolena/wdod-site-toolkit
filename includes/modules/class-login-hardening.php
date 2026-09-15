<?php
/**
 * Login hardening: generic errors, user enumeration protection, failure logging.
 *
 * @package WDOD\SiteToolkit
 */

namespace WDOD\SiteToolkit\Modules;

use WDOD\SiteToolkit\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Login_Hardening.
 */
class Login_Hardening {

	/**
	 * How many failed attempts per IP are logged inside one window.
	 *
	 * @var int
	 */
	const LOG_LIMIT = 10;

	/**
	 * Rate-limit window in seconds.
	 *
	 * @var int
	 */
	const LOG_WINDOW = 900;

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings repository.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Human readable descriptions of each toggle.
	 *
	 * @return array<string, string>
	 */
	public static function toggles() {
		return array(
			'generic_login_errors'     => __( 'Replace detailed login errors with a generic message', 'wdod-site-toolkit' ),
			'block_author_enumeration' => __( 'Block ?author=N user enumeration for visitors', 'wdod-site-toolkit' ),
			'block_rest_users'         => __( 'Hide the REST API users endpoint from visitors', 'wdod-site-toolkit' ),
			'log_failed_logins'        => __( 'Log failed login attempts (with IP) to the PHP error log', 'wdod-site-toolkit' ),
		);
	}

	/**
	 * Hooks the enabled protections.
	 *
	 * @return void
	 */
	public function register() {
		if ( $this->settings->get( 'security.generic_login_errors' ) ) {
			add_filter( 'login_errors', array( $this, 'generic_login_error' ), PHP_INT_MAX );
		}

		if ( $this->settings->get( 'security.block_author_enumeration' ) ) {
			add_action( 'template_redirect', array( $this, 'block_author_enumeration' ), 1 );
		}

		if ( $this->settings->get( 'security.block_rest_users' ) ) {
			add_filter( 'rest_endpoints', array( $this, 'hide_rest_users' ) );
		}

		if ( $this->settings->get( 'security.log_failed_logins' ) ) {
			add_action( 'wp_login_failed', array( $this, 'log_failed_login' ), 10, 2 );
		}
	}

	/**
	 * Returns a generic login error so usernames cannot be probed.
	 *
	 * @param string $error Original error markup.
	 * @return string
	 */
	public function generic_login_error( $error ) {
		unset( $error );

		/**
		 * Filters the generic message shown on failed logins.
		 *
		 * @param string $message Message (already escaped when output).
		 */
		$message = apply_filters(
			'wdod_site_toolkit_login_error_message',
			__( 'The username or password you entered is incorrect.', 'wdod-site-toolkit' )
		);

		return '<strong>' . esc_html__( 'Error:', 'wdod-site-toolkit' ) . '</strong> ' . esc_html( $message );
	}

	/**
	 * Redirects ?author=N requests from anonymous visitors to the home page.
	 *
	 * Hooked before redirect_canonical() so the numeric ID is never resolved to
	 * a pretty /author/username/ URL.
	 *
	 * @return void
	 */
	public function block_author_enumeration() {
		if ( is_user_logged_in() || ! is_author() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of a public query string.
		$raw = isset( $_GET['author'] ) ? sanitize_text_field( wp_unslash( $_GET['author'] ) ) : '';

		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return;
		}

		wp_safe_redirect( home_url( '/' ), 302 );
		exit;
	}

	/**
	 * Removes the users endpoints from the REST API for anonymous requests.
	 *
	 * @param array $endpoints Registered REST endpoints.
	 * @return array
	 */
	public function hide_rest_users( $endpoints ) {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}

		foreach ( array_keys( $endpoints ) as $route ) {
			if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}

		return $endpoints;
	}

	/**
	 * Logs a failed login with the client IP, at most LOG_LIMIT times per window per IP.
	 *
	 * @param string         $username Username or email that was tried.
	 * @param \WP_Error|null $error    Error object (WP 5.4+).
	 * @return void
	 */
	public function log_failed_login( $username, $error = null ) {
		$ip  = $this->client_ip();
		$key = 'wdod_site_toolkit_flog_' . md5( $ip );

		$count = (int) get_transient( $key );

		if ( $count >= self::LOG_LIMIT ) {
			return;
		}

		++$count;

		set_transient( $key, $count, self::LOG_WINDOW );

		$code = $error instanceof \WP_Error ? $error->get_error_code() : 'unknown';

		$message = sprintf(
			'[WDOD Site Toolkit] Failed login for "%s" from %s (%s)',
			sanitize_user( (string) $username, true ),
			$ip,
			$code
		);

		if ( self::LOG_LIMIT === $count ) {
			$message .= sprintf( ' - further attempts from this IP are not logged for %d minutes', (int) ( self::LOG_WINDOW / 60 ) );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional: the feature is "log failed logins to the PHP error log".
		error_log( $message );
	}

	/**
	 * Returns the remote address. REMOTE_ADDR is used on purpose: forwarded
	 * headers are only trustworthy behind a known proxy, which is the host's job.
	 *
	 * @return string
	 */
	private function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( '' === $ip || false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return 'unknown';
		}

		return $ip;
	}
}
