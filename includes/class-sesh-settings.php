<?php
/**
 * Settings handler class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings class.
 *
 * Handles all plugin settings with support for both legacy
 * and new settings structures.
 */
class SESH_Settings {

	/**
	 * Legacy option name (for backward compatibility).
	 *
	 * @var string
	 */
	const LEGACY_OPTION_NAME = 'speedy_econt_shipping_option_name';

	/**
	 * New option names.
	 *
	 * @var array
	 */
	const OPTION_NAMES = array(
		'speedy'  => 'sesh_speedy_settings',
		'econt'   => 'sesh_econt_settings',
		'address' => 'sesh_address_settings',
		'general' => 'sesh_general_settings',
		'sender'  => 'sesh_sender_settings',
	);

	/**
	 * Cached settings by group.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Whether using new settings structure.
	 *
	 * @var bool
	 */
	private $using_new_structure = false;

	/**
	 * WooCommerce option name mapping.
	 *
	 * Maps internal group.key to WC option names.
	 *
	 * @var array
	 */
	private $wc_option_map = array(
		'speedy.enabled'                   => array( 'sesh_speedy_enabled', 'checkbox' ),
		'speedy.api_username'              => array( 'sesh_speedy_username', 'text' ),
		'speedy.api_password'              => array( 'sesh_speedy_password', 'text' ),
		'speedy.use_dynamic_pricing'       => array( 'sesh_speedy_dynamic_pricing', 'checkbox' ),
		'speedy.fallback_rate'             => array( 'sesh_speedy_fallback_rate', 'price' ),
		'speedy.free_shipping_threshold'   => array( 'sesh_speedy_free_from', 'price' ),
		'econt.enabled'                    => array( 'sesh_econt_enabled', 'checkbox' ),
		'econt.api_username'               => array( 'sesh_econt_username', 'text' ),
		'econt.api_password'               => array( 'sesh_econt_password', 'text' ),
		'econt.use_dynamic_pricing'        => array( 'sesh_econt_dynamic_pricing', 'checkbox' ),
		'econt.fallback_rate'              => array( 'sesh_econt_fallback_rate', 'price' ),
		'econt.free_shipping_threshold'    => array( 'sesh_econt_free_from', 'price' ),
		'address.enabled'                  => array( 'sesh_address_enabled', 'checkbox' ),
		'address.label'                    => array( 'sesh_address_label', 'text' ),
		'address.fallback_rate'            => array( 'sesh_address_fallback_rate', 'price' ),
		'address.free_shipping_threshold'  => array( 'sesh_address_free_from', 'price' ),
		'address.fields'                   => array( 'sesh_address_fields', 'text' ),
		'general.shipping_options_order'   => array( 'sesh_shipping_options_order', 'text' ),
		'general.emergency_contact'        => array( 'sesh_emergency_contact', 'text' ),
		'general.free_shipping_label_suffix' => array( 'sesh_free_shipping_label', 'text' ),
		'general.email_required'           => array( 'sesh_email_required', 'checkbox' ),
		'general.address_validation_needed' => array( 'sesh_address_validation', 'checkbox' ),
		'general.hidden_fields'            => array( 'sesh_hidden_fields', 'textarea' ),
		'general.debug_mode'               => array( 'sesh_debug_mode', 'checkbox' ),
		'general.auto_generate_labels'     => array( 'sesh_auto_generate_labels', 'checkbox' ),
		'general.auto_generate_status'     => array( 'sesh_auto_generate_status', 'text' ),
		'sender.sender_name'               => array( 'sesh_sender_name', 'text' ),
		'sender.sender_phone'              => array( 'sesh_sender_phone', 'text' ),
		'sender.sender_email'              => array( 'sesh_sender_email', 'text' ),
		'sender.sender_region'             => array( 'sesh_sender_region', 'text' ),
		'sender.sender_city'               => array( 'sesh_sender_city', 'text' ),
		'sender.sender_address'            => array( 'sesh_sender_address', 'textarea' ),
		'sender.sender_postcode'           => array( 'sesh_sender_postcode', 'text' ),
	);

