<?php
/**
 * Cart shipping calculator class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cart calculator class.
 *
 * Provides shipping cost estimation on the cart page
 * before customers proceed to checkout.
 */
class SESH_Cart_Calculator {

	/**
	 * Settings instance.
	 *
	 * @var SESH_Settings
	 */
	private $settings;

	/**
	 * Database instance.
	 *
	 * @var SESH_Database
	 */
	private $database;

	/**
	 * Constructor.
	 *
	 * @param SESH_Settings $settings Settings instance.
	 * @param SESH_Database $database Database instance.
	 */
	public function __construct( SESH_Settings $settings, SESH_Database $database ) {
		$this->settings = $settings;
		$this->database = $database;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Add calculator widget to cart page.
		add_action( 'woocommerce_cart_collaterals', array( $this, 'render_calculator' ), 5 );

		// Enqueue scripts on cart page.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_sesh_search_cities', array( $this, 'ajax_search_cities' ) );
		add_action( 'wp_ajax_nopriv_sesh_search_cities', array( $this, 'ajax_search_cities' ) );

		add_action( 'wp_ajax_sesh_estimate_shipping', array( $this, 'ajax_estimate_shipping' ) );
		add_action( 'wp_ajax_nopriv_sesh_estimate_shipping', array( $this, 'ajax_estimate_shipping' ) );
	}

	/**
	 * Check if calculator is enabled.
	 *
	 * @return bool
	 */
	private function is_calculator_enabled() {
		// Check if feature is enabled in settings.
		$enabled = $this->settings->get( 'general', 'cart_calculator_enabled' );

		// Default to enabled if setting doesn't exist.
		if ( null === $enabled ) {
			return true;
		}

		return (bool) $enabled;
	}

