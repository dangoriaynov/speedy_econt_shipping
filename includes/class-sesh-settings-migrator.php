<?php
/**
 * Settings migration class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings Migrator class.
 *
 * Handles migration of settings from legacy format to new structure.
 */
class SESH_Settings_Migrator {

	/**
	 * Current settings version.
	 *
	 * @var string
	 */
	const SETTINGS_VERSION = '2.0.0';

	/**
	 * Legacy option name.
	 *
	 * @var string
	 */
	const LEGACY_OPTION = 'speedy_econt_shipping_option_name';

	/**
	 * New option names.
	 *
	 * @var array
	 */
	const NEW_OPTIONS = array(
		'speedy'  => 'sesh_speedy_settings',
		'econt'   => 'sesh_econt_settings',
		'address' => 'sesh_address_settings',
		'general' => 'sesh_general_settings',
	);

	/**
	 * Legacy to new key mapping.
	 *
	 * @var array
	 */
	private static $legacy_map = array(
		// Speedy settings.
		'enable_speedy_0'    => array( 'speedy', 'enabled' ),
		'speedy_username_0'  => array( 'speedy', 'api_username' ),
		'speedy_password_1'  => array( 'speedy', 'api_password' ),
		'speedy_free_from_6' => array( 'speedy', 'free_shipping_threshold' ),
		'speedy_shipping_7'  => array( 'speedy', 'fallback_rate' ),

		// Econt settings.
		'enable_econt_1'    => array( 'econt', 'enabled' ),
		'econt_free_from_8' => array( 'econt', 'free_shipping_threshold' ),
		'econt_shipping_9'  => array( 'econt', 'fallback_rate' ),

		// Address settings.
		'enable_address_2'     => array( 'address', 'enabled' ),
		'address_label_12'     => array( 'address', 'label' ),
		'address_free_from_10' => array( 'address', 'free_shipping_threshold' ),
		'address_shipping_11'  => array( 'address', 'fallback_rate' ),
		'address_fields_3'     => array( 'address', 'fields' ),

		// General settings.
		'additionally_hidden_fields_03' => array( 'general', 'hidden_fields' ),
		'shipping_opts_order_14'        => array( 'general', 'shipping_options_order' ),
		'emergency_contact_13'          => array( 'general', 'emergency_contact' ),
		'show_store_messages_6'         => array( 'general', 'show_store_messages' ),
		'show_deliv_opts_6'             => array( 'general', 'show_delivery_options' ),
		'calculate_final_price_8'       => array( 'general', 'calculate_final_price' ),
		'delivery_price_selector_14'    => array( 'general', 'delivery_price_selector' ),
		'email_required_9'              => array( 'general', 'email_required' ),
		'free_shipping_label_suffix_17' => array( 'general', 'free_shipping_label_suffix' ),
		'load_custom_jquery_15'         => array( 'general', 'load_custom_jquery' ),
		'address_validation_needed_16'  => array( 'general', 'address_validation_needed' ),
		'delivery_details_cart_15'      => array( 'general', 'delivery_details_cart' ),
	);

	/**
	 * Default values for new settings.
	 *
	 * @var array
	 */
	private static $defaults = array(
		'speedy'  => array(
			'enabled'                 => true,
			'api_username'            => '',
			'api_password'            => '',
			'use_dynamic_pricing'     => false,
			'fallback_rate'           => 0,
			'free_shipping_threshold' => '',
			'default_service_id'      => '',
		),
		'econt'   => array(
			'enabled'                 => true,
			'api_username'            => '',
			'api_password'            => '',
			'use_dynamic_pricing'     => false,
			'fallback_rate'           => 0,
			'free_shipping_threshold' => '',
			'default_service_type'    => 'courier_standard',
		),
		'address' => array(
			'enabled'                 => true,
			'label'                   => '',
			'fallback_rate'           => 0,
			'free_shipping_threshold' => '',
			'fields'                  => '#billing_state, #billing_city, #billing_address_1',
		),
		'general' => array(
			'hidden_fields'             => '#billing_address_2_field, #billing_company_field, #billing_country_field, #billing_postcode_field, #ship-to-different-address, .cart-subtotal, .checkout-wrap, .woocommerce-shipping-totals.shipping',
			'shipping_options_order'    => 'speedy,econt,address',
			'emergency_contact'         => '',
			'show_store_messages'       => 'speedy,econt,address',
			'show_delivery_options'     => false,
			'calculate_final_price'     => false,
			'delivery_price_selector'   => '.cart-subtotal .woocommerce-Price-amount.amount',
			'email_required'            => false,
			'free_shipping_label_suffix' => '',
			'load_custom_jquery'        => false,
			'address_validation_needed' => true,
			'delivery_details_cart'     => '<th>Доставка</th><td data-title="Доставка">Преминете към следваща стъпка за опциите на доставка</td>',
			'cart_calculator_enabled'   => true,
			'debug_mode'                => false,
			'cache_ttl'                 => 3600,
		),
	);