	/**
	 * Legacy key to new structure mapping.
	 *
	 * @var array
	 */
	private $legacy_key_map = array(
		'enable_speedy_0'               => array( 'speedy', 'enabled' ),
		'speedy_username_0'             => array( 'speedy', 'api_username' ),
		'speedy_password_1'             => array( 'speedy', 'api_password' ),
		'speedy_free_from_6'            => array( 'speedy', 'free_shipping_threshold' ),
		'speedy_shipping_7'             => array( 'speedy', 'fallback_rate' ),
		'enable_econt_1'                => array( 'econt', 'enabled' ),
		'econt_free_from_8'             => array( 'econt', 'free_shipping_threshold' ),
		'econt_shipping_9'              => array( 'econt', 'fallback_rate' ),
		'enable_address_2'              => array( 'address', 'enabled' ),
		'address_label_12'              => array( 'address', 'label' ),
		'address_free_from_10'          => array( 'address', 'free_shipping_threshold' ),
		'address_shipping_11'           => array( 'address', 'fallback_rate' ),
		'address_fields_3'              => array( 'address', 'fields' ),
		'additionally_hidden_fields_03' => array( 'general', 'hidden_fields' ),
		'shipping_opts_order_14'        => array( 'general', 'shipping_options_order' ),
		'emergency_contact_13'          => array( 'general', 'emergency_contact' ),
		'econt_username'                => array( 'econt', 'api_username' ),
		'econt_password'                => array( 'econt', 'api_password' ),
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
	 * Constructor.
	 */
	public function __construct() {
		$this->load_settings();

		// Initialize settings page in admin.
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}
	}

	/**
	 * Load settings from database.
	 */
	private function load_settings() {
		// Check if new settings structure exists.
		$version = get_option( 'sesh_settings_version', '' );

		if ( ! empty( $version ) ) {
			$this->using_new_structure = true;
			$this->load_new_settings();
		} else {
			$this->load_legacy_settings();
		}
	}

	/**
	 * Load settings from new structure.
	 */
	private function load_new_settings() {
		$defaults = SESH_Settings_Migrator::get_defaults();

		foreach ( self::OPTION_NAMES as $group => $option_name ) {
			$this->settings[ $group ] = wp_parse_args(
				get_option( $option_name, array() ),
				$defaults[ $group ] ?? array()
			);
		}
	}

	/**
	 * Load settings from legacy structure.
	 */
	private function load_legacy_settings() {
		$legacy_settings = get_option( self::LEGACY_OPTION_NAME, array() );
		$defaults        = SESH_Settings_Migrator::get_defaults();

		// Initialize with defaults.
		$this->settings = $defaults;

		// Map legacy values.
		if ( ! empty( $legacy_settings ) ) {
			foreach ( $this->legacy_key_map as $legacy_key => $new_location ) {
				if ( isset( $legacy_settings[ $legacy_key ] ) ) {
					list( $group, $key )                = $new_location;
					$this->settings[ $group ][ $key ] = $legacy_settings[ $legacy_key ];
				}
			}
		}
	}

