<?php
/**
 * Frontend class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frontend class.
 *
 * Handles all frontend functionality including checkout field customization,
 * office selection, and shipping calculations display.
 *
 * Note: The legacy frontend functions are still active for backward compatibility.
 * This class will gradually take over frontend functionality in future updates.
 */
class SESH_Frontend {

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
	 * Cart calculator instance.
	 *
	 * @var SESH_Cart_Calculator
	 */
	private $cart_calculator;

	/**
	 * Customer tracking instance.
	 *
	 * @var SESH_Customer_Tracking
	 */
	private $customer_tracking;

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
		$this->init_cart_calculator();
		$this->init_customer_tracking();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Enqueue frontend scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add checkout fields for carrier selection.
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_checkout_fields' ) );

		// AJAX handlers for checkout.
		add_action( 'wp_ajax_sesh_search_cities_checkout', array( $this, 'ajax_search_cities_checkout' ) );
		add_action( 'wp_ajax_nopriv_sesh_search_cities_checkout', array( $this, 'ajax_search_cities_checkout' ) );

		add_action( 'wp_ajax_sesh_get_offices_lazy', array( $this, 'ajax_get_offices_lazy' ) );
		add_action( 'wp_ajax_nopriv_sesh_get_offices_lazy', array( $this, 'ajax_get_offices_lazy' ) );

		add_action( 'wp_ajax_sesh_calculate_checkout_shipping', array( $this, 'ajax_calculate_checkout_shipping' ) );
		add_action( 'wp_ajax_nopriv_sesh_calculate_checkout_shipping', array( $this, 'ajax_calculate_checkout_shipping' ) );

		add_action( 'wp_ajax_sesh_search_streets', array( $this, 'ajax_search_streets' ) );
		add_action( 'wp_ajax_nopriv_sesh_search_streets', array( $this, 'ajax_search_streets' ) );

		// Legacy AJAX handlers (kept for backward compatibility).
		add_action( 'wp_ajax_sesh_get_offices', array( $this, 'ajax_get_offices' ) );
		add_action( 'wp_ajax_nopriv_sesh_get_offices', array( $this, 'ajax_get_offices' ) );

		add_action( 'wp_ajax_sesh_get_cities', array( $this, 'ajax_get_cities' ) );
		add_action( 'wp_ajax_nopriv_sesh_get_cities', array( $this, 'ajax_get_cities' ) );

