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
 * Handles all plugin settings with backward compatibility
 * for the legacy settings structure.
 */
class SESH_Settings {

	/**
	 * Legacy option name (for backward compatibility).
	 *
	 * @var string
	 */
	const LEGACY_OPTION_NAME = 'speedy_econt_shipping_option_name';

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Default settings values.
	 *
	 * @var array
	 */
	private $defaults = array(
		'enable_speedy'              => true,
		'speedy_username'            => '',
		'speedy_password'            => '',
		'speedy_free_from'           => '',
		'speedy_shipping'            => '',
		'enable_econt'               => true,
		'econt_free_from'            => '',
		'econt_shipping'             => '',
		'enable_address'             => true,
		'address_label'              => '',
		'address_free_from'          => '',
		'address_shipping'           => '',
		'address_fields'             => '#billing_state, #billing_city, #billing_address_1',
		'hidden_fields'              => '#billing_address_2_field, #billing_company_field, #billing_country_field, #billing_postcode_field, #ship-to-different-address, .cart-subtotal, .checkout-wrap, .woocommerce-shipping-totals.shipping',
		'shipping_options_order'     => 'speedy,econt,address',
		'emergency_contact'          => '',
		'show_store_messages'        => 'speedy,econt,address',
		'show_delivery_options'      => false,
		'calculate_final_price'      => false,
		'delivery_price_selector'    => '.cart-subtotal .woocommerce-Price-amount.amount',
		'email_required'             => false,
		'free_shipping_label_suffix' => '',
		'load_custom_jquery'         => false,
		'address_validation_needed'  => true,
		'delivery_details_cart'      => '<th>Доставка</th><td data-title="Доставка">Преминете към следваща стъпка за опциите на доставка</td>',
	);

	/**
	 * Legacy to new key mapping.
	 *
	 * @var array
	 */
	private $legacy_key_map = array(
		'enable_speedy_0'             => 'enable_speedy',
		'speedy_username_0'           => 'speedy_username',
		'speedy_password_1'           => 'speedy_password',
		'speedy_free_from_6'          => 'speedy_free_from',
		'speedy_shipping_7'           => 'speedy_shipping',
		'enable_econt_1'              => 'enable_econt',
		'econt_free_from_8'           => 'econt_free_from',
		'econt_shipping_9'            => 'econt_shipping',
		'enable_address_2'            => 'enable_address',
		'address_label_12'            => 'address_label',
		'address_free_from_10'        => 'address_free_from',
		'address_shipping_11'         => 'address_shipping',
		'address_fields_3'            => 'address_fields',
		'additionally_hidden_fields_03' => 'hidden_fields',
		'shipping_opts_order_14'      => 'shipping_options_order',
		'emergency_contact_13'        => 'emergency_contact',
		'show_store_messages_6'       => 'show_store_messages',
		'show_deliv_opts_6'           => 'show_delivery_options',
		'calculate_final_price_8'     => 'calculate_final_price',
		'delivery_price_selector_14'  => 'delivery_price_selector',
		'email_required_9'            => 'email_required',
		'free_shipping_label_suffix_17' => 'free_shipping_label_suffix',
		'load_custom_jquery_15'       => 'load_custom_jquery',
		'address_validation_needed_16' => 'address_validation_needed',
		'delivery_details_cart_15'    => 'delivery_details_cart',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->load_settings();
	}

	/**
	 * Load settings from database.
	 */
	private function load_settings() {
		// Try to load legacy settings first for backward compatibility.
		$legacy_settings = get_option( self::LEGACY_OPTION_NAME, array() );

		if ( ! empty( $legacy_settings ) ) {
			$this->settings = $this->map_legacy_settings( $legacy_settings );
		} else {
			$this->settings = $this->defaults;
		}
	}

	/**
	 * Map legacy settings to new format.
	 *
	 * @param array $legacy_settings Legacy settings array.
	 * @return array Mapped settings.
	 */
	private function map_legacy_settings( $legacy_settings ) {
		$mapped = $this->defaults;

		foreach ( $this->legacy_key_map as $legacy_key => $new_key ) {
			if ( isset( $legacy_settings[ $legacy_key ] ) ) {
				$mapped[ $new_key ] = $legacy_settings[ $legacy_key ];
			}
		}

		return $mapped;
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		if ( null === $this->settings ) {
			$this->load_settings();
		}

		if ( isset( $this->settings[ $key ] ) ) {
			return $this->settings[ $key ];
		}

		if ( null !== $default ) {
			return $default;
		}

		return isset( $this->defaults[ $key ] ) ? $this->defaults[ $key ] : null;
	}

	/**
	 * Set a setting value.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 */
	public function set( $key, $value ) {
		$this->settings[ $key ] = $value;
	}

	/**
	 * Save settings to database.
	 *
	 * @return bool
	 */
	public function save() {
		// For backward compatibility, save to legacy option.
		$legacy_settings = array();

		foreach ( $this->legacy_key_map as $legacy_key => $new_key ) {
			if ( isset( $this->settings[ $new_key ] ) ) {
				$legacy_settings[ $legacy_key ] = $this->settings[ $new_key ];
			}
		}

		return update_option( self::LEGACY_OPTION_NAME, $legacy_settings );
	}