	/**
	 * Run migration if needed.
	 *
	 * @return bool True if migration was performed.
	 */
	public static function maybe_migrate() {
		$current_version = get_option( 'sesh_settings_version', '' );

		// No migration needed if already at current version.
		if ( version_compare( $current_version, self::SETTINGS_VERSION, '>=' ) ) {
			return false;
		}

		// Check if we have legacy settings to migrate.
		$legacy_settings = get_option( self::LEGACY_OPTION );

		if ( ! empty( $legacy_settings ) && empty( $current_version ) ) {
			// Perform migration from legacy.
			return self::migrate_from_legacy( $legacy_settings );
		}

		// New installation - set defaults.
		if ( empty( $current_version ) ) {
			return self::set_defaults();
		}

		// Future: Handle version-to-version migrations here.
		update_option( 'sesh_settings_version', self::SETTINGS_VERSION );

		return false;
	}

	/**
	 * Migrate from legacy settings.
	 *
	 * @param array $legacy_settings Legacy settings array.
	 * @return bool
	 */
	private static function migrate_from_legacy( $legacy_settings ) {
		// Start with defaults.
		$new_settings = self::$defaults;

		// Map legacy values to new structure.
		foreach ( self::$legacy_map as $legacy_key => $new_location ) {
			if ( isset( $legacy_settings[ $legacy_key ] ) ) {
				list( $group, $key ) = $new_location;
				$value = $legacy_settings[ $legacy_key ];

				// Handle special cases.
				$value = self::transform_value( $legacy_key, $value );

				$new_settings[ $group ][ $key ] = $value;
			}
		}

		// Encrypt sensitive data.
		if ( ! empty( $new_settings['speedy']['api_password'] ) ) {
			$new_settings['speedy']['api_password'] = SESH_Encryption::encrypt(
				$new_settings['speedy']['api_password']
			);
		}

		if ( ! empty( $new_settings['econt']['api_password'] ) ) {
			$new_settings['econt']['api_password'] = SESH_Encryption::encrypt(
				$new_settings['econt']['api_password']
			);
		}

		// Save new settings.
		foreach ( self::NEW_OPTIONS as $group => $option_name ) {
			update_option( $option_name, $new_settings[ $group ] );
		}

		// Mark migration complete but keep legacy as backup.
		update_option( 'sesh_settings_version', self::SETTINGS_VERSION );
		update_option( 'sesh_legacy_settings_backup', $legacy_settings );

		/**
		 * Fires after settings migration is complete.
		 *
		 * @since 2.0.0
		 * @param array $legacy_settings Original legacy settings.
		 * @param array $new_settings    Migrated settings.
		 */
		do_action( 'sesh_settings_migrated', $legacy_settings, $new_settings );

		return true;
	}

	/**
	 * Set default settings for new installation.
	 *
	 * @return bool
	 */
	private static function set_defaults() {
		foreach ( self::NEW_OPTIONS as $group => $option_name ) {
			if ( ! get_option( $option_name ) ) {
				add_option( $option_name, self::$defaults[ $group ] );
			}
		}

		update_option( 'sesh_settings_version', self::SETTINGS_VERSION );

		return true;
	}

	/**
	 * Transform value during migration.
	 *
	 * @param string $key   Legacy key.
	 * @param mixed  $value Value to transform.
	 * @return mixed
	 */
	private static function transform_value( $key, $value ) {
		// Handle boolean fields.
		$boolean_fields = array(
			'enable_speedy_0',
			'enable_econt_1',
			'enable_address_2',
			'email_required_9',
			'show_deliv_opts_6',
			'calculate_final_price_8',
			'load_custom_jquery_15',
			'address_validation_needed_16',
		);

		if ( in_array( $key, $boolean_fields, true ) ) {
			return (bool) $value;
		}

		// Handle numeric fields.
		$numeric_fields = array(
			'speedy_free_from_6',
			'speedy_shipping_7',
			'econt_free_from_8',
			'econt_shipping_9',
			'address_free_from_10',
			'address_shipping_11',
		);

		if ( in_array( $key, $numeric_fields, true ) ) {
			return '' === $value ? '' : (float) $value;
		}

		// Handle show_store_messages legacy value.
		if ( 'show_store_messages_6' === $key && '1' === $value ) {
			return 'speedy,econt,address';
		}

		return $value;
	}

	/**
	 * Rollback to legacy settings.
	 *
	 * @return bool
	 */
	public static function rollback() {
		$backup = get_option( 'sesh_legacy_settings_backup' );

		if ( empty( $backup ) ) {
			return false;
		}

		// Restore legacy settings.
		update_option( self::LEGACY_OPTION, $backup );

		// Remove new settings.
		foreach ( self::NEW_OPTIONS as $option_name ) {
			delete_option( $option_name );
		}

		// Reset version.
		delete_option( 'sesh_settings_version' );
		delete_option( 'sesh_legacy_settings_backup' );

		return true;
	}