	/**
	 * Get a setting value.
	 *
	 * Checks WC options first (source of truth), then falls back to
	 * internal storage for legacy/migration scenarios.
	 *
	 * @param string $group   Settings group (speedy, econt, address, general).
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( $group, $key, $default = null ) {
		$map_key = "{$group}.{$key}";

		// Check WC options first (source of truth after settings page save).
		if ( isset( $this->wc_option_map[ $map_key ] ) ) {
			list( $option_name, $type ) = $this->wc_option_map[ $map_key ];
			$value = get_option( $option_name, null );

			if ( null !== $value ) {
				return $this->convert_wc_value( $value, $type, $default );
			}
		}

		// Fall back to internal storage (legacy/migration).
		if ( isset( $this->settings[ $group ][ $key ] ) ) {
			return $this->settings[ $group ][ $key ];
		}

		return $default;
	}

	/**
	 * Convert WC option value to expected type.
	 *
	 * @param mixed  $value   Raw value from WC option.
	 * @param string $type    Field type (checkbox, text, price, textarea).
	 * @param mixed  $default Default value for type inference.
	 * @return mixed
	 */
	private function convert_wc_value( $value, $type, $default ) {
		switch ( $type ) {
			case 'checkbox':
				return 'yes' === $value;
			case 'price':
				return '' === $value ? '' : (float) $value;
			default:
				return $value;
		}
	}

	/**
	 * Set a setting value.
	 *
	 * @param string $group Settings group.
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 */
	public function set( $group, $key, $value ) {
		if ( ! isset( $this->settings[ $group ] ) ) {
			$this->settings[ $group ] = array();
		}

		$this->settings[ $group ][ $key ] = $value;
	}

	/**
	 * Save settings to database.
	 *
	 * @param string $group Optional. Specific group to save.
	 * @return bool
	 */
	public function save( $group = '' ) {
		if ( $this->using_new_structure ) {
			return $this->save_new_settings( $group );
		}

		return $this->save_legacy_settings();
	}

	/**
	 * Save settings to new structure.
	 *
	 * @param string $group Optional. Specific group to save.
	 * @return bool
	 */
	private function save_new_settings( $group = '' ) {
		if ( $group && isset( self::OPTION_NAMES[ $group ] ) ) {
			return update_option( self::OPTION_NAMES[ $group ], $this->settings[ $group ] );
		}

		$success = true;
		foreach ( self::OPTION_NAMES as $grp => $option_name ) {
			if ( isset( $this->settings[ $grp ] ) ) {
				if ( ! update_option( $option_name, $this->settings[ $grp ] ) ) {
					$success = false;
				}
			}
		}

		return $success;
	}

	/**
	 * Save settings to legacy structure.
	 *
	 * @return bool
	 */
	private function save_legacy_settings() {
		$legacy_settings = array();

		foreach ( $this->legacy_key_map as $legacy_key => $new_location ) {
			list( $group, $key ) = $new_location;
			if ( isset( $this->settings[ $group ][ $key ] ) ) {
				$legacy_settings[ $legacy_key ] = $this->settings[ $group ][ $key ];
			}
		}

		return update_option( self::LEGACY_OPTION_NAME, $legacy_settings );
	}