	/**
	 * Render calculator widget on cart page.
	 */
	public function render_calculator() {
		// Only show if enabled.
		if ( ! $this->is_calculator_enabled() ) {
			return;
		}

		// Only show if cart has items.
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		// Load template.
		$template_path = SESH_PLUGIN_DIR . 'templates/cart/shipping-calculator.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts() {
		// Only load on cart page.
		if ( ! is_cart() ) {
			return;
		}

		// Only load if calculator is enabled.
		if ( ! $this->is_calculator_enabled() ) {
			return;
		}

		// Enqueue Select2 for city search.
		wp_enqueue_style( 'select2' );
		wp_enqueue_script( 'select2' );

		// Enqueue calculator JavaScript.
		wp_enqueue_script(
			'sesh-cart-calculator',
			SESH_PLUGIN_URL . 'assets/js/sesh-cart-calculator.js',
			array( 'jquery', 'select2', 'wc-cart' ),
			SESH_VERSION,
			true
		);

		// Localize script with data.
		wp_localize_script(
			'sesh-cart-calculator',
			'sesh_cart_config',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'sesh_cart_calculator' ),
				'currency_symbol' => html_entity_decode( get_woocommerce_currency_symbol() ),
				'i18n'            => array(
					'select_city'         => __( 'Type city name...', 'speedy_econt_shipping' ),
					'loading'             => __( 'Loading...', 'speedy_econt_shipping' ),
					'error'               => __( 'Error calculating shipping', 'speedy_econt_shipping' ),
					'select_city_first'   => __( 'Please select a city', 'speedy_econt_shipping' ),
					'free_shipping'       => __( 'You qualify for free shipping!', 'speedy_econt_shipping' ),
					'add_for_free'        => __( 'Add %s more for free shipping!', 'speedy_econt_shipping' ),
					'calculation_failed'  => __( 'Unable to calculate shipping', 'speedy_econt_shipping' ),
				),
			)
		);

		// Enqueue calculator styles.
		wp_enqueue_style(
			'sesh-cart-calculator',
			SESH_PLUGIN_URL . 'assets/css/cart-calculator.css',
			array(),
			SESH_VERSION
		);
	}

	/**
	 * AJAX handler for city search.
	 */
	public function ajax_search_cities() {
		check_ajax_referer( 'sesh_cart_calculator', 'nonce' );

		$carrier = isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : '';
		$search  = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		// Validate carrier.
		if ( ! in_array( $carrier, array( 'speedy', 'econt' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid carrier', 'speedy_econt_shipping' ) ) );
		}

		// Require at least 2 characters.
		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		// Search cities in database.
		$cities = $this->database->search_sites( $carrier, $search, 20 );

		// Format results for Select2.
		$results = array();
		foreach ( $cities as $city ) {
			$results[] = array(
				'id'   => $city->id,
				'text' => $this->format_city_text( $city ),
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * Format city text for dropdown.
	 *
	 * @param object $city City data.
	 * @return string
	 */
	private function format_city_text( $city ) {
		$text = esc_html( $city->name );

		if ( ! empty( $city->region ) && $city->region !== $city->name ) {
			$text .= ' (' . esc_html( $city->region ) . ')';
		}

		return $text;
	}

	/**
	 * AJAX handler for shipping estimation.
	 */
	public function ajax_estimate_shipping() {
		check_ajax_referer( 'sesh_cart_calculator', 'nonce' );

		$carrier       = isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : '';
		$delivery_type = isset( $_POST['delivery_type'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_type'] ) ) : '';
		$city_id       = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;

		// Validate inputs.
		if ( ! in_array( $carrier, array( 'speedy', 'econt' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid carrier', 'speedy_econt_shipping' ) ) );
		}

		if ( ! in_array( $delivery_type, array( 'office', 'address' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid delivery type', 'speedy_econt_shipping' ) ) );
		}

		// Econt only supports office delivery.
		if ( 'econt' === $carrier && 'address' === $delivery_type ) {
			wp_send_json_error( array( 'message' => __( 'Address delivery is not available for Econt', 'speedy_econt_shipping' ) ) );
		}

		if ( empty( $city_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select a city', 'speedy_econt_shipping' ) ) );
		}

		// Check if cart exists.
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			wp_send_json_error( array( 'message' => __( 'Cart is empty', 'speedy_econt_shipping' ) ) );
		}

		// Get cart data.
		$cart_total  = WC()->cart->get_subtotal();
		$cart_weight = WC()->cart->get_cart_contents_weight();

		// Get shipping method instance.
		$method = $this->get_shipping_method_instance( $carrier, $delivery_type );
		if ( ! $method ) {
			wp_send_json_error( array( 'message' => __( 'Shipping method not available', 'speedy_econt_shipping' ) ) );
		}

		// Get free shipping threshold.
		$free_threshold = $this->get_free_shipping_threshold( $carrier );

		// Check if qualifies for free shipping.
		if ( $free_threshold > 0 && $cart_total >= $free_threshold ) {
			wp_send_json_success(
				array(
					'price'                   => 0,
					'formatted_price'         => wc_price( 0 ),
					'delivery_time'           => $this->get_delivery_time( $carrier ),
					'free_shipping_remaining' => 0,
					'formatted_remaining'     => wc_price( 0 ),
					'is_free'                 => true,
				)
			);
		}

		// Get shipping estimate.
		$estimate = $this->calculate_estimate( $carrier, $city_id, $cart_weight, $delivery_type );

		if ( is_wp_error( $estimate ) ) {
			wp_send_json_error( array( 'message' => $estimate->get_error_message() ) );
		}

		// Calculate remaining for free shipping.
		$remaining = max( 0, $free_threshold - $cart_total );

		wp_send_json_success(
			array(
				'price'                   => $estimate['price'],
				'formatted_price'         => wc_price( $estimate['price'] ),
				'delivery_time'           => $estimate['delivery_time'],
				'free_shipping_remaining' => $remaining,
				'formatted_remaining'     => wc_price( $remaining ),
				'is_free'                 => false,
			)
		);
	}

	/**
	 * Get shipping method instance.
	 *
	 * @param string $carrier       Carrier (speedy or econt).
	 * @param string $delivery_type Delivery type (office or address).
	 * @return WC_Shipping_Method|null
	 */
	private function get_shipping_method_instance( $carrier, $delivery_type ) {
		$method_id = 'address' === $delivery_type ? 'sesh_address' : 'sesh_' . $carrier;

		// Get all shipping zones.
		$zones = WC_Shipping_Zones::get_zones();

		// Try to find method in zones.
		foreach ( $zones as $zone ) {
			foreach ( $zone['shipping_methods'] as $method ) {
				if ( $method->id === $method_id && $method->is_enabled() ) {
					return $method;
				}
			}
		}

		// Try worldwide zone (zone 0).
		$worldwide_zone = new WC_Shipping_Zone( 0 );
		foreach ( $worldwide_zone->get_shipping_methods() as $method ) {
			if ( $method->id === $method_id && $method->is_enabled() ) {
				return $method;
			}
		}

		return null;
	}

	/**
	 * Get free shipping threshold for carrier.
	 *
	 * @param string $carrier Carrier (speedy or econt).
	 * @return float
	 */
	private function get_free_shipping_threshold( $carrier ) {
		$threshold = $this->settings->get( $carrier, 'free_shipping_threshold' );
		return $threshold ? (float) $threshold : 0;
	}

	/**
	 * Get delivery time estimate.
	 *
	 * @param string $carrier Carrier (speedy or econt).
	 * @return string
	 */
	private function get_delivery_time( $carrier ) {
		// Default delivery time estimates.
		$delivery_times = array(
			'speedy' => __( '1-2 business days', 'speedy_econt_shipping' ),
			'econt'  => __( '1-3 business days', 'speedy_econt_shipping' ),
		);

		return isset( $delivery_times[ $carrier ] ) ? $delivery_times[ $carrier ] : __( '1-3 business days', 'speedy_econt_shipping' );
	}

	/**
	 * Calculate shipping estimate.
	 *
	 * @param string $carrier       Carrier (speedy or econt).
	 * @param int    $city_id       City ID.
	 * @param float  $weight        Package weight.
	 * @param string $delivery_type Delivery type (office or address).
	 * @return array|WP_Error Estimate data or error.
	 */
	private function calculate_estimate( $carrier, $city_id, $weight, $delivery_type ) {
		// Get method instance.
		$method = $this->get_shipping_method_instance( $carrier, $delivery_type );
		if ( ! $method ) {
			return new WP_Error( 'no_method', __( 'Shipping method not available', 'speedy_econt_shipping' ) );
		}

		// Check for configured flat rate.
		$flat_rate = $method->get_option( 'cost' );
		if ( ! empty( $flat_rate ) && is_numeric( $flat_rate ) ) {
			return array(
				'price'         => (float) $flat_rate,
				'delivery_time' => $this->get_delivery_time( $carrier ),
			);
		}

		// Try to get API-based price.
		$api_price = $this->get_api_estimate( $carrier, $city_id, $weight, $delivery_type );
		if ( ! is_wp_error( $api_price ) ) {
			return array(
				'price'         => $api_price,
				'delivery_time' => $this->get_delivery_time( $carrier ),
			);
		}

		// Fall back to settings-based rate.
		$fallback_rate = $this->settings->get( $carrier, 'fallback_rate' );
		if ( ! empty( $fallback_rate ) && is_numeric( $fallback_rate ) ) {
			return array(
				'price'         => (float) $fallback_rate,
				'delivery_time' => $this->get_delivery_time( $carrier ),
			);
		}

		return new WP_Error( 'no_rate', __( 'Unable to calculate shipping cost', 'speedy_econt_shipping' ) );
	}

	/**
	 * Get API-based shipping estimate.
	 *
	 * @param string $carrier       Carrier (speedy or econt).
	 * @param int    $city_id       City ID.
	 * @param float  $weight        Package weight.
	 * @param string $delivery_type Delivery type.
	 * @return float|WP_Error Price or error.
	 */
	private function get_api_estimate( $carrier, $city_id, $weight, $delivery_type ) {
		// Check if API is available.
		$plugin = SESH_Plugin::instance();
		if ( ! $plugin ) {
			return new WP_Error( 'no_plugin', __( 'Plugin not initialized', 'speedy_econt_shipping' ) );
		}

		// Get API client.
		$api_client = 'speedy' === $carrier ? $plugin->get_speedy_api() : $plugin->get_econt_api();
		if ( ! $api_client ) {
			return new WP_Error( 'no_api', __( 'API not available', 'speedy_econt_shipping' ) );
		}

		// Get city data.
		$city = $this->database->get_site_by_id( $carrier, $city_id );
		if ( ! $city ) {
			return new WP_Error( 'invalid_city', __( 'Invalid city', 'speedy_econt_shipping' ) );
		}

		// Ensure minimum weight.
		$weight = max( $weight, 0.5 );

		try {
			// Try to call API (method signatures may vary).
			if ( method_exists( $api_client, 'calculate_shipping' ) ) {
				$params = array(
					'to_city_id'    => $city_id,
					'weight'        => $weight,
					'delivery_type' => $delivery_type,
				);

				$quote = $api_client->calculate_shipping( $params );

				if ( is_wp_error( $quote ) ) {
					return $quote;
				}

				// Extract price from quote.
				if ( is_object( $quote ) && isset( $quote->price ) ) {
					return (float) $quote->price;
				} elseif ( is_array( $quote ) && isset( $quote['price'] ) ) {
					return (float) $quote['price'];
				}
			}

			return new WP_Error( 'api_error', __( 'API calculation not available', 'speedy_econt_shipping' ) );

		} catch ( Exception $e ) {
			return new WP_Error( 'exception', $e->getMessage() );
		}
	}
}
