<?php
/**
 * Settings repository: a single array option with defaults and sanitisation.
 *
 * @package WDOD\SiteToolkit
 */

namespace WDOD\SiteToolkit;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings.
 */
class Settings {

	/**
	 * Option name that stores every setting of the plugin.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'wdod_site_toolkit_settings';

	/**
	 * Settings API group used by options.php.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'wdod_site_toolkit';

	/**
	 * In-memory cache of the merged settings.
	 *
	 * @var array|null
	 */
	private $cache = null;

	/**
	 * Registers the option with the Settings API so that options.php and
	 * update_option() both run the sanitiser.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_setting' ) );
	}

	/**
	 * Calls register_setting().
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'description'       => __( 'WDOD Site Toolkit settings.', 'wdod-site-toolkit' ),
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => $this->defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Returns the default settings, filterable via wdod_site_toolkit_settings_defaults.
	 *
	 * @return array
	 */
	public function defaults() {
		$defaults = array(
			'staging'     => array(
				'enabled' => false,
			),
			'performance' => array(
				'disable_emojis'     => false,
				'disable_embeds'     => false,
				'disable_xmlrpc'     => false,
				'limit_heartbeat'    => false,
				'heartbeat_interval' => 60,
				'limit_revisions'    => false,
				'revisions_to_keep'  => 10,
			),
			'security'    => array(
				'generic_login_errors'     => false,
				'block_author_enumeration' => false,
				'block_rest_users'         => false,
				'log_failed_logins'        => false,
			),
		);

		/**
		 * Filters the default plugin settings.
		 *
		 * @param array $defaults Default settings, grouped by section.
		 */
		$filtered = apply_filters( 'wdod_site_toolkit_settings_defaults', $defaults );

		return is_array( $filtered ) ? $this->merge( $defaults, $filtered ) : $defaults;
	}

	/**
	 * Returns the field schema used by the sanitiser and the admin forms.
	 *
	 * @return array<string, array<string, array>>
	 */
	public function schema() {
		return array(
			'staging'     => array(
				'enabled' => array( 'type' => 'bool' ),
			),
			'performance' => array(
				'disable_emojis'     => array( 'type' => 'bool' ),
				'disable_embeds'     => array( 'type' => 'bool' ),
				'disable_xmlrpc'     => array( 'type' => 'bool' ),
				'limit_heartbeat'    => array( 'type' => 'bool' ),
				'heartbeat_interval' => array(
					'type' => 'int',
					'min'  => 15,
					'max'  => 120,
				),
				'limit_revisions'    => array( 'type' => 'bool' ),
				'revisions_to_keep'  => array(
					'type' => 'int',
					'min'  => 0,
					'max'  => 100,
				),
			),
			'security'    => array(
				'generic_login_errors'     => array( 'type' => 'bool' ),
				'block_author_enumeration' => array( 'type' => 'bool' ),
				'block_rest_users'         => array( 'type' => 'bool' ),
				'log_failed_logins'        => array( 'type' => 'bool' ),
			),
		);
	}

	/**
	 * Returns all settings, or a single value via dot notation ("performance.disable_emojis").
	 *
	 * @param string|null $key     Dot-notation key or null for everything.
	 * @param mixed       $fallback Value returned when the key does not exist.
	 * @return mixed
	 */
	public function get( $key = null, $fallback = null ) {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION_NAME, array() );
			$this->cache = $this->merge( $this->defaults(), is_array( $stored ) ? $stored : array() );
		}

		if ( null === $key ) {
			return $this->cache;
		}

		$value = $this->cache;

		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $fallback;
			}

			$value = $value[ $segment ];
		}

		return $value;
	}

	/**
	 * Merges a partial settings array into the stored option.
	 *
	 * @param array $partial Partial settings, grouped by section.
	 * @return bool True when the option changed.
	 */
	public function update( array $partial ) {
		$current = $this->get();
		$next    = $this->sanitize( $this->merge( $current, $partial ) );

		$this->cache = null;

		return update_option( self::OPTION_NAME, $next );
	}

	/**
	 * Tells whether any boolean toggle in a section is switched on.
	 *
	 * @param string $section Section slug.
	 * @return bool
	 */
	public function has_enabled_toggle( $section ) {
		$schema = $this->schema();
		$values = $this->get( $section, array() );

		if ( empty( $schema[ $section ] ) || ! is_array( $values ) ) {
			return false;
		}

		foreach ( $schema[ $section ] as $key => $field ) {
			if ( 'bool' === $field['type'] && ! empty( $values[ $key ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sanitises incoming settings.
	 *
	 * Sections that are not part of $input keep their stored values. Inside a
	 * submitted section every known key is sanitised; unchecked checkboxes are
	 * therefore correctly stored as false.
	 *
	 * @param mixed $input Raw settings, usually from options.php or update().
	 * @return array
	 */
	public function sanitize( $input ) {
		$schema  = $this->schema();
		$current = $this->cache;

		if ( null === $current ) {
			$stored  = get_option( self::OPTION_NAME, array() );
			$current = $this->merge( $this->defaults(), is_array( $stored ) ? $stored : array() );
		}

		if ( ! is_array( $input ) ) {
			return $current;
		}

		$output = $current;

		foreach ( $schema as $section => $fields ) {
			if ( ! isset( $input[ $section ] ) || ! is_array( $input[ $section ] ) ) {
				continue;
			}

			$raw = $input[ $section ];

			foreach ( $fields as $key => $field ) {
				$value = isset( $raw[ $key ] ) ? $raw[ $key ] : null;

				if ( 'bool' === $field['type'] ) {
					$output[ $section ][ $key ] = ! empty( $value ) && 'false' !== $value && '0' !== $value;
					continue;
				}

				if ( 'int' === $field['type'] ) {
					if ( null === $value || '' === $value ) {
						$value = $output[ $section ][ $key ];
					}

					$value = (int) $value;

					if ( isset( $field['min'] ) ) {
						$value = max( (int) $field['min'], $value );
					}

					if ( isset( $field['max'] ) ) {
						$value = min( (int) $field['max'], $value );
					}

					$output[ $section ][ $key ] = $value;
				}
			}
		}

		$this->cache = null;

		return $output;
	}

	/**
	 * Recursively merges arrays while keeping the left-hand keys authoritative.
	 *
	 * @param array $base     Base array.
	 * @param array $override Overriding values.
	 * @return array
	 */
	private function merge( array $base, array $override ) {
		foreach ( $override as $key => $value ) {
			if ( isset( $base[ $key ] ) && is_array( $base[ $key ] ) && is_array( $value ) ) {
				$base[ $key ] = $this->merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}

		return $base;
	}
}