	/**
	 * Get default settings.
	 *
	 * @param string $group Optional. Settings group.
	 * @return array
	 */
	public static function get_defaults( $group = '' ) {
		if ( $group && isset( self::$defaults[ $group ] ) ) {
			return self::$defaults[ $group ];
		}

		return self::$defaults;
	}

	/**
	 * Check if migration has been completed.
	 *
	 * @return bool
	 */
	public static function is_migrated() {
		$version = get_option( 'sesh_settings_version', '' );
		return ! empty( $version );
	}

	/**
	 * Get the current settings version.
	 *
	 * @return string
	 */
	public static function get_version() {
		return get_option( 'sesh_settings_version', '' );
	}

	/**
	 * Migrate settings to WooCommerce settings format.
	 *
	 * Migrates from plugin-specific option names to WooCommerce individual option format.
	 *
	 * @return bool True if migration performed.
	 */
	public static function migrate_to_wc_settings() {
		// Check if already migrated to WC settings format.
		if ( get_option( 'sesh_wc_settings_migrated', false ) ) {
			return false;
		}

		// Get settings from new structure.
		$speedy_settings  = get_option( 'sesh_speedy_settings', array() );
		$econt_settings   = get_option( 'sesh_econt_settings', array() );
		$address_settings = get_option( 'sesh_address_settings', array() );
		$general_settings = get_option( 'sesh_general_settings', array() );

		// Migrate Speedy settings.
		if ( ! empty( $speedy_settings ) ) {
			update_option( 'sesh_speedy_enabled', ! empty( $speedy_settings['enabled'] ) ? 'yes' : 'no' );
			update_option( 'sesh_speedy_username', $speedy_settings['api_username'] ?? '' );
			update_option( 'sesh_speedy_password', $speedy_settings['api_password'] ?? '' );
			update_option( 'sesh_speedy_dynamic_pricing', ! empty( $speedy_settings['use_dynamic_pricing'] ) ? 'yes' : 'no' );
			update_option( 'sesh_speedy_fallback_rate', $speedy_settings['fallback_rate'] ?? 0 );
			update_option( 'sesh_speedy_free_from', $speedy_settings['free_shipping_threshold'] ?? '' );
		}

		// Migrate Econt settings.
		if ( ! empty( $econt_settings ) ) {
			update_option( 'sesh_econt_enabled', ! empty( $econt_settings['enabled'] ) ? 'yes' : 'no' );
			update_option( 'sesh_econt_username', $econt_settings['api_username'] ?? '' );
			update_option( 'sesh_econt_password', $econt_settings['api_password'] ?? '' );
			update_option( 'sesh_econt_dynamic_pricing', ! empty( $econt_settings['use_dynamic_pricing'] ) ? 'yes' : 'no' );
			update_option( 'sesh_econt_fallback_rate', $econt_settings['fallback_rate'] ?? 0 );
			update_option( 'sesh_econt_free_from', $econt_settings['free_shipping_threshold'] ?? '' );
		}

		// Migrate Address settings.
		if ( ! empty( $address_settings ) ) {
			update_option( 'sesh_address_enabled', ! empty( $address_settings['enabled'] ) ? 'yes' : 'no' );
			update_option( 'sesh_address_label', $address_settings['label'] ?? __( 'address', 'speedy_econt_shipping' ) );
			update_option( 'sesh_address_fallback_rate', $address_settings['fallback_rate'] ?? 0 );
			update_option( 'sesh_address_free_from', $address_settings['free_shipping_threshold'] ?? '' );
			update_option( 'sesh_address_fields', $address_settings['fields'] ?? '#billing_state, #billing_city, #billing_address_1' );
		}

		// Migrate General settings.
		if ( ! empty( $general_settings ) ) {
			update_option( 'sesh_shipping_options_order', $general_settings['shipping_options_order'] ?? 'speedy,econt,address' );
			update_option( 'sesh_emergency_contact', $general_settings['emergency_contact'] ?? '' );
			update_option( 'sesh_free_shipping_label', $general_settings['free_shipping_label_suffix'] ?? __( 'for free', 'speedy_econt_shipping' ) );
			update_option( 'sesh_email_required', ! empty( $general_settings['email_required'] ) ? 'yes' : 'no' );
			update_option( 'sesh_address_validation', ! empty( $general_settings['address_validation_needed'] ) ? 'yes' : 'no' );
			update_option( 'sesh_hidden_fields', $general_settings['hidden_fields'] ?? '' );
			update_option( 'sesh_debug_mode', ! empty( $general_settings['debug_mode'] ) ? 'yes' : 'no' );
		}

		// Mark migration complete.
		update_option( 'sesh_wc_settings_migrated', true );

		/**
		 * Fires after WooCommerce settings migration is complete.
		 *
		 * @since 2.0.0
		 */
		do_action( 'sesh_wc_settings_migrated' );

		return true;
	}
}
