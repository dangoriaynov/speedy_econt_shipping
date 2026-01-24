<?php
/**
 * WooCommerce Settings Integration.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Speedy & Econt WooCommerce Settings class.
 *
 * Integrates plugin settings into WooCommerce > Settings > Shipping
 * as a dedicated tab following WooCommerce conventions.
 */
class SESH_WC_Settings extends WC_Settings_Page {

	/**
	 * Settings instance.
	 *
	 * @var SESH_Settings
	 */
	private $plugin_settings;

	/**
	 * Constructor.
	 *
	 * @param SESH_Settings $settings Settings instance.
	 */
	public function __construct( $settings ) {
		$this->id                = 'sesh_shipping';
		$this->label             = __( 'Speedy & Econt', 'speedy_econt_shipping' );
		$this->plugin_settings   = $settings;

		parent::__construct();
	}

	/**
	 * Get sections.
	 *
	 * @return array
	 */
	public function get_sections() {
		return array(
			''        => __( 'General', 'speedy_econt_shipping' ),
			'speedy'  => __( 'Speedy', 'speedy_econt_shipping' ),
			'econt'   => __( 'Econt', 'speedy_econt_shipping' ),
			'address' => __( 'Address Delivery', 'speedy_econt_shipping' ),
		);
	}

	/**
	 * Get settings array.
	 *
	 * @param string $current_section Current section ID.
	 * @return array Settings.
	 */
	public function get_settings( $current_section = '' ) {
		switch ( $current_section ) {
			case 'speedy':
				return $this->get_speedy_settings();
			case 'econt':
				return $this->get_econt_settings();
			case 'address':
				return $this->get_address_settings();
			default:
				return $this->get_general_settings();
		}
	}