		// Checkout validation and order meta saving.
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_checkout_fields' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_checkout_shipping_meta' ), 10, 2 );
	}

	/**
	 * Initialize cart calculator.
	 */
	private function init_cart_calculator() {
		require_once SESH_PLUGIN_DIR . 'includes/class-sesh-cart-calculator.php';
		$this->cart_calculator = new SESH_Cart_Calculator( $this->settings, $this->database );
	}

	/**
	 * Initialize customer tracking.
	 */
	private function init_customer_tracking() {
		// Get plugin instance to access API clients.
		$plugin = SESH_Plugin::instance();

		// Initialize label manager.
		$label_manager = new SESH_Label_Manager(
			$this->database,
			$plugin->get_speedy_api(),
			$plugin->get_econt_api()
		);

		// Initialize customer tracking.
		$this->customer_tracking = new SESH_Customer_Tracking( $this->database, $label_manager );
	}

	/**
	 * Enqueue frontend scripts and styles.
	 */
	public function enqueue_scripts() {
		// Only load on checkout and cart pages.
		if ( ! ( is_checkout() || is_cart() ) ) {
			return;
		}

		// Enqueue Select2 for dropdowns.
		wp_enqueue_style( 'select2' );
		wp_enqueue_script( 'select2' );

		// Base frontend styles.
		wp_enqueue_style(
			'sesh-frontend',
			SESH_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			SESH_VERSION
		);

		// Modern checkout UI styles.
		wp_enqueue_style(
			'sesh-checkout-css',
			SESH_PLUGIN_URL . 'assets/css/sesh-checkout.css',
			array( 'sesh-frontend', 'select2' ),
			SESH_VERSION
		);

		// Only load checkout scripts on checkout page.
		if ( ! is_checkout() ) {
			return;
		}

		// Enqueue modern JavaScript modules.
		// 1. Price Display (no dependencies).
		wp_enqueue_script(
			'sesh-price-display',
			SESH_PLUGIN_URL . 'assets/js/sesh-price-display.js',
			array( 'jquery' ),
			SESH_VERSION,
			true
		);

		// 2. Location Selector (depends on price display).
		wp_enqueue_script(
			'sesh-location-selector',
			SESH_PLUGIN_URL . 'assets/js/sesh-location-selector.js',
			array( 'jquery', 'select2', 'sesh-price-display' ),
			SESH_VERSION,
			true
		);

		// 3. Checkout main module (depends on both above).
		wp_enqueue_script(
			'sesh-checkout',
			SESH_PLUGIN_URL . 'assets/js/sesh-checkout.js',
			array( 'jquery', 'wc-checkout', 'sesh-price-display', 'sesh-location-selector' ),
			SESH_VERSION,
			true
		);

		// Localize script with checkout data.
		wp_localize_script(
			'sesh-checkout',
			'sesh_checkout_params',
			$this->get_checkout_params()
		);
	}

	/**
	 * Get checkout parameters for JavaScript.
	 *
	 * @return array
	 */
	private function get_checkout_params() {
		// Build delivery options from settings.
		$delivery_options        = $this->build_delivery_options();
		$default_shipping_method = $this->get_default_shipping_method();

		// Build field selectors.
		$selectors = $this->get_field_selectors();

		// Get show store messages from settings.
		$show_store_messages = array_filter(
			array_map( 'trim', explode( ',', $this->settings->get_show_store_messages() ) )
		);

		return array(
			'ajax_url'                => admin_url( 'admin-ajax.php' ),
			'nonce'                   => wp_create_nonce( 'sesh_frontend_nonce' ),
			'delivery_options'        => $delivery_options,
			'default_shipping_method' => $default_shipping_method,
			'currency_symbol'         => html_entity_decode( get_woocommerce_currency_symbol() ),
			'shop_url'                => get_permalink( wc_get_page_id( 'shop' ) ),
			'calculate_final_price'   => $this->settings->is_calculate_final_price(),
			'delivery_price_selector' => $this->settings->get_delivery_price_selector(),
			'free_shipping_suffix'    => $this->settings->get_free_shipping_label_suffix(),
			'show_store_messages'     => $show_store_messages,
			'shipping_to_id'          => 'shipping-to-row',
			'selectors'               => $selectors,
			'i18n'                    => array(
				'select_region'          => __( 'Select region', 'speedy_econt_shipping' ),
				'select_city'            => __( 'Please select a city for delivery.', 'speedy_econt_shipping' ),
				'select_office'          => __( 'Please select a pickup office.', 'speedy_econt_shipping' ),
				'type_city_name'         => __( 'Type city name...', 'speedy_econt_shipping' ),
				'type_street_name'       => __( 'Type street name...', 'speedy_econt_shipping' ),
				'searching'              => __( 'Searching...', 'speedy_econt_shipping' ),
				'no_results'             => __( 'No results found', 'speedy_econt_shipping' ),
				'loading'                => __( 'Loading...', 'speedy_econt_shipping' ),
				'loading_offices'        => __( 'Loading offices...', 'speedy_econt_shipping' ),
				'calculating_price'      => __( 'Calculating price...', 'speedy_econt_shipping' ),
				'error'                  => __( 'Error loading data', 'speedy_econt_shipping' ),
				'error_search'           => __( 'Unable to search. Please try again.', 'speedy_econt_shipping' ),
				'error_load_offices'     => __( 'Unable to load offices. Please try again.', 'speedy_econt_shipping' ),
				'error_calculate_price'  => __( 'Unable to calculate price', 'speedy_econt_shipping' ),
				'retry'                  => __( 'Retry', 'speedy_econt_shipping' ),
				'estimated'              => __( '(estimated)', 'speedy_econt_shipping' ),
				'delivery'               => __( 'delivery', 'speedy_econt_shipping' ),
				'free'                   => __( 'for free', 'speedy_econt_shipping' ),
				'congrats_free_delivery' => __( 'Congrats, you won free delivery using %s!', 'speedy_econt_shipping' ),
				'left_till_free'         => __( 'Still left %s', 'speedy_econt_shipping' ),
				'to_shop'                => __( 'To shop', 'speedy_econt_shipping' ),
				'no_free_shipping'       => __( 'Sorry, there is no free shipping available for the option chosen: %s', 'speedy_econt_shipping' ),
				'min_chars'              => __( 'Type at least 2 characters', 'speedy_econt_shipping' ),
			),
		);
	}

	/**
	 * Build delivery options array for JavaScript.
	 *
	 * @return array
	 */
	private function build_delivery_options() {
		$options = array();
		$order   = $this->settings->get_shipping_options_order();

		foreach ( $order as $carrier ) {
			switch ( $carrier ) {
				case 'speedy':
					if ( $this->settings->is_speedy_enabled() ) {
						$options['speedy'] = array(
							'id'        => 'shipping_method_0_sesh_speedy',
							'name'      => 'speedy',
							'label'     => __( 'Speedy Office', 'speedy_econt_shipping' ),
							'shipping'  => $this->settings->get_speedy_shipping(),
							'free_from' => $this->settings->get_speedy_free_from(),
						);
					}
					break;

				case 'econt':
					if ( $this->settings->is_econt_enabled() ) {
						$options['econt'] = array(
							'id'        => 'shipping_method_0_sesh_econt',
							'name'      => 'econt',
							'label'     => __( 'Econt Office', 'speedy_econt_shipping' ),
							'shipping'  => $this->settings->get_econt_shipping(),
							'free_from' => $this->settings->get_econt_free_from(),
						);
					}
					break;

				case 'address':
					if ( $this->settings->is_address_enabled() ) {
						$options['address'] = array(
							'id'        => 'shipping_method_0_sesh_address',
							'name'      => 'address',
							'label'     => $this->settings->get_address_label(),
							'shipping'  => $this->settings->get_address_shipping(),
							'free_from' => $this->settings->get_address_free_from(),
						);
					}
					break;
			}
		}

		return $options;
	}

	/**
	 * Get default shipping method from settings.
	 *
	 * @return string
	 */
	private function get_default_shipping_method() {
		$order = $this->settings->get_shipping_options_order();
		return ! empty( $order ) ? $order[0] : '';
	}

	/**
	 * Get field selectors for JavaScript.
	 *
	 * @return array
	 */
	private function get_field_selectors() {
		return array(
			// Speedy selectors (city autocomplete replaces region dropdown).
			'speedy_city_sel'         => '#speedy_city',
			'speedy_city_id_sel'      => '#speedy_city_id',
			'speedy_office_sel'       => '#speedy_office',
			'speedy_city_field'       => '#speedy_city_field',
			'speedy_office_field'     => '#speedy_office_field',
			'speedy_office_preview'   => '#speedy_office_preview',
			'speedy_price_display'    => '#speedy_price_display',

			// Econt selectors (city autocomplete replaces region dropdown).
			'econt_city_sel'          => '#econt_city',
			'econt_city_id_sel'       => '#econt_city_id',
			'econt_office_sel'        => '#econt_office',
			'econt_city_field'        => '#econt_city_field',
			'econt_office_field'      => '#econt_office_field',
			'econt_office_preview'    => '#econt_office_preview',
			'econt_price_display'     => '#econt_price_display',

			// Address selectors.
			'address_region_sel'      => '#billing_state',
			'address_city_sel'        => '#billing_city',
			'address_street_sel'      => '#billing_address_1',
			'address_region_field'    => '#billing_state_field',
			'address_city_field'      => '#billing_city_field',
			'address_street_field'    => '#billing_address_1_field',

			// Shipping to field.
			'shipping_to_field'       => '#shipping-to-row',
		);
	}

	/**
	 * AJAX handler for getting offices.
	 */
	public function ajax_get_offices() {
		check_ajax_referer( 'sesh_frontend_nonce', 'nonce' );

		$carrier   = isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : '';
		$city_name = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';

		if ( empty( $carrier ) || empty( $city_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'speedy_econt_shipping' ) ) );
		}

		if ( ! in_array( $carrier, array( 'speedy', 'econt' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid carrier', 'speedy_econt_shipping' ) ) );
		}

		$offices = $this->database->get_offices_by_city( $carrier, $city_name );

		if ( empty( $offices ) ) {
			wp_send_json_success( array( 'offices' => array() ) );
		}

		$formatted_offices = array();
		foreach ( $offices as $office ) {
			$formatted_offices[] = array(
				'id'      => $office->id,
				'name'    => $office->name,
				'address' => $office->address,
			);
		}

		wp_send_json_success( array( 'offices' => $formatted_offices ) );
	}

	/**
	 * AJAX handler for getting cities by region.
	 *
	 * @deprecated Use ajax_search_cities_checkout() instead.
	 */
	public function ajax_get_cities() {
		check_ajax_referer( 'sesh_frontend_nonce', 'nonce' );

		$carrier = isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : '';
		$region  = isset( $_POST['region'] ) ? sanitize_text_field( wp_unslash( $_POST['region'] ) ) : '';

		if ( empty( $carrier ) || empty( $region ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'speedy_econt_shipping' ) ) );
		}

		if ( ! in_array( $carrier, array( 'speedy', 'econt' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid carrier', 'speedy_econt_shipping' ) ) );
		}

		$cities = $this->database->get_cities_by_region( $carrier, $region );

		if ( empty( $cities ) ) {
			wp_send_json_success( array( 'cities' => array() ) );
		}

		$formatted_cities = array();
		foreach ( $cities as $city ) {
			$formatted_cities[] = array(
				'id'           => $city->id,
				'name'         => $city->name,
				'municipality' => isset( $city->municipality ) ? $city->municipality : '',
			);
		}

		wp_send_json_success( array( 'cities' => $formatted_cities ) );
	}

	/**
	 * AJAX handler for city autocomplete search.
	 *
	 * Returns cities in Select2 format for autocomplete.
	 */
	public function ajax_search_cities_checkout() {
		check_ajax_referer( 'sesh_frontend_nonce', 'nonce' );

		$carrier = isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : '';
		$search  = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		if ( ! in_array( $carrier, array( 'speedy', 'econt' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid carrier', 'speedy_econt_shipping' ) ) );
		}

		// Require at least 2 characters for search.
		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		// Search cities in database.
		$cities = $this->database->search_cities( $carrier, $search, 20 );

		// Format results for Select2.
		$results = array();
		foreach ( $cities as $city ) {
			$text = esc_html( $city->name );

			// Add region for disambiguation if different from city name.
			if ( ! empty( $city->region ) && $city->region !== $city->name ) {
				$text .= ' (' . esc_html( $city->region ) . ')';
			}

			// Add municipality if available and different.
			if ( ! empty( $city->municipality ) && $city->municipality !== $city->name && $city->municipality !== $city->region ) {
				$text .= ', ' . esc_html( $city->municipality );
			}

			$results[] = array(
				'id'           => $city->id,
				'text'         => $text,
				'name'         => $city->name,
				'region'       => $city->region,
				'municipality' => isset( $city->municipality ) ? $city->municipality : '',
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * AJAX handler for lazy loading offices by city ID.
	 *
	 * Returns extended office data including working hours and phone.
	 */
	public function ajax_get_offices_lazy() {
		check_ajax_referer( 'sesh_frontend_nonce', 'nonce' );

		$carrier = isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : '';
		$city_id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;

		if ( empty( $carrier ) || empty( $city_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'speedy_econt_shipping' ) ) );
		}

		if ( ! in_array( $carrier, array( 'speedy', 'econt' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid carrier', 'speedy_econt_shipping' ) ) );
		}

		// Get offices by city ID.
		$offices = $this->database->get_offices_by_city_id( $carrier, $city_id );

		if ( empty( $offices ) ) {
			wp_send_json_success( array( 'offices' => array() ) );
		}

		$formatted_offices = array();
		foreach ( $offices as $office ) {
			$formatted_offices[] = array(
				'id'            => $office->id,
				'name'          => $office->name,
				'address'       => $office->address,
				'working_hours' => isset( $office->working_hours ) ? $office->working_hours : '',
				'phone'         => isset( $office->phone ) ? $office->phone : '',
				'lat'           => isset( $office->lat ) ? $office->lat : null,
				'lng'           => isset( $office->lng ) ? $office->lng : null,
			);
		}

		wp_send_json_success( array( 'offices' => $formatted_offices ) );
	}

	/**
	 * AJAX handler for real-time shipping price calculation.
	 *
	 * @since 3.0.0
	 */
	public function ajax_calculate_checkout_shipping() {
		check_ajax_referer( 'sesh_frontend_nonce', 'nonce' );

		$carrier       = isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : '';
		$delivery_type = isset( $_POST['delivery_type'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_type'] ) ) : 'office';
		$city_id       = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;
		$office_id     = isset( $_POST['office_id'] ) ? absint( $_POST['office_id'] ) : 0;

		// Validate carrier.
		if ( ! in_array( $carrier, array( 'speedy', 'econt', 'address' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid carrier', 'speedy_econt_shipping' ) ) );
		}

		// Validate delivery type.
		if ( ! in_array( $delivery_type, array( 'office', 'address' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid delivery type', 'speedy_econt_shipping' ) ) );
		}

		// Get cart data.
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			wp_send_json_error( array( 'message' => __( 'Cart is empty', 'speedy_econt_shipping' ) ) );
		}

		$cart_total  = WC()->cart->get_subtotal();
		$cart_weight = WC()->cart->get_cart_contents_weight();

		// Ensure minimum weight.
		$cart_weight = max( $cart_weight, 0.5 );

		// Check for free shipping.
		$free_threshold = $this->get_free_shipping_threshold( $carrier );
		$is_free        = ( $free_threshold > 0 && $cart_total >= $free_threshold );

		if ( $is_free ) {
			// Store calculated price in WooCommerce session for shipping method to use.
			$this->store_shipping_data_in_session( $carrier, $delivery_type, $city_id, $office_id, 0, true );

			wp_send_json_success(
				array(
					'price'           => 0,
					'formatted_price' => wc_price( 0 ),
					'delivery_time'   => $this->get_delivery_time_estimate( $carrier ),
					'is_free'         => true,
					'breakdown'       => array(
						'base_price'  => 0,
						'discount'    => 0,
						'final_price' => 0,
					),
				)
			);
		}

		// Try to get API-based price with caching.
		$cache_key    = $this->build_price_cache_key( $carrier, $delivery_type, $city_id, $office_id, $cart_weight );
		$cached_price = $this->database->get_cache( $cache_key );

		if ( false !== $cached_price ) {
			// Store in session even for cached prices.
			$this->store_shipping_data_in_session( $carrier, $delivery_type, $city_id, $office_id, $cached_price['price'], false );

			wp_send_json_success(
				array(
					'price'           => $cached_price['price'],
					'formatted_price' => wc_price( $cached_price['price'] ),
					'delivery_time'   => $cached_price['delivery_time'],
					'is_free'         => false,
					'breakdown'       => $cached_price['breakdown'],
					'cached'          => true,
				)
			);
		}

		// Calculate price via API.
		$price_data = $this->calculate_shipping_price( $carrier, $delivery_type, $city_id, $office_id, $cart_weight );

		if ( is_wp_error( $price_data ) ) {
			// Fall back to flat rate from settings.
			$fallback_price = $this->get_fallback_shipping_price( $carrier );

			// Store fallback price in session.
			$this->store_shipping_data_in_session( $carrier, $delivery_type, $city_id, $office_id, $fallback_price, false );

			wp_send_json_success(
				array(
					'price'           => $fallback_price,
					'formatted_price' => wc_price( $fallback_price ),
					'delivery_time'   => $this->get_delivery_time_estimate( $carrier ),
					'is_free'         => false,
					'is_fallback'     => true,
					'breakdown'       => array(
						'base_price'  => $fallback_price,
						'discount'    => 0,
						'final_price' => $fallback_price,
					),
				)
			);
		}

		// Cache the result for 5 minutes.
		$this->database->set_cache( $cache_key, $price_data, $carrier, 'shipping_quote', 300 );

		// Store calculated price in WooCommerce session.
		$this->store_shipping_data_in_session( $carrier, $delivery_type, $city_id, $office_id, $price_data['price'], false );

		wp_send_json_success(
			array(
				'price'           => $price_data['price'],
				'formatted_price' => wc_price( $price_data['price'] ),
				'delivery_time'   => $price_data['delivery_time'],
				'is_free'         => false,
				'breakdown'       => $price_data['breakdown'],
			)
		);
	}

	/**
	 * Store shipping data in WooCommerce session for shipping method to use.
	 *
	 * @since 3.0.0
	 *
	 * @param string $carrier       Carrier name (speedy/econt/address).
	 * @param string $delivery_type Delivery type (office/address).
	 * @param int    $city_id       City ID.
	 * @param int    $office_id     Office ID.
	 * @param float  $price         Calculated price.
	 * @param bool   $is_free       Whether shipping is free.
	 */
	private function store_shipping_data_in_session( $carrier, $delivery_type, $city_id, $office_id, $price, $is_free ) {
		if ( ! WC()->session ) {
			return;
		}

		WC()->session->set(
			'sesh_shipping_data',
			array(
				'carrier'       => $carrier,
				'delivery_type' => $delivery_type,
				'city_id'       => $city_id,
				'office_id'     => $office_id,
				'price'         => $price,
				'is_free'       => $is_free,
				'timestamp'     => time(),
			)
		);
	}

	/**
	 * AJAX handler for street autocomplete search.
	 *
	 * @since 3.0.0
	 */
	public function ajax_search_streets() {
		check_ajax_referer( 'sesh_frontend_nonce', 'nonce' );

		$carrier = isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : '';
		$city_id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;
		$search  = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		if ( empty( $carrier ) || empty( $city_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'speedy_econt_shipping' ) ) );
		}

		if ( ! in_array( $carrier, array( 'speedy', 'econt' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid carrier', 'speedy_econt_shipping' ) ) );
		}

		// Require at least 2 characters.
		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		// Get API client.
		$plugin     = SESH_Plugin::instance();
		$api_client = 'speedy' === $carrier ? $plugin->get_speedy_api() : $plugin->get_econt_api();

		if ( ! $api_client ) {
			wp_send_json_error( array( 'message' => __( 'API not available', 'speedy_econt_shipping' ) ) );
		}

		// Call API to get streets.
		$streets = array();
		try {
			if ( method_exists( $api_client, 'get_streets' ) ) {
				$streets = $api_client->get_streets( $city_id, $search );
			}
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}

		if ( is_wp_error( $streets ) ) {
			wp_send_json_error( array( 'message' => $streets->get_error_message() ) );
		}

		// Format results for Select2.
		$results = array();
		if ( is_array( $streets ) ) {
			foreach ( $streets as $street ) {
				$street_id   = is_object( $street ) ? $street->id : ( isset( $street['id'] ) ? $street['id'] : '' );
				$street_name = is_object( $street ) ? $street->name : ( isset( $street['name'] ) ? $street['name'] : '' );

				if ( $street_id && $street_name ) {
					$results[] = array(
						'id'   => $street_id,
						'text' => $street_name,
					);
				}
			}
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * Get free shipping threshold for a carrier.
	 *
	 * @param string $carrier Carrier name.
	 * @return float Threshold amount, 0 if disabled, -1 if not available.
	 */
	private function get_free_shipping_threshold( $carrier ) {
		switch ( $carrier ) {
			case 'speedy':
				$threshold = $this->settings->get_speedy_free_from();
				break;
			case 'econt':
				$threshold = $this->settings->get_econt_free_from();
				break;
			case 'address':
				$threshold = $this->settings->get_address_free_from();
				break;
			default:
				$threshold = -1;
		}

		return (float) $threshold;
	}

	/**
	 * Get delivery time estimate for a carrier.
	 *
	 * @param string $carrier Carrier name.
	 * @return string Delivery time estimate.
	 */
	private function get_delivery_time_estimate( $carrier ) {
		$delivery_times = array(
			'speedy'  => __( '1-2 business days', 'speedy_econt_shipping' ),
			'econt'   => __( '1-3 business days', 'speedy_econt_shipping' ),
			'address' => __( '2-4 business days', 'speedy_econt_shipping' ),
		);

		return isset( $delivery_times[ $carrier ] ) ? $delivery_times[ $carrier ] : __( '1-3 business days', 'speedy_econt_shipping' );
	}

	/**
	 * Get fallback shipping price from settings.
	 *
	 * @param string $carrier Carrier name.
	 * @return float Fallback price.
	 */
	private function get_fallback_shipping_price( $carrier ) {
		switch ( $carrier ) {
			case 'speedy':
				return (float) $this->settings->get_speedy_shipping();
			case 'econt':
				return (float) $this->settings->get_econt_shipping();
			case 'address':
				return (float) $this->settings->get_address_shipping();
			default:
				return 0.0;
		}
	}

	/**
	 * Build cache key for shipping price.
	 *
	 * @param string $carrier       Carrier name.
	 * @param string $delivery_type Delivery type (office/address).
	 * @param int    $city_id       City ID.
	 * @param int    $office_id     Office ID.
	 * @param float  $weight        Package weight.
	 * @return string Cache key.
	 */
	private function build_price_cache_key( $carrier, $delivery_type, $city_id, $office_id, $weight ) {
		return sprintf(
			'shipping_quote_%s_%s_%d_%d_%.2f',
			$carrier,
			$delivery_type,
			$city_id,
			$office_id,
			$weight
		);
	}

	/**
	 * Calculate shipping price via API.
	 *
	 * @param string $carrier       Carrier name.
	 * @param string $delivery_type Delivery type.
	 * @param int    $city_id       City ID.
	 * @param int    $office_id     Office ID.
	 * @param float  $weight        Package weight.
	 * @return array|WP_Error Price data or error.
	 */
	private function calculate_shipping_price( $carrier, $delivery_type, $city_id, $office_id, $weight ) {
		$plugin     = SESH_Plugin::instance();
		$api_client = null;

		if ( 'speedy' === $carrier ) {
			$api_client = $plugin->get_speedy_api();
		} elseif ( 'econt' === $carrier ) {
			$api_client = $plugin->get_econt_api();
		}

		if ( ! $api_client ) {
			return new WP_Error( 'no_api', __( 'API not available', 'speedy_econt_shipping' ) );
		}

		try {
			if ( method_exists( $api_client, 'calculate_shipping' ) ) {
				$params = array(
					'to_city_id'    => $city_id,
					'to_office_id'  => $office_id,
					'weight'        => $weight,
					'delivery_type' => $delivery_type,
				);

				$quote = $api_client->calculate_shipping( $params );

				if ( is_wp_error( $quote ) ) {
					return $quote;
				}

				// Extract price from quote.
				$price = 0;
				if ( is_object( $quote ) && isset( $quote->price ) ) {
					$price = (float) $quote->price;
				} elseif ( is_array( $quote ) && isset( $quote['price'] ) ) {
					$price = (float) $quote['price'];
				}

				return array(
					'price'         => $price,
					'delivery_time' => $this->get_delivery_time_estimate( $carrier ),
					'breakdown'     => array(
						'base_price'  => $price,
						'discount'    => 0,
						'final_price' => $price,
					),
				);
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage() );
		}

		return new WP_Error( 'no_method', __( 'Shipping calculation not available', 'speedy_econt_shipping' ) );
	}

	/**
	 * Get regions for a carrier.
	 *
	 * @param string $carrier Carrier (speedy or econt).
	 * @return array
	 */
	public function get_regions( $carrier ) {
		return $this->database->get_regions( $carrier );
	}

	/**
	 * Render checkout fields for carrier selection.
	 *
	 * @param WC_Checkout $checkout WooCommerce checkout object.
	 */
	public function render_checkout_fields( $checkout ) {
		if ( ! is_checkout() ) {
			return;
		}

		echo '<div id="shipping-to-row" class="sesh-shipping-fields" style="display:none;">';

		// Hidden fields to store selected delivery data for form submission.
		// These fields are populated by JavaScript and submitted with the checkout form.
		?>
		<input type="hidden" name="sesh_carrier" id="sesh_carrier" value="" />
		<input type="hidden" name="sesh_delivery_type" id="sesh_delivery_type" value="" />
		<input type="hidden" name="sesh_city_id" id="sesh_city_id" value="" />
		<input type="hidden" name="sesh_city_name" id="sesh_city_name" value="" />
		<input type="hidden" name="sesh_office_id" id="sesh_office_id" value="" />
		<input type="hidden" name="sesh_office_name" id="sesh_office_name" value="" />
		<input type="hidden" name="sesh_office_address" id="sesh_office_address" value="" />
		<input type="hidden" name="sesh_shipping_price" id="sesh_shipping_price" value="" />
		<?php

		// Render Speedy fields.
		if ( $this->settings->is_speedy_enabled() ) {
			$this->render_carrier_fields( 'speedy', __( 'Speedy Delivery', 'speedy_econt_shipping' ) );
		}

		// Render Econt fields.
		if ( $this->settings->is_econt_enabled() ) {
			$this->render_carrier_fields( 'econt', __( 'Econt Delivery', 'speedy_econt_shipping' ) );
		}

		// Address fields (use standard WooCommerce billing fields).
		if ( $this->settings->is_address_enabled() ) {
			$this->render_address_fields();
		}

		echo '</div>';
	}

	/**
	 * Render carrier-specific fields (city autocomplete, office).
	 *
	 * @param string $carrier Carrier slug (speedy or econt).
	 * @param string $label   Carrier display label.
	 */
	private function render_carrier_fields( $carrier, $label ) {
		$carrier_slug = sanitize_key( $carrier );
		?>
		<div id="<?php echo esc_attr( $carrier_slug ); ?>_fields" class="sesh-carrier-fields" style="display:none;">
			<h3><?php echo esc_html( $label ); ?></h3>

			<?php // Hidden field to store selected city ID. ?>
			<input type="hidden" name="<?php echo esc_attr( $carrier_slug ); ?>_city_id" id="<?php echo esc_attr( $carrier_slug ); ?>_city_id" value="" />

			<p class="form-row form-row-wide sesh-location-selector" id="<?php echo esc_attr( $carrier_slug ); ?>_city_field">
				<label for="<?php echo esc_attr( $carrier_slug ); ?>_city">
					<?php esc_html_e( 'City', 'speedy_econt_shipping' ); ?>
					<abbr class="required" title="required">*</abbr>
				</label>
				<select name="<?php echo esc_attr( $carrier_slug ); ?>_city" id="<?php echo esc_attr( $carrier_slug ); ?>_city" class="sesh-city-autocomplete" data-carrier="<?php echo esc_attr( $carrier_slug ); ?>">
					<option value=""><?php esc_html_e( 'Type city name...', 'speedy_econt_shipping' ); ?></option>
				</select>
			</p>

			<p class="form-row form-row-wide sesh-location-selector" id="<?php echo esc_attr( $carrier_slug ); ?>_office_field" style="display:none;">
				<label for="<?php echo esc_attr( $carrier_slug ); ?>_office">
					<?php esc_html_e( 'Office', 'speedy_econt_shipping' ); ?>
					<abbr class="required" title="required">*</abbr>
				</label>
				<select name="<?php echo esc_attr( $carrier_slug ); ?>_office" id="<?php echo esc_attr( $carrier_slug ); ?>_office" class="sesh-office-select" data-carrier="<?php echo esc_attr( $carrier_slug ); ?>">
					<option value=""><?php esc_html_e( 'Select office', 'speedy_econt_shipping' ); ?></option>
				</select>
				<span class="sesh-office-loading" style="display:none;">
					<span class="sesh-spinner sesh-spinner--sm"></span>
					<?php esc_html_e( 'Loading offices...', 'speedy_econt_shipping' ); ?>
				</span>
			</p>

			<?php // Office preview card (populated via JS). ?>
			<div id="<?php echo esc_attr( $carrier_slug ); ?>_office_preview" class="sesh-office-preview" style="display:none;"></div>

			<?php // Price display area (populated via JS). ?>
			<div id="<?php echo esc_attr( $carrier_slug ); ?>_price_display" class="sesh-price-breakdown" style="display:none;"></div>
		</div>
		<?php
	}

	/**
	 * Render address delivery fields placeholder.
	 *
	 * Address delivery uses standard WooCommerce billing fields.
	 */
	private function render_address_fields() {
		?>
		<div id="address_fields" class="sesh-carrier-fields" style="display:none;">
			<h3><?php echo esc_html( $this->settings->get_address_label() ); ?></h3>
			<p class="sesh-address-notice">
				<?php esc_html_e( 'Please fill in your delivery address in the billing fields above.', 'speedy_econt_shipping' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Get settings instance.
	 *
	 * @return SESH_Settings
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Validate checkout fields before order is created.
	 *
	 * This runs when customer clicks "Place Order". If validation fails,
	 * wc_add_notice() stops the checkout and displays the error message.
	 *
	 * @since 3.0.0
	 */
	public function validate_checkout_fields() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce verification.
		$carrier = isset( $_POST['sesh_carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['sesh_carrier'] ) ) : '';

		// If no carrier selected, WooCommerce's own validation will catch it.
		if ( empty( $carrier ) ) {
			return;
		}

		// For Speedy or Econt office delivery, validate city and office selection.
		if ( in_array( $carrier, array( 'speedy', 'econt' ), true ) ) {
			$city_id       = isset( $_POST['sesh_city_id'] ) ? absint( $_POST['sesh_city_id'] ) : 0;
			$office_id     = isset( $_POST['sesh_office_id'] ) ? absint( $_POST['sesh_office_id'] ) : 0;
			$delivery_type = isset( $_POST['sesh_delivery_type'] ) ? sanitize_text_field( wp_unslash( $_POST['sesh_delivery_type'] ) ) : '';

			// City is always required for Speedy/Econt.
			if ( empty( $city_id ) ) {
				wc_add_notice(
					__( 'Please select a city for delivery.', 'speedy_econt_shipping' ),
					'error'
				);
			}

			// Office required only for office delivery type.
			if ( 'office' === $delivery_type && empty( $office_id ) ) {
				wc_add_notice(
					__( 'Please select a pickup office.', 'speedy_econt_shipping' ),
					'error'
				);
			}
		}

		// Phone is required for all shipping methods (carriers need it for delivery).
		$phone = isset( $_POST['billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ) : '';
		if ( empty( $phone ) ) {
			wc_add_notice(
				__( 'Phone number is required for delivery.', 'speedy_econt_shipping' ),
				'error'
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Save shipping delivery details to order meta.
	 *
	 * This runs during order creation, after validation passes.
	 * The data saved here is used later by the label generator.
	 *
	 * @since 3.0.0
	 *
	 * @param WC_Order $order The order being created.
	 * @param array    $data  The checkout form data.
	 */
	public function save_checkout_shipping_meta( $order, $data ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce verification.
		$carrier        = isset( $_POST['sesh_carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['sesh_carrier'] ) ) : '';
		$delivery_type  = isset( $_POST['sesh_delivery_type'] ) ? sanitize_text_field( wp_unslash( $_POST['sesh_delivery_type'] ) ) : '';
		$city_id        = isset( $_POST['sesh_city_id'] ) ? absint( $_POST['sesh_city_id'] ) : 0;
		$city_name      = isset( $_POST['sesh_city_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sesh_city_name'] ) ) : '';
		$office_id      = isset( $_POST['sesh_office_id'] ) ? absint( $_POST['sesh_office_id'] ) : 0;
		$office_name    = isset( $_POST['sesh_office_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sesh_office_name'] ) ) : '';
		$office_address = isset( $_POST['sesh_office_address'] ) ? sanitize_text_field( wp_unslash( $_POST['sesh_office_address'] ) ) : '';
		$shipping_price = isset( $_POST['sesh_shipping_price'] ) ? floatval( $_POST['sesh_shipping_price'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Only save if we have a carrier selected.
		if ( empty( $carrier ) ) {
			return;
		}

		// Save to order meta (these fields are read by label generator).
		$order->update_meta_data( '_sesh_carrier', $carrier );
		$order->update_meta_data( '_sesh_delivery_type', $delivery_type );

		if ( $city_id > 0 ) {
			$order->update_meta_data( '_sesh_city_id', $city_id );
			$order->update_meta_data( '_sesh_city_name', $city_name );
		}

		// Only save office data for office delivery.
		if ( 'office' === $delivery_type && $office_id > 0 ) {
			$order->update_meta_data( '_sesh_office_id', $office_id );
			$order->update_meta_data( '_sesh_office_name', $office_name );
			$order->update_meta_data( '_sesh_office_address', $office_address );
		}

		// Save the calculated price for reference.
		if ( $shipping_price > 0 ) {
			$order->update_meta_data( '_sesh_calculated_shipping_price', $shipping_price );
		}

		// Add order note for admin reference.
		$delivery_label = 'office' === $delivery_type
			? __( 'Office pickup', 'speedy_econt_shipping' )
			: __( 'Address delivery', 'speedy_econt_shipping' );

		$destination = 'office' === $delivery_type ? $office_name : $city_name;

		$note = sprintf(
			/* translators: 1: Carrier name, 2: Delivery type label, 3: Destination */
			__( 'Shipping: %1$s (%2$s) to %3$s', 'speedy_econt_shipping' ),
			ucfirst( $carrier ),
			$delivery_label,
			$destination
		);
		$order->add_order_note( $note );
	}
}
