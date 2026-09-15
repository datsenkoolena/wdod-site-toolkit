<?php
/**
 * Tools > WDOD Toolkit admin screen.
 *
 * @package WDOD\SiteToolkit
 */

namespace WDOD\SiteToolkit\Admin;

use WDOD\SiteToolkit\Debug_Log;
use WDOD\SiteToolkit\Environment_Report;
use WDOD\SiteToolkit\Modules\Login_Hardening;
use WDOD\SiteToolkit\Modules\Performance;
use WDOD\SiteToolkit\Modules\Staging_Mode;
use WDOD\SiteToolkit\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Page.
 */
class Admin_Page {

	/**
	 * Menu / page slug.
	 *
	 * @var string
	 */
	const SLUG = 'wdod-site-toolkit';

	/**
	 * Capability required for everything on this screen.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Hook suffix returned by add_management_page().
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings repository.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Registers menu, assets, actions and AJAX handlers.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wdod_site_toolkit_clear_log', array( $this, 'handle_clear_log' ) );
		add_action( 'admin_post_wdod_site_toolkit_download_log', array( $this, 'handle_download_log' ) );
		add_action( 'admin_post_wdod_site_toolkit_reset_mail_counter', array( $this, 'handle_reset_mail_counter' ) );
		add_action( 'wp_ajax_wdod_site_toolkit_log_tail', array( $this, 'ajax_log_tail' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WDOD_SITE_TOOLKIT_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Tab definitions.
	 *
	 * @return array<string, string>
	 */
	public function tabs() {
		return array(
			'environment' => __( 'Environment', 'wdod-site-toolkit' ),
			'debug-log'   => __( 'Debug Log', 'wdod-site-toolkit' ),
			'staging'     => __( 'Staging', 'wdod-site-toolkit' ),
			'performance' => __( 'Performance', 'wdod-site-toolkit' ),
			'security'    => __( 'Security', 'wdod-site-toolkit' ),
		);
	}

	/**
	 * Adds the Tools submenu.
	 *
	 * @return void
	 */
	public function add_menu() {
		$this->hook_suffix = add_management_page(
			__( 'WDOD Toolkit', 'wdod-site-toolkit' ),
			__( 'WDOD Toolkit', 'wdod-site-toolkit' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Adds a "Settings" link on the plugins list.
	 *
	 * @param string[] $links Action links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $this->page_url() ),
			esc_html__( 'Settings', 'wdod-site-toolkit' )
		);

		return $links;
	}

	/**
	 * Enqueues CSS/JS on the plugin screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'wdod-site-toolkit-admin',
			WDOD_SITE_TOOLKIT_URL . 'assets/css/admin.css',
			array(),
			WDOD_SITE_TOOLKIT_VERSION
		);

		wp_enqueue_script(
			'wdod-site-toolkit-admin',
			WDOD_SITE_TOOLKIT_URL . 'assets/js/admin.js',
			array(),
			WDOD_SITE_TOOLKIT_VERSION,
			true
		);

		wp_localize_script(
			'wdod-site-toolkit-admin',
			'wdodSiteToolkit',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'wdod_site_toolkit_log_tail' ),
				'refreshInterval' => 10000,
				'i18n'            => array(
					'copied'     => __( 'Copied!', 'wdod-site-toolkit' ),
					'copyFailed' => __( 'Copy failed - select the text and copy manually.', 'wdod-site-toolkit' ),
					'updated'    => __( 'Updated', 'wdod-site-toolkit' ),
					'error'      => __( 'Could not refresh the log.', 'wdod-site-toolkit' ),
					'empty'      => __( 'The log file is empty.', 'wdod-site-toolkit' ),
					'missing'    => __( 'No debug.log file exists yet.', 'wdod-site-toolkit' ),
				),
			)
		);
	}

	/**
	 * Renders the page with its tabs.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wdod-site-toolkit' ), 403 );
		}

		$tabs    = $this->tabs();
		$current = $this->current_tab();

		echo '<div class="wrap wdod-toolkit">';
		echo '<h1>' . esc_html__( 'WDOD Site Toolkit', 'wdod-site-toolkit' ) . '</h1>';

		$this->render_notices();
		settings_errors();

		echo '<nav class="nav-tab-wrapper wdod-toolkit__tabs">';

		foreach ( $tabs as $slug => $label ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( $this->page_url( $slug ) ),
				$slug === $current ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}

		echo '</nav>';

		echo '<div class="wdod-toolkit__panel">';

		switch ( $current ) {
			case 'debug-log':
				$this->render_debug_log_tab();
				break;
			case 'staging':
				$this->render_staging_tab();
				break;
			case 'performance':
				$this->render_performance_tab();
				break;
			case 'security':
				$this->render_security_tab();
				break;
			case 'environment':
			default:
				$this->render_environment_tab();
				break;
		}

		echo '</div></div>';
	}

	/**
	 * Environment tab.
	 *
	 * @return void
	 */
	private function render_environment_tab() {
		$environment = new Environment_Report();
		$report      = $environment->to_array();
		$report_text = $environment->to_text();

		include WDOD_SITE_TOOLKIT_DIR . 'includes/admin/views/environment.php';
	}