	/**
	 * Check if Speedy is enabled.
	 *
	 * @return bool
	 */
	public function is_speedy_enabled() {
		return (bool) $this->get( 'enable_speedy', true );
	}

	/**
	 * Get Speedy username.
	 *
	 * @return string
	 */
	public function get_speedy_username() {
		return (string) $this->get( 'speedy_username', '' );
	}

	/**
	 * Get Speedy password.
	 *
	 * @return string
	 */
	public function get_speedy_password() {
		return (string) $this->get( 'speedy_password', '' );
	}

	/**
	 * Get Speedy free shipping threshold.
	 *
	 * @return float Returns -1 if no free shipping.
	 */
	public function get_speedy_free_from() {
		$value = $this->get( 'speedy_free_from', '' );
		return '' === $value ? -1 : (float) $value;
	}

	/**
	 * Get Speedy shipping rate.
	 *
	 * @return float
	 */
	public function get_speedy_shipping() {
		return (float) $this->get( 'speedy_shipping', 0 );
	}

	/**
	 * Check if Econt is enabled.
	 *
	 * @return bool
	 */
	public function is_econt_enabled() {
		return (bool) $this->get( 'enable_econt', true );
	}

	/**
	 * Get Econt free shipping threshold.
	 *
	 * @return float Returns -1 if no free shipping.
	 */
	public function get_econt_free_from() {
		$value = $this->get( 'econt_free_from', '' );
		return '' === $value ? -1 : (float) $value;
	}

	/**
	 * Get Econt shipping rate.
	 *
	 * @return float
	 */
	public function get_econt_shipping() {
		return (float) $this->get( 'econt_shipping', 0 );
	}

	/**
	 * Check if address delivery is enabled.
	 *
	 * @return bool
	 */
	public function is_address_enabled() {
		return (bool) $this->get( 'enable_address', true );
	}

	/**
	 * Get address delivery label.
	 *
	 * @return string
	 */
	public function get_address_label() {
		$label = $this->get( 'address_label', '' );
		return empty( $label ) ? __( 'address', 'speedy_econt_shipping' ) : $label;
	}

	/**
	 * Get address free shipping threshold.
	 *
	 * @return float Returns -1 if no free shipping.
	 */
	public function get_address_free_from() {
		$value = $this->get( 'address_free_from', '' );
		return '' === $value ? -1 : (float) $value;
	}

	/**
	 * Get address shipping rate.
	 *
	 * @return float
	 */
	public function get_address_shipping() {
		return (float) $this->get( 'address_shipping', 0 );
	}

	/**
	 * Get shipping options order.
	 *
	 * @return array
	 */
	public function get_shipping_options_order() {
		$order_string = $this->get( 'shipping_options_order', 'speedy,econt,address' );
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
		$fields = $this->get( 'address_fields', '#billing_state, #billing_city, #billing_address_1' );
		return array_map( 'trim', explode( ',', $fields ) );
	}

	/**
	 * Get hidden fields.
	 *
	 * @return array
	 */
	public function get_hidden_fields() {
		$fields = $this->get( 'hidden_fields', '' );
		return array_map( 'trim', explode( ',', $fields ) );
	}

	/**
	 * Check if email is required.
	 *
	 * @return bool
	 */
	public function is_email_required() {
		return (bool) $this->get( 'email_required', false );
	}

	/**
	 * Check if final price should be calculated.
	 *
	 * @return bool
	 */
	public function is_calculate_final_price() {
		return (bool) $this->get( 'calculate_final_price', false );
	}

	/**
	 * Check if address validation is needed.
	 *
	 * @return bool
	 */
	public function is_address_validation_needed() {
		return (bool) $this->get( 'address_validation_needed', true );
	}

	/**
	 * Get emergency contact.
	 *
	 * @return string
	 */
	public function get_emergency_contact() {
		return (string) $this->get( 'emergency_contact', '' );
	}

	/**
	 * Get free shipping label suffix.
	 *
	 * @return string
	 */
	public function get_free_shipping_label_suffix() {
		$suffix = $this->get( 'free_shipping_label_suffix', '' );
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
		return $this->get( 'delivery_price_selector', '.cart-subtotal .woocommerce-Price-amount.amount' );
	}

	/**
	 * Get show store messages options.
	 *
	 * @return string
	 */
	public function get_show_store_messages() {
		$value = $this->get( 'show_store_messages', 'speedy,econt,address' );
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
		return (bool) $this->get( 'show_delivery_options', false );
	}

	/**
	 * Check if custom jQuery should be loaded.
	 *
	 * @return bool
	 */
	public function is_load_custom_jquery() {
		return (bool) $this->get( 'load_custom_jquery', false );
	}

	/**
	 * Get delivery details cart HTML.
	 *
	 * @return string
	 */
	public function get_delivery_details_cart_html() {
		return $this->get(
			'delivery_details_cart',
			'<th>Доставка</th><td data-title="Доставка">Преминете към следваща стъпка за опциите на доставка</td>'
		);
	}
}