	/**
	 * Get General settings.
	 *
	 * @return array
	 */
	private function get_general_settings() {
		return array(
			array(
				'title' => __( 'General Settings', 'speedy_econt_shipping' ),
				'type'  => 'title',
				'desc'  => __( 'General plugin settings that apply to all shipping methods.', 'speedy_econt_shipping' ),
				'id'    => 'sesh_general_settings',
			),
			array(
				'title'    => __( 'Shipping Options Order', 'speedy_econt_shipping' ),
				'desc'     => __( 'Order in which shipping options appear at checkout (comma-separated: speedy,econt,address)', 'speedy_econt_shipping' ),
				'id'       => 'sesh_shipping_options_order',
				'default'  => 'speedy,econt,address',
				'type'     => 'text',
				'css'      => 'min-width:300px;',
			),
			array(
				'title'    => __( 'Emergency Contact', 'speedy_econt_shipping' ),
				'desc'     => __( 'Contact information shown when shipping data fails to load', 'speedy_econt_shipping' ),
				'id'       => 'sesh_emergency_contact',
				'default'  => '',
				'type'     => 'text',
				'css'      => 'min-width:400px;',
			),
			array(
				'title'    => __( 'Free Shipping Label', 'speedy_econt_shipping' ),
				'desc'     => __( 'Text shown for free shipping (use "-" for no text)', 'speedy_econt_shipping' ),
				'id'       => 'sesh_free_shipping_label',
				'default'  => __( 'for free', 'speedy_econt_shipping' ),
				'type'     => 'text',
			),
			array(
				'title'    => __( 'Email Required', 'speedy_econt_shipping' ),
				'desc'     => __( 'Make email field required at checkout', 'speedy_econt_shipping' ),
				'id'       => 'sesh_email_required',
				'default'  => 'no',
				'type'     => 'checkbox',
			),
			array(
				'title'    => __( 'Validate Address', 'speedy_econt_shipping' ),
				'desc'     => __( 'Validate that delivery details are filled before allowing checkout', 'speedy_econt_shipping' ),
				'id'       => 'sesh_address_validation',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			array(
				'title'    => __( 'Hidden Fields (CSS Selectors)', 'speedy_econt_shipping' ),
				'desc'     => __( 'CSS selectors for fields to hide at checkout (comma-separated)', 'speedy_econt_shipping' ),
				'id'       => 'sesh_hidden_fields',
				'default'  => '#billing_company_field, #billing_country_field, #billing_postcode_field, #ship-to-different-address',
				'type'     => 'textarea',
				'css'      => 'min-width:400px; min-height:80px;',
			),
			array(
				'title'    => __( 'Debug Mode', 'speedy_econt_shipping' ),
				'desc'     => __( 'Enable debug logging (logs will be saved to WooCommerce log files)', 'speedy_econt_shipping' ),
				'id'       => 'sesh_debug_mode',
				'default'  => 'no',
				'type'     => 'checkbox',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'sesh_general_settings',
			),
		);
	}

	/**
	 * Get Speedy settings.
	 *
	 * @return array
	 */
	private function get_speedy_settings() {
		return array(
			array(
				'title' => __( 'Speedy Settings', 'speedy_econt_shipping' ),
				'type'  => 'title',
				'desc'  => sprintf(
					/* translators: %s: URL to Speedy website */
					__( 'Configure Speedy courier integration. You need API credentials from Speedy. <a href="%s" target="_blank">Request API access</a>', 'speedy_econt_shipping' ),
					'https://www.speedy.bg'
				),
				'id'    => 'sesh_speedy_settings',
			),
			array(
				'title'    => __( 'Enable Speedy', 'speedy_econt_shipping' ),
				'desc'     => __( 'Enable Speedy shipping method', 'speedy_econt_shipping' ),
				'id'       => 'sesh_speedy_enabled',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			array(
				'title'    => __( 'API Username', 'speedy_econt_shipping' ),
				'desc'     => __( 'Your Speedy API username', 'speedy_econt_shipping' ),
				'id'       => 'sesh_speedy_username',
				'default'  => '',
				'type'     => 'text',
				'css'      => 'min-width:300px;',
			),
			array(
				'title'    => __( 'API Password', 'speedy_econt_shipping' ),
				'desc'     => __( 'Your Speedy API password (stored encrypted)', 'speedy_econt_shipping' ),
				'id'       => 'sesh_speedy_password',
				'default'  => '',
				'type'     => 'password',
				'css'      => 'min-width:300px;',
			),
			array(
				'title'    => __( 'Use Dynamic Pricing', 'speedy_econt_shipping' ),
				'desc'     => __( 'Calculate shipping cost dynamically via Speedy API', 'speedy_econt_shipping' ),
				'id'       => 'sesh_speedy_dynamic_pricing',
				'default'  => 'no',
				'type'     => 'checkbox',
			),
			array(
				'title'    => __( 'Fallback Rate', 'speedy_econt_shipping' ),
				'desc'     => __( 'Flat rate to use when API pricing is unavailable', 'speedy_econt_shipping' ),
				'id'       => 'sesh_speedy_fallback_rate',
				'default'  => '0',
				'type'     => 'price',
			),
			array(
				'title'    => __( 'Free Shipping Threshold', 'speedy_econt_shipping' ),
				'desc'     => __( 'Minimum order amount for free Speedy shipping (leave empty to disable)', 'speedy_econt_shipping' ),
				'id'       => 'sesh_speedy_free_from',
				'default'  => '',
				'type'     => 'price',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'sesh_speedy_settings',
			),
		);
	}

	/**
	 * Get Econt settings.
	 *
	 * @return array
	 */
	private function get_econt_settings() {
		return array(
			array(
				'title' => __( 'Econt Settings', 'speedy_econt_shipping' ),
				'type'  => 'title',
				'desc'  => sprintf(
					/* translators: %s: URL to Econt website */
					__( 'Configure Econt courier integration. API credentials are optional for viewing offices but required for creating shipments. <a href="%s" target="_blank">Get Econt API credentials</a>', 'speedy_econt_shipping' ),
					'https://www.econt.com'
				),
				'id'    => 'sesh_econt_settings',
			),
			array(
				'title'    => __( 'Enable Econt', 'speedy_econt_shipping' ),
				'desc'     => __( 'Enable Econt shipping method', 'speedy_econt_shipping' ),
				'id'       => 'sesh_econt_enabled',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			array(
				'title'    => __( 'API Username', 'speedy_econt_shipping' ),
				'desc'     => __( 'Your Econt API username (required for creating shipments)', 'speedy_econt_shipping' ),
				'id'       => 'sesh_econt_username',
				'default'  => '',
				'type'     => 'text',
				'css'      => 'min-width:300px;',
			),
			array(
				'title'    => __( 'API Password', 'speedy_econt_shipping' ),
				'desc'     => __( 'Your Econt API password (stored encrypted)', 'speedy_econt_shipping' ),
				'id'       => 'sesh_econt_password',
				'default'  => '',
				'type'     => 'password',
				'css'      => 'min-width:300px;',
			),
			array(
				'title'    => __( 'Use Dynamic Pricing', 'speedy_econt_shipping' ),
				'desc'     => __( 'Calculate shipping cost dynamically via Econt API', 'speedy_econt_shipping' ),
				'id'       => 'sesh_econt_dynamic_pricing',
				'default'  => 'no',
				'type'     => 'checkbox',
			),
			array(
				'title'    => __( 'Fallback Rate', 'speedy_econt_shipping' ),
				'desc'     => __( 'Flat rate to use when API pricing is unavailable', 'speedy_econt_shipping' ),
				'id'       => 'sesh_econt_fallback_rate',
				'default'  => '0',
				'type'     => 'price',
			),
			array(
				'title'    => __( 'Free Shipping Threshold', 'speedy_econt_shipping' ),
				'desc'     => __( 'Minimum order amount for free Econt shipping (leave empty to disable)', 'speedy_econt_shipping' ),
				'id'       => 'sesh_econt_free_from',
				'default'  => '',
				'type'     => 'price',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'sesh_econt_settings',
			),
		);
	}

	/**
	 * Get Address Delivery settings.
	 *
	 * @return array
	 */
	private function get_address_settings() {
		return array(
			array(
				'title' => __( 'Address Delivery Settings', 'speedy_econt_shipping' ),
				'type'  => 'title',
				'desc'  => __( 'Configure delivery to customer address (courier to door).', 'speedy_econt_shipping' ),
				'id'    => 'sesh_address_settings',
			),
			array(
				'title'    => __( 'Enable Address Delivery', 'speedy_econt_shipping' ),
				'desc'     => __( 'Enable delivery to customer address', 'speedy_econt_shipping' ),
				'id'       => 'sesh_address_enabled',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			array(
				'title'    => __( 'Address Label', 'speedy_econt_shipping' ),
				'desc'     => __( 'Label for address delivery option', 'speedy_econt_shipping' ),
				'id'       => 'sesh_address_label',
				'default'  => __( 'address', 'speedy_econt_shipping' ),
				'type'     => 'text',
			),
			array(
				'title'    => __( 'Fallback Rate', 'speedy_econt_shipping' ),
				'desc'     => __( 'Address delivery shipping fee', 'speedy_econt_shipping' ),
				'id'       => 'sesh_address_fallback_rate',
				'default'  => '0',
				'type'     => 'price',
			),
			array(
				'title'    => __( 'Free Shipping Threshold', 'speedy_econt_shipping' ),
				'desc'     => __( 'Minimum order amount for free address delivery (leave empty to disable)', 'speedy_econt_shipping' ),
				'id'       => 'sesh_address_free_from',
				'default'  => '',
				'type'     => 'price',
			),
			array(
				'title'    => __( 'Address Fields (CSS Selectors)', 'speedy_econt_shipping' ),
				'desc'     => __( 'CSS selectors for address fields to show (comma-separated)', 'speedy_econt_shipping' ),
				'id'       => 'sesh_address_fields',
				'default'  => '#billing_state, #billing_city, #billing_address_1',
				'type'     => 'text',
				'css'      => 'min-width:400px;',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'sesh_address_settings',
			),
		);
	}

	/**
	 * Save settings.
	 */
	public function save() {
		global $current_section;

		$settings = $this->get_settings( $current_section );

		// Save WooCommerce settings.
		WC_Admin_Settings::save_fields( $settings );

		// Sync to plugin settings structure for backward compatibility.
		$this->sync_to_plugin_settings( $current_section );
	}

	/**
	 * Sync WooCommerce settings to plugin settings structure.
	 *
	 * @param string $section Current section.
	 */
	private function sync_to_plugin_settings( $section ) {
		switch ( $section ) {
			case 'speedy':
				$this->plugin_settings->set( 'speedy', 'enabled', 'yes' === get_option( 'sesh_speedy_enabled', 'yes' ) );
				$this->plugin_settings->set( 'speedy', 'api_username', get_option( 'sesh_speedy_username', '' ) );

				// Handle password encryption.
				$password = get_option( 'sesh_speedy_password', '' );
				if ( ! empty( $password ) && ! SESH_Encryption::is_encrypted( $password ) ) {
					$password = SESH_Encryption::encrypt( $password );
					update_option( 'sesh_speedy_password', $password );
				}
				$this->plugin_settings->set( 'speedy', 'api_password', $password );

				$this->plugin_settings->set( 'speedy', 'use_dynamic_pricing', 'yes' === get_option( 'sesh_speedy_dynamic_pricing', 'no' ) );
				$this->plugin_settings->set( 'speedy', 'fallback_rate', floatval( get_option( 'sesh_speedy_fallback_rate', 0 ) ) );
				$this->plugin_settings->set( 'speedy', 'free_shipping_threshold', get_option( 'sesh_speedy_free_from', '' ) );
				break;

			case 'econt':
				$this->plugin_settings->set( 'econt', 'enabled', 'yes' === get_option( 'sesh_econt_enabled', 'yes' ) );
				$this->plugin_settings->set( 'econt', 'api_username', get_option( 'sesh_econt_username', '' ) );

				// Handle password encryption.
				$password = get_option( 'sesh_econt_password', '' );
				if ( ! empty( $password ) && ! SESH_Encryption::is_encrypted( $password ) ) {
					$password = SESH_Encryption::encrypt( $password );
					update_option( 'sesh_econt_password', $password );
				}
				$this->plugin_settings->set( 'econt', 'api_password', $password );

				$this->plugin_settings->set( 'econt', 'use_dynamic_pricing', 'yes' === get_option( 'sesh_econt_dynamic_pricing', 'no' ) );
				$this->plugin_settings->set( 'econt', 'fallback_rate', floatval( get_option( 'sesh_econt_fallback_rate', 0 ) ) );
				$this->plugin_settings->set( 'econt', 'free_shipping_threshold', get_option( 'sesh_econt_free_from', '' ) );
				break;

			case 'address':
				$this->plugin_settings->set( 'address', 'enabled', 'yes' === get_option( 'sesh_address_enabled', 'yes' ) );
				$this->plugin_settings->set( 'address', 'label', get_option( 'sesh_address_label', __( 'address', 'speedy_econt_shipping' ) ) );
				$this->plugin_settings->set( 'address', 'fallback_rate', floatval( get_option( 'sesh_address_fallback_rate', 0 ) ) );
				$this->plugin_settings->set( 'address', 'free_shipping_threshold', get_option( 'sesh_address_free_from', '' ) );
				$this->plugin_settings->set( 'address', 'fields', get_option( 'sesh_address_fields', '#billing_state, #billing_city, #billing_address_1' ) );
				break;

			default:
				// General settings.
				$this->plugin_settings->set( 'general', 'shipping_options_order', get_option( 'sesh_shipping_options_order', 'speedy,econt,address' ) );
				$this->plugin_settings->set( 'general', 'emergency_contact', get_option( 'sesh_emergency_contact', '' ) );
				$this->plugin_settings->set( 'general', 'free_shipping_label_suffix', get_option( 'sesh_free_shipping_label', __( 'for free', 'speedy_econt_shipping' ) ) );
				$this->plugin_settings->set( 'general', 'email_required', 'yes' === get_option( 'sesh_email_required', 'no' ) );
				$this->plugin_settings->set( 'general', 'address_validation_needed', 'yes' === get_option( 'sesh_address_validation', 'yes' ) );
				$this->plugin_settings->set( 'general', 'hidden_fields', get_option( 'sesh_hidden_fields', '' ) );
				$this->plugin_settings->set( 'general', 'debug_mode', 'yes' === get_option( 'sesh_debug_mode', 'no' ) );
				break;
		}

		// Save plugin settings.
		$this->plugin_settings->save();
	}
}