	/**
	 * Debug log tab.
	 *
	 * @return void
	 */
	private function render_debug_log_tab() {
		$log          = new Debug_Log();
		$tail         = $log->tail();
		$log_path     = $log->get_path();
		$log_exists   = $log->exists();
		$log_writable = $log->is_writable();
		$log_size     = $log->size();
		$clear_url    = $this->action_url( 'wdod_site_toolkit_clear_log' );
		$download_url = $this->action_url( 'wdod_site_toolkit_download_log' );
		$debug_log_on = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;

		include WDOD_SITE_TOOLKIT_DIR . 'includes/admin/views/debug-log.php';
	}

	/**
	 * Staging tab.
	 *
	 * @return void
	 */
	private function render_staging_tab() {
		$enabled   = (bool) $this->settings->get( 'staging.enabled' );
		$count     = Staging_Mode::blocked_count();
		$allowlist = array();

		if ( $enabled ) {
			$module    = new Staging_Mode( $this->settings );
			$allowlist = $module->allowlist();
		}

		echo '<div class="wdod-toolkit__status wdod-toolkit__status--' . ( $enabled ? 'on' : 'off' ) . '">';
		echo '<p><strong>' . esc_html__( 'Status:', 'wdod-site-toolkit' ) . '</strong> ';
		echo $enabled ? esc_html__( 'Staging mode is ON', 'wdod-site-toolkit' ) : esc_html__( 'Staging mode is OFF', 'wdod-site-toolkit' );
		echo '</p>';

		printf(
			'<p>%s <span class="wdod-toolkit__counter">%s</span> <a class="button button-small" href="%s">%s</a></p>',
			esc_html__( 'Emails blocked so far:', 'wdod-site-toolkit' ),
			esc_html( number_format_i18n( $count ) ),
			esc_url( $this->action_url( 'wdod_site_toolkit_reset_mail_counter' ) ),
			esc_html__( 'Reset counter', 'wdod-site-toolkit' )
		);

		if ( $enabled ) {
			$items = array();

			foreach ( $allowlist as $email ) {
				$items[] = '<code>' . esc_html( $email ) . '</code>';
			}

			echo '<p>' . esc_html__( 'Allowlisted recipients (filter wdod_site_toolkit_mail_allowlist):', 'wdod-site-toolkit' ) . ' ';
			echo $items ? wp_kses( implode( ', ', $items ), array( 'code' => array() ) ) : esc_html__( 'none', 'wdod-site-toolkit' );
			echo '</p>';
		}

		echo '</div>';

		$this->render_settings_tab(
			'staging',
			__( 'Staging mode', 'wdod-site-toolkit' ),
			__( 'Turn this on for staging/development copies of a site. Outgoing email is blocked (wp_mail() and PHPMailer), the site is set to noindex/nofollow, "Discourage search engines" is forced on and a red STAGING badge appears in the admin bar.', 'wdod-site-toolkit' ),
			array(
				'enabled' => array(
					'type'        => 'checkbox',
					'label'       => __( 'Enable staging mode', 'wdod-site-toolkit' ),
					'description' => __( 'Blocks outgoing mail, adds noindex and shows the admin bar badge.', 'wdod-site-toolkit' ),
				),
			)
		);
	}

	/**
	 * Performance tab.
	 *
	 * @return void
	 */
	private function render_performance_tab() {
		$labels = Performance::toggles();
		$fields = array();

		foreach ( $labels as $key => $label ) {
			$fields[ $key ] = array(
				'type'  => 'checkbox',
				'label' => $label,
			);
		}

		$fields['heartbeat_interval'] = array(
			'type'        => 'number',
			'label'       => __( 'Heartbeat interval (seconds)', 'wdod-site-toolkit' ),
			'description' => __( 'Between 15 and 120. Applies only when the Heartbeat toggle above is on.', 'wdod-site-toolkit' ),
			'min'         => 15,
			'max'         => 120,
			'step'        => 1,
		);

		$fields['revisions_to_keep'] = array(
			'type'        => 'number',
			'label'       => __( 'Revisions to keep per post', 'wdod-site-toolkit' ),
			'description' => __( '0 disables revisions. Applies only when the revisions toggle above is on.', 'wdod-site-toolkit' ),
			'min'         => 0,
			'max'         => 100,
			'step'        => 1,
		);

		$this->render_settings_tab(
			'performance',
			__( 'Performance tweaks', 'wdod-site-toolkit' ),
			__( 'Small, safe optimisations that remove things most sites never use. Every option can be switched off again at any time.', 'wdod-site-toolkit' ),
			$fields
		);
	}

	/**
	 * Security tab.
	 *
	 * @return void
	 */
	private function render_security_tab() {
		$fields = array();

		foreach ( Login_Hardening::toggles() as $key => $label ) {
			$fields[ $key ] = array(
				'type'  => 'checkbox',
				'label' => $label,
			);
		}

		$fields['log_failed_logins']['description']    = __( 'Writes to the PHP error log (debug.log when WP_DEBUG_LOG is on). At most 10 lines per IP every 15 minutes.', 'wdod-site-toolkit' );
		$fields['generic_login_errors']['description'] = __( 'Customise the message with the wdod_site_toolkit_login_error_message filter.', 'wdod-site-toolkit' );

		$this->render_settings_tab(
			'security',
			__( 'Login hardening', 'wdod-site-toolkit' ),
			__( 'Reduces the information an attacker can collect from the login form and public endpoints. This is not a replacement for strong passwords or 2FA.', 'wdod-site-toolkit' ),
			$fields
		);
	}