	/**
	 * Register settings with WordPress Settings API.
	 */
	public function register_settings() {
		// Register setting groups.
		foreach ( self::OPTION_NAMES as $group => $option_name ) {
			register_setting(
				'sesh_settings_group',
				$option_name,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( $this, 'sanitize_' . $group . '_settings' ),
				)
			);
		}
	}

	/**
	 * Sanitize Speedy settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_speedy_settings( $input ) {
		$sanitized = array();

		$sanitized['enabled'] = ! empty( $input['enabled'] );

		$sanitized['api_username'] = isset( $input['api_username'] )
			? sanitize_text_field( $input['api_username'] )
			: '';

		// Handle password - encrypt if changed.
		if ( isset( $input['api_password'] ) && ! empty( $input['api_password'] ) ) {
			// Only encrypt if it's a new/changed password (not already encrypted).
			if ( ! SESH_Encryption::is_encrypted( $input['api_password'] ) ) {
				$sanitized['api_password'] = SESH_Encryption::encrypt( $input['api_password'] );
			} else {
				$sanitized['api_password'] = $input['api_password'];
			}
		} else {
			// Keep existing password.
			$sanitized['api_password'] = $this->get( 'speedy', 'api_password', '' );
		}

		$sanitized['use_dynamic_pricing'] = ! empty( $input['use_dynamic_pricing'] );

		$sanitized['fallback_rate'] = isset( $input['fallback_rate'] )
			? $this->sanitize_price( $input['fallback_rate'] )
			: 0;

		$sanitized['free_shipping_threshold'] = isset( $input['free_shipping_threshold'] )
			? $this->sanitize_price( $input['free_shipping_threshold'], true )
			: '';

		$sanitized['default_service_id'] = isset( $input['default_service_id'] )
			? sanitize_text_field( $input['default_service_id'] )
			: '';

		return $sanitized;
	}

	/**
	 * Sanitize Econt settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_econt_settings( $input ) {
		$sanitized = array();

		$sanitized['enabled'] = ! empty( $input['enabled'] );

		$sanitized['api_username'] = isset( $input['api_username'] )
			? sanitize_text_field( $input['api_username'] )
			: '';

		// Handle password - encrypt if changed.
		if ( isset( $input['api_password'] ) && ! empty( $input['api_password'] ) ) {
			if ( ! SESH_Encryption::is_encrypted( $input['api_password'] ) ) {
				$sanitized['api_password'] = SESH_Encryption::encrypt( $input['api_password'] );
			} else {
				$sanitized['api_password'] = $input['api_password'];
			}
		} else {
			$sanitized['api_password'] = $this->get( 'econt', 'api_password', '' );
		}

		$sanitized['use_dynamic_pricing'] = ! empty( $input['use_dynamic_pricing'] );

		$sanitized['fallback_rate'] = isset( $input['fallback_rate'] )
			? $this->sanitize_price( $input['fallback_rate'] )
			: 0;

		$sanitized['free_shipping_threshold'] = isset( $input['free_shipping_threshold'] )
			? $this->sanitize_price( $input['free_shipping_threshold'], true )
			: '';

		$sanitized['default_service_type'] = isset( $input['default_service_type'] )
			? sanitize_text_field( $input['default_service_type'] )
			: 'courier_standard';

		return $sanitized;
	}

	/**
	 * Sanitize Address settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_address_settings( $input ) {
		$sanitized = array();

		$sanitized['enabled'] = ! empty( $input['enabled'] );

		$sanitized['label'] = isset( $input['label'] )
			? sanitize_text_field( $input['label'] )
			: '';

		$sanitized['fallback_rate'] = isset( $input['fallback_rate'] )
			? $this->sanitize_price( $input['fallback_rate'] )
			: 0;

		$sanitized['free_shipping_threshold'] = isset( $input['free_shipping_threshold'] )
			? $this->sanitize_price( $input['free_shipping_threshold'], true )
			: '';

		$sanitized['fields'] = isset( $input['fields'] )
			? sanitize_text_field( $input['fields'] )
			: '#billing_state, #billing_city, #billing_address_1';

		return $sanitized;
	}

	/**
	 * Sanitize General settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_general_settings( $input ) {
		$sanitized = array();

		$sanitized['hidden_fields'] = isset( $input['hidden_fields'] )
			? sanitize_text_field( $input['hidden_fields'] )
			: '';

		$sanitized['shipping_options_order'] = isset( $input['shipping_options_order'] )
			? sanitize_text_field( $input['shipping_options_order'] )
			: 'speedy,econt,address';

		$sanitized['emergency_contact'] = isset( $input['emergency_contact'] )
			? sanitize_text_field( $input['emergency_contact'] )
			: '';

		$sanitized['show_store_messages'] = isset( $input['show_store_messages'] )
			? sanitize_text_field( $input['show_store_messages'] )
			: 'speedy,econt,address';

		$sanitized['show_delivery_options']     = ! empty( $input['show_delivery_options'] );
		$sanitized['calculate_final_price']     = ! empty( $input['calculate_final_price'] );
		$sanitized['email_required']            = ! empty( $input['email_required'] );
		$sanitized['load_custom_jquery']        = ! empty( $input['load_custom_jquery'] );
		$sanitized['address_validation_needed'] = ! empty( $input['address_validation_needed'] );
		$sanitized['debug_mode']                = ! empty( $input['debug_mode'] );

		$sanitized['delivery_price_selector'] = isset( $input['delivery_price_selector'] )
			? sanitize_text_field( $input['delivery_price_selector'] )
			: '.cart-subtotal .woocommerce-Price-amount.amount';

		$sanitized['free_shipping_label_suffix'] = isset( $input['free_shipping_label_suffix'] )
			? sanitize_text_field( $input['free_shipping_label_suffix'] )
			: '';

		// Allow limited HTML for delivery details cart.
		$allowed_html = array(
			'th' => array(),
			'td' => array( 'data-title' => array() ),
		);
		$sanitized['delivery_details_cart'] = isset( $input['delivery_details_cart'] )
			? wp_kses( $input['delivery_details_cart'], $allowed_html )
			: '';

		$sanitized['cache_ttl'] = isset( $input['cache_ttl'] )
			? absint( $input['cache_ttl'] )
			: 3600;

		return $sanitized;
	}

	/**
	 * Sanitize Sender settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_sender_settings( $input ) {
		$sanitized = array();

		$sanitized['sender_name'] = isset( $input['sender_name'] )
			? sanitize_text_field( $input['sender_name'] )
			: '';

		// Validate and sanitize Bulgarian phone number.
		$phone = isset( $input['sender_phone'] ) ? sanitize_text_field( $input['sender_phone'] ) : '';
		if ( ! empty( $phone ) && ! $this->validate_bulgarian_phone( $phone ) ) {
			add_settings_error(
				'sesh_sender_settings',
				'invalid_phone',
				__( 'Invalid Bulgarian phone number format. Use format: 0888123456 or +359888123456', 'speedy_econt_shipping' ),
				'error'
			);
		}
		$sanitized['sender_phone'] = $phone;

		$sanitized['sender_email'] = isset( $input['sender_email'] )
			? sanitize_email( $input['sender_email'] )
			: '';

		$sanitized['sender_region'] = isset( $input['sender_region'] )
			? sanitize_text_field( $input['sender_region'] )
			: '';

		$sanitized['sender_city'] = isset( $input['sender_city'] )
			? sanitize_text_field( $input['sender_city'] )
			: '';

		$sanitized['sender_address'] = isset( $input['sender_address'] )
			? sanitize_textarea_field( $input['sender_address'] )
			: '';

		$sanitized['sender_postcode'] = isset( $input['sender_postcode'] )
			? sanitize_text_field( $input['sender_postcode'] )
			: '';

		return $sanitized;
	}

	/**
	 * Sanitize a price value.
	 *
	 * @param mixed $value        Value to sanitize.
	 * @param bool  $allow_empty Whether to allow empty values.
	 * @return float|string
	 */
	private function sanitize_price( $value, $allow_empty = false ) {
		if ( '' === $value && $allow_empty ) {
			return '';
		}

		return (float) $value;
	}

	// =========================================================================
	// Convenience Getters
	// =========================================================================

	/**
	 * Check if Speedy is enabled.
	 *
	 * @return bool
	 */
	public function is_speedy_enabled() {
		return (bool) $this->get( 'speedy', 'enabled', true );
	}

	/**
	 * Get Speedy username.
	 *
	 * @return string
	 */
	public function get_speedy_username() {
		return (string) $this->get( 'speedy', 'api_username', '' );
	}

	/**
	 * Get Speedy password (decrypted).
	 *
	 * @return string
	 */
	public function get_speedy_password() {
		$password = $this->get( 'speedy', 'api_password', '' );

		if ( ! empty( $password ) && SESH_Encryption::is_encrypted( $password ) ) {
			return SESH_Encryption::decrypt( $password );
		}

		return (string) $password;
	}

	/**
	 * Get Speedy free shipping threshold.
	 *
	 * @return float Returns -1 if no free shipping.
	 */
	public function get_speedy_free_from() {
		$value = $this->get( 'speedy', 'free_shipping_threshold', '' );
		return '' === $value ? -1 : (float) $value;
	}

	/**
	 * Get Speedy shipping rate.
	 *
	 * @return float
	 */
	public function get_speedy_shipping() {
		return (float) $this->get( 'speedy', 'fallback_rate', 0 );
	}

	/**
	 * Check if Speedy dynamic pricing is enabled.
	 *
	 * @return bool
	 */
	public function is_speedy_dynamic_pricing() {
		return (bool) $this->get( 'speedy', 'use_dynamic_pricing', false );
	}

	/**
	 * Check if Econt is enabled.
	 *
	 * @return bool
	 */
	public function is_econt_enabled() {
		return (bool) $this->get( 'econt', 'enabled', true );
	}

	/**
	 * Get Econt username.
	 *
	 * @return string
	 */
	public function get_econt_username() {
		return (string) $this->get( 'econt', 'api_username', '' );
	}

	/**
	 * Get Econt password (decrypted).
	 *
	 * @return string
	 */
	public function get_econt_password() {
		$password = $this->get( 'econt', 'api_password', '' );

		if ( ! empty( $password ) && SESH_Encryption::is_encrypted( $password ) ) {
			return SESH_Encryption::decrypt( $password );
		}

		return (string) $password;
	}

	/**
	 * Get Econt free shipping threshold.
	 *
	 * @return float Returns -1 if no free shipping.
	 */
	public function get_econt_free_from() {
		$value = $this->get( 'econt', 'free_shipping_threshold', '' );
		return '' === $value ? -1 : (float) $value;
	}

	/**
	 * Get Econt shipping rate.
	 *
	 * @return float
	 */
	public function get_econt_shipping() {
		return (float) $this->get( 'econt', 'fallback_rate', 0 );
	}

	/**
	 * Check if Econt dynamic pricing is enabled.
	 *
	 * @return bool
	 */
	public function is_econt_dynamic_pricing() {
		return (bool) $this->get( 'econt', 'use_dynamic_pricing', false );
	}

	/**
	 * Check if address delivery is enabled.
	 *
	 * @return bool
	 */
	public function is_address_enabled() {
		return (bool) $this->get( 'address', 'enabled', true );
	}

	/**
	 * Get address delivery label.
	 *
	 * @return string
	 */
	public function get_address_label() {
		$label = $this->get( 'address', 'label', '' );
		return empty( $label ) ? __( 'address', 'speedy_econt_shipping' ) : $label;
	}

	/**
	 * Get address free shipping threshold.
	 *
	 * @return float Returns -1 if no free shipping.
	 */
	public function get_address_free_from() {
		$value = $this->get( 'address', 'free_shipping_threshold', '' );
		return '' === $value ? -1 : (float) $value;
	}

	/**
	 * Get address shipping rate.
	 *
	 * @return float
	 */
	public function get_address_shipping() {
		return (float) $this->get( 'address', 'fallback_rate', 0 );
	}

	/**
	 * Get shipping options order.
	 *
	 * @return array
	 */
	public function get_shipping_options_order() {
		$order_string = $this->get( 'general', 'shipping_options_order', 'speedy,econt,address' );
		$options      = array_map( 'trim', explode( ',', $order_string ) );
		$result       = array();

		foreach ( $options as $option ) {
			switch ( $option ) {
				case 'speedy':
					if ( $this->is_speedy_enabled() ) {
						$result[] = 'speedy';
					}
					break;
				case 'econt':
					if ( $this->is_econt_enabled() ) {
						$result[] = 'econt';
					}
					break;
				case 'address':
					if ( $this->is_address_enabled() ) {
						$result[] = 'address';
					}
					break;
			}
		}

		return $result;
	}

	/**
	 * Get address fields.
	 *
	 * @return array
	 */
	public function get_address_fields() {
		$fields = $this->get( 'address', 'fields', '#billing_state, #billing_city, #billing_address_1' );
		return array_map( 'trim', explode( ',', $fields ) );
	}

	/**
	 * Get hidden fields.
	 *
	 * @return array
	 */
	public function get_hidden_fields() {
		$fields = $this->get( 'general', 'hidden_fields', '' );
		return array_filter( array_map( 'trim', explode( ',', $fields ) ) );
	}

	/**
	 * Check if email is required.
	 *
	 * @return bool
	 */
	public function is_email_required() {
		return (bool) $this->get( 'general', 'email_required', false );
	}

	/**
	 * Check if final price should be calculated.
	 *
	 * @return bool
	 */
	public function is_calculate_final_price() {
		return (bool) $this->get( 'general', 'calculate_final_price', false );
	}

	/**
	 * Check if address validation is needed.
	 *
	 * @return bool
	 */
	public function is_address_validation_needed() {
		return (bool) $this->get( 'general', 'address_validation_needed', true );
	}

	/**
	 * Get emergency contact.
	 *
	 * @return string
	 */
	public function get_emergency_contact() {
		return (string) $this->get( 'general', 'emergency_contact', '' );
	}

	/**
	 * Get free shipping label suffix.
	 *
	 * @return string
	 */
	public function get_free_shipping_label_suffix() {
		$suffix = $this->get( 'general', 'free_shipping_label_suffix', '' );
		if ( empty( $suffix ) ) {
			return __( 'for free', 'speedy_econt_shipping' );
		}
		if ( '-' === $suffix ) {
			return '';
		}
		return $suffix;
	}

	/**
	 * Get delivery price selector.
	 *
	 * @return string
	 */
	public function get_delivery_price_selector() {
		return $this->get( 'general', 'delivery_price_selector', '.cart-subtotal .woocommerce-Price-amount.amount' );
	}

	/**
	 * Get show store messages options.
	 *
	 * @return string
	 */
	public function get_show_store_messages() {
		$value = $this->get( 'general', 'show_store_messages', 'speedy,econt,address' );
		// Handle legacy value.
		if ( '1' === $value ) {
			return 'speedy,econt,address';
		}
		return $value;
	}

	/**
	 * Check if delivery options should be shown immediately.
	 *
	 * @return bool
	 */
	public function is_show_delivery_options() {
		return (bool) $this->get( 'general', 'show_delivery_options', false );
	}

	/**
	 * Check if custom jQuery should be loaded.
	 *
	 * @return bool
	 */
	public function is_load_custom_jquery() {
		return (bool) $this->get( 'general', 'load_custom_jquery', false );
	}

	/**
	 * Get delivery details cart HTML.
	 *
	 * @return string
	 */
	public function get_delivery_details_cart_html() {
		return $this->get(
			'general',
			'delivery_details_cart',
			'<th>Доставка</th><td data-title="Доставка">Преминете към следваща стъпка за опциите на доставка</td>'
		);
	}

	/**
	 * Check if debug mode is enabled.
	 *
	 * @return bool
	 */
	public function is_debug_mode() {
		return (bool) $this->get( 'general', 'debug_mode', false );
	}

	/**
	 * Get cache TTL in seconds.
	 *
	 * @return int
	 */
	public function get_cache_ttl() {
		return (int) $this->get( 'general', 'cache_ttl', 3600 );
	}

	/**
	 * Check if using new settings structure.
	 *
	 * @return bool
	 */
	public function is_using_new_structure() {
		return $this->using_new_structure;
	}

	/**
	 * Export settings for backup or multi-site deployment.
	 *
	 * @return array
	 */
	public function export() {
		$export = array(
			'version'  => SESH_Settings_Migrator::SETTINGS_VERSION,
			'settings' => array(),
		);

		foreach ( self::OPTION_NAMES as $group => $option_name ) {
			$settings = $this->settings[ $group ] ?? array();

			// Remove encrypted passwords for security.
			if ( isset( $settings['api_password'] ) ) {
				$settings['api_password'] = '';
			}

			$export['settings'][ $group ] = $settings;
		}

		return $export;
	}

	/**
	 * Import settings.
	 *
	 * @param array $data Import data.
	 * @return bool|WP_Error
	 */
	public function import( $data ) {
		if ( ! isset( $data['version'] ) || ! isset( $data['settings'] ) ) {
			return new WP_Error( 'invalid_format', __( 'Invalid settings format.', 'speedy_econt_shipping' ) );
		}

		foreach ( $data['settings'] as $group => $settings ) {
			if ( ! isset( self::OPTION_NAMES[ $group ] ) ) {
				continue;
			}

			// Merge with existing (keeps passwords if not provided).
			$current = $this->settings[ $group ] ?? array();
			foreach ( $settings as $key => $value ) {
				if ( 'api_password' === $key && empty( $value ) ) {
					continue; // Don't overwrite password with empty.
				}
				$current[ $key ] = $value;
			}

			$this->settings[ $group ] = $current;
		}

		return $this->save();
	}

	// =========================================================================
	// Sender Settings Getters
	// =========================================================================

	/**
	 * Get sender name.
	 *
	 * @return string
	 */
	public function get_sender_name() {
		return (string) $this->get( 'sender', 'sender_name', '' );
	}

	/**
	 * Get sender phone.
	 *
	 * @return string
	 */
	public function get_sender_phone() {
		return (string) $this->get( 'sender', 'sender_phone', '' );
	}

	/**
	 * Get sender email.
	 *
	 * @return string
	 */
	public function get_sender_email() {
		return (string) $this->get( 'sender', 'sender_email', '' );
	}

	/**
	 * Get sender region.
	 *
	 * @return string
	 */
	public function get_sender_region() {
		return (string) $this->get( 'sender', 'sender_region', '' );
	}

	/**
	 * Get sender city.
	 *
	 * @return string
	 */
	public function get_sender_city() {
		return (string) $this->get( 'sender', 'sender_city', '' );
	}

	/**
	 * Get sender address.
	 *
	 * @return string
	 */
	public function get_sender_address() {
		return (string) $this->get( 'sender', 'sender_address', '' );
	}

	/**
	 * Get sender postcode.
	 *
	 * @return string
	 */
	public function get_sender_postcode() {
		return (string) $this->get( 'sender', 'sender_postcode', '' );
	}

	/**
	 * Get all sender settings as an array.
	 *
	 * @return array
	 */
	public function get_sender_params() {
		return array(
			'name'     => $this->get_sender_name(),
			'phone'    => $this->get_sender_phone(),
			'email'    => $this->get_sender_email(),
			'region'   => $this->get_sender_region(),
			'city'     => $this->get_sender_city(),
			'address'  => $this->get_sender_address(),
			'postcode' => $this->get_sender_postcode(),
		);
	}

	/**
	 * Validate Bulgarian phone number format.
	 *
	 * @param string $phone Phone number to validate.
	 * @return bool
	 */
	private function validate_bulgarian_phone( $phone ) {
		// Remove all spaces and dashes.
		$phone = preg_replace( '/[\s\-]/', '', $phone );

		// Check valid formats:
		// - 0888123456 (10 digits starting with 0)
		// - +359888123456 (13 characters starting with +359)
		// - 00359888123456 (14 digits starting with 00359).
		if ( preg_match( '/^0[0-9]{9}$/', $phone ) ) {
			return true;
		}

		if ( preg_match( '/^\+3590?[0-9]{9}$/', $phone ) ) {
			return true;
		}

		if ( preg_match( '/^003590?[0-9]{9}$/', $phone ) ) {
			return true;
		}

		return false;
	}
}
