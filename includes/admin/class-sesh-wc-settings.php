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

		// Encrypt passwords before they're saved to prevent plaintext storage.
		add_filter( 'pre_update_option_sesh_speedy_password', array( $this, 'encrypt_password_on_save' ), 10, 2 );
		add_filter( 'pre_update_option_sesh_econt_password', array( $this, 'encrypt_password_on_save' ), 10, 2 );

		// Validate and sanitize CSS selectors.
		add_filter( 'pre_update_option_sesh_hidden_fields', array( $this, 'sanitize_css_selectors' ) );
		add_filter( 'pre_update_option_sesh_address_fields', array( $this, 'sanitize_css_selectors' ) );

		// Validate sender phone number.
		add_filter( 'pre_update_option_sesh_sender_phone', array( $this, 'validate_sender_phone' ) );

		// Validate API credentials on save.
		add_action( 'update_option_sesh_speedy_username', array( $this, 'validate_speedy_credentials_on_save' ), 10, 2 );
		add_action( 'update_option_sesh_speedy_password', array( $this, 'validate_speedy_credentials_on_save' ), 10, 2 );
		add_action( 'update_option_sesh_econt_username', array( $this, 'validate_econt_credentials_on_save' ), 10, 2 );
		add_action( 'update_option_sesh_econt_password', array( $this, 'validate_econt_credentials_on_save' ), 10, 2 );

		// AJAX handlers for testing API connections.
		add_action( 'wp_ajax_sesh_test_speedy_connection', array( $this, 'ajax_test_speedy_connection' ) );
		add_action( 'wp_ajax_sesh_test_econt_connection', array( $this, 'ajax_test_econt_connection' ) );

		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Custom field type for test buttons.
		add_action( 'woocommerce_admin_field_sesh_test_button', array( $this, 'output_test_button_field' ) );

		// Display validation notices.
		add_action( 'admin_notices', array( $this, 'display_validation_notices' ) );

		parent::__construct();
	}

	/**
	 * Encrypt password before saving to database.
	 *
	 * @param string $new_value New password value.
	 * @param string $old_value Old password value.
	 * @return string Encrypted password.
	 */
	public function encrypt_password_on_save( $new_value, $old_value ) {
		// Empty password means keep the old one.
		if ( empty( $new_value ) ) {
			return $old_value;
		}

		// Already encrypted - return as-is.
		if ( class_exists( 'SESH_Encryption' ) && SESH_Encryption::is_encrypted( $new_value ) ) {
			return $new_value;
		}

		// Encrypt the new password.
		if ( class_exists( 'SESH_Encryption' ) ) {
			return SESH_Encryption::encrypt( $new_value );
		}

		return $new_value;
	}

	/**
	 * Validate and sanitize Bulgarian phone number.
	 *
	 * @param string $value Phone number value.
	 * @return string Sanitized phone number or empty if invalid.
	 */
	public function validate_sender_phone( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Remove spaces and dashes.
		$value = preg_replace( '/[\s\-]/', '', sanitize_text_field( $value ) );

		// Valid formats: 0888123456, +359888123456, 00359888123456.
		$is_valid = preg_match( '/^0[0-9]{9}$/', $value ) ||
			preg_match( '/^\+?3590?[0-9]{9}$/', $value ) ||
			preg_match( '/^003590?[0-9]{9}$/', $value );

		if ( ! $is_valid ) {
			WC_Admin_Settings::add_error(
				__( 'Invalid Bulgarian phone number format. Use format: 0888123456 or +359888123456', 'speedy_econt_shipping' )
			);
			// Return the sanitized value anyway, let the user fix it.
		}

		return $value;
	}

	/**
	 * Sanitize and validate CSS selectors.
	 *
	 * @param string $value Comma-separated CSS selectors.
	 * @return string Sanitized selectors.
	 */
	public function sanitize_css_selectors( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$selectors       = explode( ',', $value );
		$valid_selectors = array();

		foreach ( $selectors as $selector ) {
			$selector = trim( $selector );

			if ( empty( $selector ) ) {
				continue;
			}

			// Validate selector format.
			if ( $this->is_valid_css_selector( $selector ) ) {
				$valid_selectors[] = $selector;
			} else {
				// Add admin notice for invalid selector.
				WC_Admin_Settings::add_error(
					sprintf(
						/* translators: %s: invalid CSS selector */
						__( 'Invalid CSS selector removed: %s', 'speedy_econt_shipping' ),
						esc_html( $selector )
					)
				);
			}
		}

		return implode( ', ', $valid_selectors );
	}

	/**
	 * Check if a string is a valid CSS selector.
	 *
	 * Basic validation for common selector patterns used in this plugin.
	 *
	 * @param string $selector CSS selector to validate.
	 * @return bool Whether selector appears valid.
	 */
	private function is_valid_css_selector( $selector ) {
		// Must not be empty.
		if ( empty( $selector ) ) {
			return false;
		}

		// Must start with valid character (# . [ a-z).
		if ( ! preg_match( '/^[#.\[\*a-zA-Z]/', $selector ) ) {
			return false;
		}

		// Check for dangerous characters (script injection attempts).
		$dangerous_patterns = array(
			'<',
			'>',
			'javascript:',
			'expression(',
			'url(',
			'@import',
			'behavior:',
		);

		$selector_lower = strtolower( $selector );
		foreach ( $dangerous_patterns as $pattern ) {
			if ( strpos( $selector_lower, $pattern ) !== false ) {
				return false;
			}
		}

		// Basic pattern: allows #id, .class, element, [attr], and combinations.
		// Also allows pseudo-selectors like :first-child.
		$pattern = '/^[#.\[\]a-zA-Z0-9_\-:="\'\s\*\>\+\~\^$|]+$/';

		return (bool) preg_match( $pattern, $selector );
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
			'sender'  => __( 'Sender Address', 'speedy_econt_shipping' ),
		);
	}

	/**
	 * Get settings for the default section (General).
	 *
	 * Uses the new WooCommerce 5.5+ settings infrastructure.
	 *
	 * @return array Settings.
	 */
	protected function get_settings_for_default_section() {
		return $this->get_general_settings();
	}

	/**
	 * Get settings for the Speedy section.
	 *
	 * @return array Settings.
	 */
	protected function get_settings_for_speedy_section() {
		return $this->get_speedy_settings();
	}

	/**
	 * Get settings for the Econt section.
	 *
	 * @return array Settings.
	 */
	protected function get_settings_for_econt_section() {
		return $this->get_econt_settings();
	}

	/**
	 * Get settings for the Address section.
	 *
	 * @return array Settings.
	 */
	protected function get_settings_for_address_section() {
		return $this->get_address_settings();
	}

	/**
	 * Get settings for the Sender section.
	 *
	 * @return array Settings.
	 */
	protected function get_settings_for_sender_section() {
		return $this->get_sender_settings();
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
				'desc'     => __( 'Order in which shipping options appear at checkout.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_shipping_options_order',
				'default'  => 'speedy,econt,address',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select',
				'options'  => array(
					'speedy,econt,address' => __( 'Speedy → Econt → Address', 'speedy_econt_shipping' ),
					'speedy,address,econt' => __( 'Speedy → Address → Econt', 'speedy_econt_shipping' ),
					'econt,speedy,address' => __( 'Econt → Speedy → Address', 'speedy_econt_shipping' ),
					'econt,address,speedy' => __( 'Econt → Address → Speedy', 'speedy_econt_shipping' ),
					'address,speedy,econt' => __( 'Address → Speedy → Econt', 'speedy_econt_shipping' ),
					'address,econt,speedy' => __( 'Address → Econt → Speedy', 'speedy_econt_shipping' ),
				),
			),
			array(
				'title'    => __( 'Emergency Contact', 'speedy_econt_shipping' ),
				'desc'     => __( 'Contact information shown when shipping data fails to load.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_emergency_contact',
				'default'  => '',
				'type'     => 'text',
				'css'      => 'min-width:400px;',
			),
			array(
				'title'    => __( 'Free Shipping Label', 'speedy_econt_shipping' ),
				'desc'     => __( 'Text shown for free shipping. Use "-" for no text.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
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
				'desc'     => __( 'CSS selectors for fields to hide at checkout (comma-separated).', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_hidden_fields',
				'default'  => '#billing_company_field, #billing_country_field, #billing_postcode_field, #ship-to-different-address',
				'type'     => 'textarea',
				'css'      => 'min-width:400px; min-height:80px;',
			),
			array(
				'title'    => __( 'Debug Mode', 'speedy_econt_shipping' ),
				'desc'     => __( 'Enable debug logging (logs saved to WooCommerce log files)', 'speedy_econt_shipping' ),
				'id'       => 'sesh_debug_mode',
				'default'  => 'no',
				'type'     => 'checkbox',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'sesh_general_settings',
			),

			// Label Generation Settings.
			array(
				'title' => __( 'Label Generation Settings', 'speedy_econt_shipping' ),
				'type'  => 'title',
				'desc'  => __( 'Configure automatic shipping label generation.', 'speedy_econt_shipping' ),
				'id'    => 'sesh_label_settings',
			),
			array(
				'title'    => __( 'Auto-Generate Labels', 'speedy_econt_shipping' ),
				'desc'     => __( 'Automatically generate shipping labels when orders reach specified status', 'speedy_econt_shipping' ),
				'id'       => 'sesh_auto_generate_labels',
				'default'  => 'no',
				'type'     => 'checkbox',
				'class'    => 'sesh-auto-generate-labels-toggle',
			),
			array(
				'title'    => __( 'Auto-Generate Status', 'speedy_econt_shipping' ),
				'desc'     => __( 'Order status that triggers automatic label generation.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_auto_generate_status',
				'default'  => 'processing',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select sesh-auto-generate-status',
				'options'  => array(
					'processing' => __( 'Processing', 'speedy_econt_shipping' ),
					'completed'  => __( 'Completed', 'speedy_econt_shipping' ),
					'on-hold'    => __( 'On Hold', 'speedy_econt_shipping' ),
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'sesh_label_settings',
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
					__( 'Configure Speedy courier integration. You need API credentials from Speedy. <a href="%s" target="_blank" rel="noopener noreferrer">Request API access</a>', 'speedy_econt_shipping' ),
					esc_url( 'https://www.speedy.bg' )
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
				'desc'     => __( 'Your Speedy API username.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_speedy_username',
				'default'  => '',
				'type'     => 'text',
				'css'      => 'min-width:300px;',
			),
			array(
				'title'    => __( 'API Password', 'speedy_econt_shipping' ),
				'desc'     => __( 'Your Speedy API password. Stored encrypted in database.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_speedy_password',
				'default'  => '',
				'type'     => 'password',
				'css'      => 'min-width:300px;',
			),
			array(
				'title' => __( 'Test Connection', 'speedy_econt_shipping' ),
				'type'  => 'sesh_test_button',
				'id'    => 'sesh_speedy_test_connection',
				'class' => 'button sesh-test-connection',
				'data'  => array( 'provider' => 'speedy' ),
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
				'desc'     => __( 'Flat rate to use when API pricing is unavailable.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_speedy_fallback_rate',
				'default'  => '0',
				'type'     => 'price',
			),
			array(
				'title'    => __( 'Free Shipping Threshold', 'speedy_econt_shipping' ),
				'desc'     => __( 'Minimum order amount for free shipping. Leave empty to disable.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
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
					__( 'Configure Econt courier integration. API credentials are optional for viewing offices but required for creating shipments. <a href="%s" target="_blank" rel="noopener noreferrer">Get Econt API credentials</a>', 'speedy_econt_shipping' ),
					esc_url( 'https://www.econt.com' )
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
				'desc'     => __( 'Your Econt API username. Required for creating shipments.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_econt_username',
				'default'  => '',
				'type'     => 'text',
				'css'      => 'min-width:300px;',
			),
			array(
				'title'    => __( 'API Password', 'speedy_econt_shipping' ),
				'desc'     => __( 'Your Econt API password. Stored encrypted in database.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_econt_password',
				'default'  => '',
				'type'     => 'password',
				'css'      => 'min-width:300px;',
			),
			array(
				'title' => __( 'Test Connection', 'speedy_econt_shipping' ),
				'type'  => 'sesh_test_button',
				'id'    => 'sesh_econt_test_connection',
				'class' => 'button sesh-test-connection',
				'data'  => array( 'provider' => 'econt' ),
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
				'desc'     => __( 'Flat rate to use when API pricing is unavailable.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_econt_fallback_rate',
				'default'  => '0',
				'type'     => 'price',
			),
			array(
				'title'    => __( 'Free Shipping Threshold', 'speedy_econt_shipping' ),
				'desc'     => __( 'Minimum order amount for free shipping. Leave empty to disable.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
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
				'desc'     => __( 'Label displayed for address delivery option at checkout.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_address_label',
				'default'  => __( 'address', 'speedy_econt_shipping' ),
				'type'     => 'text',
			),
			array(
				'title'    => __( 'Fallback Rate', 'speedy_econt_shipping' ),
				'desc'     => __( 'Address delivery shipping fee.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_address_fallback_rate',
				'default'  => '0',
				'type'     => 'price',
			),
			array(
				'title'    => __( 'Free Shipping Threshold', 'speedy_econt_shipping' ),
				'desc'     => __( 'Minimum order amount for free delivery. Leave empty to disable.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_address_free_from',
				'default'  => '',
				'type'     => 'price',
			),
			array(
				'title'    => __( 'Address Fields (CSS Selectors)', 'speedy_econt_shipping' ),
				'desc'     => __( 'CSS selectors for address fields to show (comma-separated).', 'speedy_econt_shipping' ),
				'desc_tip' => true,
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
	 * Get Sender Address settings.
	 *
	 * @return array
	 */
	private function get_sender_settings() {
		return array(
			array(
				'title' => __( 'Sender Address Configuration', 'speedy_econt_shipping' ),
				'type'  => 'title',
				'desc'  => __( 'Configure the sender address for shipping labels and API requests. This information is required for generating shipping labels.', 'speedy_econt_shipping' ),
				'id'    => 'sesh_sender_settings',
			),
			array(
				'title'    => __( 'Company/Sender Name', 'speedy_econt_shipping' ),
				'desc'     => __( 'Full name or company name of the sender.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_sender_name',
				'default'  => '',
				'type'     => 'text',
				'css'      => 'min-width:400px;',
			),
			array(
				'title'             => __( 'Phone Number', 'speedy_econt_shipping' ),
				'desc'              => __( 'Bulgarian phone number. Format: 0888123456 or +359888123456.', 'speedy_econt_shipping' ),
				'desc_tip'          => true,
				'id'                => 'sesh_sender_phone',
				'default'           => '',
				'type'              => 'text',
				'css'               => 'min-width:300px;',
				'custom_attributes' => array(
					'pattern'     => '^(0[0-9]{9}|\+?3590?[0-9]{9}|003590?[0-9]{9})$',
					'title'       => __( 'Enter a valid Bulgarian phone number', 'speedy_econt_shipping' ),
					'placeholder' => '0888123456',
				),
			),
			array(
				'title'             => __( 'Email Address', 'speedy_econt_shipping' ),
				'desc'              => __( 'Contact email for shipping notifications.', 'speedy_econt_shipping' ),
				'desc_tip'          => true,
				'id'                => 'sesh_sender_email',
				'default'           => get_option( 'admin_email' ),
				'type'              => 'email',
				'css'               => 'min-width:300px;',
				'custom_attributes' => array(
					'placeholder' => 'email@example.com',
				),
			),
			array(
				'title'    => __( 'Region', 'speedy_econt_shipping' ),
				'desc'     => __( 'Bulgarian region/oblast. Auto-filled when city is selected.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_sender_region',
				'default'  => '',
				'type'     => 'text',
				'css'      => 'min-width:300px;',
				'class'    => 'sesh-sender-region',
			),
			array(
				'title'    => __( 'City', 'speedy_econt_shipping' ),
				'desc'     => __( 'City name. Start typing to search carrier databases.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_sender_city',
				'default'  => '',
				'type'     => 'text',
				'css'      => 'min-width:300px;',
				'class'    => 'sesh-sender-city',
			),
			array(
				'title'    => __( 'Street Address', 'speedy_econt_shipping' ),
				'desc'     => __( 'Full street address including building number.', 'speedy_econt_shipping' ),
				'desc_tip' => true,
				'id'       => 'sesh_sender_address',
				'default'  => '',
				'type'     => 'textarea',
				'css'      => 'min-width:400px; min-height:60px;',
			),
			array(
				'title'             => __( 'Post Code', 'speedy_econt_shipping' ),
				'desc'              => __( 'Bulgarian postal code (4 digits).', 'speedy_econt_shipping' ),
				'desc_tip'          => true,
				'id'                => 'sesh_sender_postcode',
				'default'           => '',
				'type'              => 'text',
				'css'               => 'min-width:150px;',
				'custom_attributes' => array(
					'pattern'     => '^[0-9]{4}$',
					'maxlength'   => '4',
					'title'       => __( 'Enter a 4-digit Bulgarian postal code', 'speedy_econt_shipping' ),
					'placeholder' => '1000',
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'sesh_sender_settings',
			),
		);
	}

	/**
	 * Save settings.
	 *
	 * Uses the new WooCommerce 5.5+ get_settings_for_section() method.
	 * Password encryption is handled via pre_update_option filter.
	 */
	public function save() {
		global $current_section;

		// Use the new WooCommerce 5.5+ method to get settings for the current section.
		$settings = $this->get_settings_for_section( $current_section );

		// Save WooCommerce settings (SESH_Settings reads directly from WC options).
		WC_Admin_Settings::save_fields( $settings );
	}

	/**
	 * Output custom test button field.
	 *
	 * @param array $value Field configuration.
	 */
	public function output_test_button_field( $value ) {
		$provider = isset( $value['data']['provider'] ) ? esc_attr( $value['data']['provider'] ) : '';
		$id       = isset( $value['id'] ) ? esc_attr( $value['id'] ) : '';
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<button type="button"
					id="<?php echo esc_attr( $id ); ?>"
					class="button sesh-test-connection"
					data-provider="<?php echo esc_attr( $provider ); ?>">
					<?php esc_html_e( 'Test Connection', 'speedy_econt_shipping' ); ?>
				</button>
				<span class="sesh-test-result" style="margin-left: 10px;"></span>
				<p class="description">
					<?php esc_html_e( 'Save your credentials before testing.', 'speedy_econt_shipping' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Enqueue admin scripts for the settings page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only load on WooCommerce settings page.
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		if ( 'sesh_shipping' !== $tab ) {
			return;
		}

		// Enqueue jQuery UI autocomplete for city search.
		wp_enqueue_script( 'jquery-ui-autocomplete' );

		wp_enqueue_script(
			'sesh-admin-settings',
			plugins_url( 'assets/js/sesh-admin-settings.js', dirname( dirname( __FILE__ ) ) ),
			array( 'jquery', 'jquery-ui-autocomplete' ),
			defined( 'SESH_VERSION' ) ? SESH_VERSION : '2.0.0',
			true
		);

		wp_localize_script(
			'sesh-admin-settings',
			'seshAdmin',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'sesh_test_connection' ),
				'i18n'        => array(
					'testing'    => __( 'Testing...', 'speedy_econt_shipping' ),
					'testButton' => __( 'Test Connection', 'speedy_econt_shipping' ),
					'success'    => __( 'Connection successful!', 'speedy_econt_shipping' ),
					'error'      => __( 'Connection failed:', 'speedy_econt_shipping' ),
				),
			)
		);

		// Also provide seshAdminSettings for city autocomplete compatibility.
		wp_localize_script(
			'sesh-admin-settings',
			'seshAdminSettings',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'sesh_admin_settings' ),
				'i18n'     => array(
					'invalid_phone' => __( 'Invalid Bulgarian phone number format. Use: 0888123456 or +359888123456', 'speedy_econt_shipping' ),
					'city_required' => __( 'Please select a valid city from the list.', 'speedy_econt_shipping' ),
					'testing'       => __( 'Testing...', 'speedy_econt_shipping' ),
					'testButton'    => __( 'Test Connection', 'speedy_econt_shipping' ),
					'success'       => __( 'Connection successful!', 'speedy_econt_shipping' ),
					'error'         => __( 'Connection failed:', 'speedy_econt_shipping' ),
				),
			)
		);
	}

	/**
	 * Validate Speedy credentials when saved.
	 *
	 * Runs asynchronously after option is updated to avoid blocking save.
	 *
	 * @param mixed $old_value Old option value.
	 * @param mixed $new_value New option value.
	 */
	public function validate_speedy_credentials_on_save( $old_value, $new_value ) {
		// Only validate if we're on the settings page and credentials exist.
		if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Get current credentials.
		$username = $this->plugin_settings->get_speedy_username();
		$password = $this->plugin_settings->get_speedy_password();

		// Skip if credentials are empty.
		if ( empty( $username ) || empty( $password ) ) {
			return;
		}

		// Only validate if credentials actually changed.
		static $speedy_validated = false;
		if ( $speedy_validated ) {
			return;
		}
		$speedy_validated = true;

		// Validate asynchronously using a transient to show notice on next page load.
		try {
			if ( ! class_exists( 'SESH_Speedy_API' ) ) {
				return;
			}

			$api    = new SESH_Speedy_API( $username, $password );
			$result = $api->validate_credentials();

			if ( is_wp_error( $result ) ) {
				set_transient(
					'sesh_speedy_validation_error',
					$result->get_error_message(),
					60
				);
			} else {
				delete_transient( 'sesh_speedy_validation_error' );
			}
		} catch ( SESH_API_Exception $e ) {
			set_transient( 'sesh_speedy_validation_error', $e->getMessage(), 60 );
		} catch ( Exception $e ) {
			// Silently fail - don't block the save process.
		}
	}

	/**
	 * Validate Econt credentials when saved.
	 *
	 * Runs asynchronously after option is updated to avoid blocking save.
	 *
	 * @param mixed $old_value Old option value.
	 * @param mixed $new_value New option value.
	 */
	public function validate_econt_credentials_on_save( $old_value, $new_value ) {
		// Only validate if we're on the settings page and credentials exist.
		if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Get current credentials.
		$username = $this->plugin_settings->get_econt_username();
		$password = $this->plugin_settings->get_econt_password();

		// Skip if credentials are empty.
		if ( empty( $username ) || empty( $password ) ) {
			return;
		}

		// Only validate if credentials actually changed.
		static $econt_validated = false;
		if ( $econt_validated ) {
			return;
		}
		$econt_validated = true;

		// Validate asynchronously using a transient to show notice on next page load.
		try {
			if ( ! class_exists( 'SESH_Econt_API' ) ) {
				return;
			}

			$api    = new SESH_Econt_API( $username, $password );
			$result = $api->validate_credentials();

			if ( is_wp_error( $result ) ) {
				set_transient(
					'sesh_econt_validation_error',
					$result->get_error_message(),
					60
				);
			} else {
				delete_transient( 'sesh_econt_validation_error' );
			}
		} catch ( SESH_API_Exception $e ) {
			set_transient( 'sesh_econt_validation_error', $e->getMessage(), 60 );
		} catch ( Exception $e ) {
			// Silently fail - don't block the save process.
		}
	}

	/**
	 * Display validation error notices.
	 *
	 * Called on admin_notices hook to show any credential validation errors.
	 */
	public function display_validation_notices() {
		$speedy_error = get_transient( 'sesh_speedy_validation_error' );
		if ( $speedy_error ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'Speedy API:', 'speedy_econt_shipping' ),
				esc_html( $speedy_error )
			);
			delete_transient( 'sesh_speedy_validation_error' );
		}

		$econt_error = get_transient( 'sesh_econt_validation_error' );
		if ( $econt_error ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'Econt API:', 'speedy_econt_shipping' ),
				esc_html( $econt_error )
			);
			delete_transient( 'sesh_econt_validation_error' );
		}
	}

	/**
	 * AJAX handler for testing Speedy API connection.
	 */
	public function ajax_test_speedy_connection() {
		check_ajax_referer( 'sesh_test_connection', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'speedy_econt_shipping' ) ) );
		}

		$username = $this->plugin_settings->get_speedy_username();
		$password = $this->plugin_settings->get_speedy_password();

		if ( empty( $username ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Please save API credentials first.', 'speedy_econt_shipping' ) ) );
		}

		try {
			$api    = new SESH_Speedy_API( $username, $password );
			$result = $api->validate_credentials();

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			wp_send_json_success( array( 'message' => __( 'Speedy API connection successful!', 'speedy_econt_shipping' ) ) );
		} catch ( SESH_API_Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => __( 'Unexpected error:', 'speedy_econt_shipping' ) . ' ' . $e->getMessage() ) );
		}
	}

	/**
	 * AJAX handler for testing Econt API connection.
	 */
	public function ajax_test_econt_connection() {
		check_ajax_referer( 'sesh_test_connection', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'speedy_econt_shipping' ) ) );
		}

		$username = $this->plugin_settings->get_econt_username();
		$password = $this->plugin_settings->get_econt_password();

		if ( empty( $username ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Please save API credentials first.', 'speedy_econt_shipping' ) ) );
		}

		try {
			$api    = new SESH_Econt_API( $username, $password );
			$result = $api->validate_credentials();

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			wp_send_json_success( array( 'message' => __( 'Econt API connection successful!', 'speedy_econt_shipping' ) ) );
		} catch ( SESH_API_Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => __( 'Unexpected error:', 'speedy_econt_shipping' ) . ' ' . $e->getMessage() ) );
		}
	}
}
