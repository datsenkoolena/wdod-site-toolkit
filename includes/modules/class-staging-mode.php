<?php
/**
 * Staging mode: block outgoing mail, discourage indexing and show a badge.
 *
 * @package WDOD\SiteToolkit
 */

namespace WDOD\SiteToolkit\Modules;

use WDOD\SiteToolkit\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Staging_Mode.
 */
class Staging_Mode {

	/**
	 * Option that counts blocked emails.
	 *
	 * @var string
	 */
	const COUNTER_OPTION = 'wdod_site_toolkit_blocked_mail_count';

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
	 * Hooks everything up.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'pre_wp_mail', array( $this, 'block_mail' ), 10, 2 );
		add_action( 'phpmailer_init', array( $this, 'strip_recipients' ), PHP_INT_MAX );
		add_filter( 'wp_robots', array( $this, 'robots_noindex' ), PHP_INT_MAX );
		add_filter( 'pre_option_blog_public', array( $this, 'discourage_indexing' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_badge' ), 1 );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
	}

	/**
	 * Returns the list of addresses that are still allowed to receive mail.
	 *
	 * @return string[] Lower-cased email addresses.
	 */
	public function allowlist() {
		/**
		 * Filters the email addresses that keep receiving mail while staging mode is on.
		 *
		 * @param string[] $allowlist Email addresses.
		 */
		$allowlist = apply_filters( 'wdod_site_toolkit_mail_allowlist', array() );

		$clean = array();

		foreach ( (array) $allowlist as $email ) {
			$email = strtolower( trim( (string) $email ) );

			if ( is_email( $email ) ) {
				$clean[] = $email;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Whether an address may receive mail.
	 *
	 * @param string $email Email address, optionally in "Name <email>" form.
	 * @return bool
	 */
	public function is_allowed( $email ) {
		$email = strtolower( trim( (string) $email ) );

		if ( preg_match( '/<([^>]+)>/', $email, $matches ) ) {
			$email = trim( $matches[1] );
		}

		return in_array( $email, $this->allowlist(), true );
	}

	/**
	 * Short-circuits wp_mail() unless every recipient is on the allowlist.
	 *
	 * @param null|bool $short_circuit Short-circuit value (null = continue).
	 * @param array     $atts          wp_mail() arguments.
	 * @return null|bool
	 */
	public function block_mail( $short_circuit, $atts ) {
		if ( null !== $short_circuit ) {
			return $short_circuit;
		}

		$to = isset( $atts['to'] ) ? $atts['to'] : array();

		if ( ! is_array( $to ) ) {
			$to = explode( ',', (string) $to );
		}

		$to = array_filter( array_map( 'trim', $to ) );

		if ( ! empty( $to ) ) {
			$all_allowed = true;

			foreach ( $to as $address ) {
				if ( ! $this->is_allowed( $address ) ) {
					$all_allowed = false;
					break;
				}
			}

			if ( $all_allowed ) {
				return null;
			}
		}

		$this->increment_counter();

		return false;
	}

	/**
	 * Safety net for code that talks to PHPMailer directly: removes any
	 * recipient that is not on the allowlist.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 * @return void
	 */
	public function strip_recipients( $phpmailer ) {
		if ( ! is_object( $phpmailer ) || ! method_exists( $phpmailer, 'clearAllRecipients' ) ) {
			return;
		}

		$keep = array(
			'to'  => array(),
			'cc'  => array(),
			'bcc' => array(),
		);

		$removed = 0;

		foreach ( array( 'to', 'cc', 'bcc' ) as $type ) {
			$getter = 'get' . ucfirst( $type ) . 'Addresses';

			if ( ! method_exists( $phpmailer, $getter ) ) {
				continue;
			}

			foreach ( (array) $phpmailer->$getter() as $recipient ) {
				$address = isset( $recipient[0] ) ? $recipient[0] : '';
				$name    = isset( $recipient[1] ) ? $recipient[1] : '';

				if ( $this->is_allowed( $address ) ) {
					$keep[ $type ][] = array( $address, $name );
				} else {
					++$removed;
				}
			}
		}

		if ( 0 === $removed ) {
			return;
		}

		$phpmailer->clearAllRecipients();

		foreach ( $keep['to'] as $recipient ) {
			$phpmailer->addAddress( $recipient[0], $recipient[1] );
		}

		foreach ( $keep['cc'] as $recipient ) {
			$phpmailer->addCC( $recipient[0], $recipient[1] );
		}

		foreach ( $keep['bcc'] as $recipient ) {
			$phpmailer->addBCC( $recipient[0], $recipient[1] );
		}

		$this->increment_counter();
	}

	/**
	 * Adds noindex/nofollow to the robots meta tag.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public function robots_noindex( $robots ) {
		unset( $robots['index'], $robots['follow'], $robots['max-image-preview'] );

		$robots['noindex']  = true;
		$robots['nofollow'] = true;

		return $robots;
	}

	/**
	 * Forces "Discourage search engines" on.
	 *
	 * @return string
	 */
	public function discourage_indexing() {
		return '0';
	}

	/**
	 * Adds a red STAGING badge to the admin bar.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar.
	 * @return void
	 */
	public function admin_bar_badge( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$style = 'display:inline-block;background:#d63638;color:#fff;padding:0 8px;line-height:20px;margin-top:6px;border-radius:2px;font-weight:600;letter-spacing:.04em;';

		$wp_admin_bar->add_node(
			array(
				'id'    => 'wdod-site-toolkit-staging',
				'title' => '<span style="' . esc_attr( $style ) . '">' . esc_html__( 'STAGING', 'wdod-site-toolkit' ) . '</span>',
				'href'  => admin_url( 'tools.php?page=wdod-site-toolkit&tab=staging' ),
				'meta'  => array(
					'title' => __( 'Staging mode is on: outgoing mail is blocked and the site is set to noindex.', 'wdod-site-toolkit' ),
				),
			)
		);
	}

	/**
	 * Reminds administrators on the plugin's own screen that mail is blocked.
	 *
	 * @return void
	 */
	public function admin_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'tools_page_wdod-site-toolkit' !== $screen->id || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Staging mode is active. Outgoing email is blocked (except allowlisted addresses) and search engines are told not to index this site.', 'wdod-site-toolkit' )
		);
	}

	/**
	 * Number of emails blocked so far.
	 *
	 * @return int
	 */
	public static function blocked_count() {
		return (int) get_option( self::COUNTER_OPTION, 0 );
	}

	/**
	 * Resets the counter.
	 *
	 * @return void
	 */
	public static function reset_counter() {
		update_option( self::COUNTER_OPTION, 0, false );
	}

	/**
	 * Increments the blocked-mail counter.
	 *
	 * @return void
	 */
	private function increment_counter() {
		update_option( self::COUNTER_OPTION, self::blocked_count() + 1, false );
	}
}
