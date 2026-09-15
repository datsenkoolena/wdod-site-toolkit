<?php
/**
 * Performance tweaks: emojis, embeds, XML-RPC, heartbeat, revisions.
 *
 * @package WDOD\SiteToolkit
 */

namespace WDOD\SiteToolkit\Modules;

use WDOD\SiteToolkit\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Performance.
 */
class Performance {

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
	 * Human readable descriptions of each toggle (used by admin + CLI).
	 *
	 * @return array<string, string>
	 */
	public static function toggles() {
		return array(
			'disable_emojis'  => __( 'Disable emoji detection script and styles', 'wdod-site-toolkit' ),
			'disable_embeds'  => __( 'Disable oEmbed discovery, wp-embed script and embed rewrite rules', 'wdod-site-toolkit' ),
			'disable_xmlrpc'  => __( 'Disable XML-RPC, pingbacks and the RSD link', 'wdod-site-toolkit' ),
			'limit_heartbeat' => __( 'Slow down the Heartbeat API', 'wdod-site-toolkit' ),
			'limit_revisions' => __( 'Limit the number of post revisions', 'wdod-site-toolkit' ),
		);
	}

	/**
	 * Hooks the enabled tweaks.
	 *
	 * @return void
	 */
	public function register() {
		if ( $this->settings->get( 'performance.disable_emojis' ) ) {
			add_action( 'init', array( $this, 'disable_emojis' ) );
		}

		if ( $this->settings->get( 'performance.disable_embeds' ) ) {
			add_action( 'init', array( $this, 'disable_embeds' ), 9999 );
		}

		if ( $this->settings->get( 'performance.disable_xmlrpc' ) ) {
			$this->disable_xmlrpc();
		}

		if ( $this->settings->get( 'performance.limit_heartbeat' ) ) {
			add_filter( 'heartbeat_settings', array( $this, 'heartbeat_settings' ) );
		}

		if ( $this->settings->get( 'performance.limit_revisions' ) ) {
			add_filter( 'wp_revisions_to_keep', array( $this, 'revisions_to_keep' ), 10, 2 );
		}
	}

	/**
	 * Removes every emoji-related hook WordPress registers by default.
	 *
	 * @return void
	 */
	public function disable_emojis() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_action( 'embed_head', 'print_emoji_detection_script' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		add_filter( 'tiny_mce_plugins', array( $this, 'remove_tinymce_plugin_emoji' ) );
		add_filter( 'wp_resource_hints', array( $this, 'remove_emoji_dns_prefetch' ), 10, 2 );
		add_filter( 'emoji_svg_url', '__return_false' );
	}

	/**
	 * Removes the wpemoji TinyMCE plugin.
	 *
	 * @param array $plugins TinyMCE plugins.
	 * @return array
	 */
	public function remove_tinymce_plugin_emoji( $plugins ) {
		return is_array( $plugins ) ? array_values( array_diff( $plugins, array( 'wpemoji' ) ) ) : array();
	}

	/**
	 * Removes the s.w.org DNS prefetch hint.
	 *
	 * @param array  $urls          Resource hint URLs.
	 * @param string $relation_type Relation type.
	 * @return array
	 */
	public function remove_emoji_dns_prefetch( $urls, $relation_type ) {
		if ( 'dns-prefetch' !== $relation_type ) {
			return $urls;
		}

		return array_values(
			array_filter(
				(array) $urls,
				static function ( $url ) {
					$href = is_array( $url ) && isset( $url['href'] ) ? $url['href'] : $url;

					return false === strpos( (string) $href, 's.w.org' );
				}
			)
		);
	}

	/**
	 * Disables oEmbed discovery, the wp-embed script and the /embed/ endpoint.
	 *
	 * @return void
	 */
	public function disable_embeds() {
		remove_action( 'rest_api_init', 'wp_oembed_register_route' );
		add_filter( 'embed_oembed_discover', '__return_false' );
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );

		add_filter( 'tiny_mce_plugins', array( $this, 'remove_tinymce_plugin_embed' ) );
		add_filter( 'rewrite_rules_array', array( $this, 'remove_embed_rewrite_rules' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_wp_embed' ), 100 );
		add_action( 'wp_footer', array( $this, 'dequeue_wp_embed' ), 1 );
	}

	/**
	 * Removes the wpembed TinyMCE plugin.
	 *
	 * @param array $plugins TinyMCE plugins.
	 * @return array
	 */
	public function remove_tinymce_plugin_embed( $plugins ) {
		return is_array( $plugins ) ? array_values( array_diff( $plugins, array( 'wpembed' ) ) ) : array();
	}

	/**
	 * Strips the /embed/ rewrite rules.
	 *
	 * @param array $rules Rewrite rules.
	 * @return array
	 */
	public function remove_embed_rewrite_rules( $rules ) {
		foreach ( (array) $rules as $rule => $rewrite ) {
			if ( false !== strpos( $rewrite, 'embed=true' ) ) {
				unset( $rules[ $rule ] );
			}
		}

		return $rules;
	}

	/**
	 * Dequeues the wp-embed script in case something enqueued it anyway.
	 *
	 * @return void
	 */
	public function dequeue_wp_embed() {
		wp_dequeue_script( 'wp-embed' );
	}

	/**
	 * Disables XML-RPC and removes the related headers/links.
	 *
	 * @return void
	 */
	private function disable_xmlrpc() {
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		add_filter( 'wp_headers', array( $this, 'remove_pingback_header' ) );
		add_filter( 'pings_open', '__return_false', 9999 );
		remove_action( 'wp_head', 'rsd_link' );
		add_filter( 'bloginfo_url', array( $this, 'blank_pingback_url' ), 10, 2 );
	}

	/**
	 * Removes the X-Pingback header.
	 *
	 * @param array $headers HTTP headers.
	 * @return array
	 */
	public function remove_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );

		return $headers;
	}

	/**
	 * Empties the pingback URL exposed via bloginfo('pingback_url').
	 *
	 * @param string $output Output.
	 * @param string $show   Requested info.
	 * @return string
	 */
	public function blank_pingback_url( $output, $show ) {
		return 'pingback_url' === $show ? '' : $output;
	}

	/**
	 * Applies the configured Heartbeat interval (15-120 seconds).
	 *
	 * @param array $settings Heartbeat settings.
	 * @return array
	 */
	public function heartbeat_settings( $settings ) {
		$interval = (int) $this->settings->get( 'performance.heartbeat_interval', 60 );

		$settings['interval'] = max( 15, min( 120, $interval ) );

		return $settings;
	}

	/**
	 * Caps the number of revisions kept per post.
	 *
	 * @param int      $num  Current number.
	 * @param \WP_Post $post Post object.
	 * @return int
	 */
	public function revisions_to_keep( $num, $post ) {
		unset( $post );

		$limit = (int) $this->settings->get( 'performance.revisions_to_keep', 10 );

		return max( 0, min( 100, $limit ) );
	}
}