	/**
	 * Renders one settings section through the shared view.
	 *
	 * @param string $section     Section slug (matches Settings::schema()).
	 * @param string $title       Section title.
	 * @param string $description Intro paragraph.
	 * @param array  $fields      Field definitions.
	 * @return void
	 */
	private function render_settings_tab( $section, $title, $description, array $fields ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- All parameters are consumed by the included view.
		$values      = (array) $this->settings->get( $section, array() );
		$option_name = Settings::OPTION_NAME;
		$group       = Settings::OPTION_GROUP;

		include WDOD_SITE_TOOLKIT_DIR . 'includes/admin/views/settings-tab.php';
	}

	/**
	 * Handles the "Clear log" action.
	 *
	 * @return void
	 */
	public function handle_clear_log() {
		$this->verify_action( 'wdod_site_toolkit_clear_log' );

		$log    = new Debug_Log();
		$notice = $log->clear() ? 'log-cleared' : 'log-clear-failed';

		$this->redirect_back( 'debug-log', $notice );
	}

	/**
	 * Handles the "Download log" action.
	 *
	 * @return void
	 */
	public function handle_download_log() {
		$this->verify_action( 'wdod_site_toolkit_download_log' );

		$log = new Debug_Log();
		$log->download();
	}

	/**
	 * Resets the blocked-mail counter.
	 *
	 * @return void
	 */
	public function handle_reset_mail_counter() {
		$this->verify_action( 'wdod_site_toolkit_reset_mail_counter' );

		Staging_Mode::reset_counter();

		$this->redirect_back( 'staging', 'counter-reset' );
	}

	/**
	 * AJAX: returns the tail of the debug log for the auto-refresh.
	 *
	 * @return void
	 */
	public function ajax_log_tail() {
		check_ajax_referer( 'wdod_site_toolkit_log_tail', 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'wdod-site-toolkit' ) ), 403 );
		}

		$log  = new Debug_Log();
		$tail = $log->tail();

		wp_send_json_success(
			array(
				'exists'    => $tail['exists'],
				'size'      => $tail['size'],
				'sizeHuman' => $tail['exists'] ? size_format( $tail['size'] ) : '',
				'truncated' => $tail['truncated'],
				'content'   => $tail['content'],
				'time'      => wp_date( get_option( 'time_format' ) ),
			)
		);
	}

	/**
	 * Verifies nonce + capability for an admin-post action.
	 *
	 * @param string $action Action / nonce name.
	 * @return void
	 */
	private function verify_action( $action ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wdod-site-toolkit' ), 403 );
		}

		check_admin_referer( $action );
	}

	/**
	 * Builds a nonce'd admin-post URL.
	 *
	 * @param string $action Action name.
	 * @return string
	 */
	private function action_url( $action ) {
		return wp_nonce_url( add_query_arg( 'action', $action, admin_url( 'admin-post.php' ) ), $action );
	}

	/**
	 * Redirects back to a tab with a notice code.
	 *
	 * @param string $tab    Tab slug.
	 * @param string $notice Notice code.
	 * @return void
	 */
	private function redirect_back( $tab, $notice ) {
		wp_safe_redirect( add_query_arg( 'wdod-notice', $notice, $this->page_url( $tab ) ) );
		exit;
	}

	/**
	 * URL of the admin page, optionally for a given tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public function page_url( $tab = '' ) {
		$url = admin_url( 'tools.php?page=' . self::SLUG );

		return $tab ? add_query_arg( 'tab', $tab, $url ) : $url;
	}

	/**
	 * Returns the current tab slug.
	 *
	 * @return string
	 */
	private function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only, no state change.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'environment';

		return array_key_exists( $tab, $this->tabs() ) ? $tab : 'environment';
	}

	/**
	 * Prints notices for admin-post actions.
	 *
	 * @return void
	 */
	private function render_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Notice code only; the action itself was nonce-verified.
		$code = isset( $_GET['wdod-notice'] ) ? sanitize_key( wp_unslash( $_GET['wdod-notice'] ) ) : '';

		if ( '' === $code ) {
			return;
		}

		$notices = array(
			'log-cleared'      => array( 'success', __( 'The debug log has been cleared.', 'wdod-site-toolkit' ) ),
			'log-clear-failed' => array( 'error', __( 'The debug log could not be cleared. Check the file permissions.', 'wdod-site-toolkit' ) ),
			'counter-reset'    => array( 'success', __( 'The blocked-mail counter has been reset.', 'wdod-site-toolkit' ) ),
		);

		if ( ! isset( $notices[ $code ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $notices[ $code ][0] ),
			esc_html( $notices[ $code ][1] )
		);
	}
}
