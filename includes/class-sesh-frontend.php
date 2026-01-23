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
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Enqueue frontend scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// AJAX handlers for office selection (will be expanded in Phase 3).
		add_action( 'wp_ajax_sesh_get_offices', array( $this, 'ajax_get_offices' ) );
		add_action( 'wp_ajax_nopriv_sesh_get_offices', array( $this, 'ajax_get_offices' ) );

		add_action( 'wp_ajax_sesh_get_cities', array( $this, 'ajax_get_cities' ) );
		add_action( 'wp_ajax_nopriv_sesh_get_cities', array( $this, 'ajax_get_cities' ) );
	}

	/**
	 * Initialize cart calculator.
	 */
	private function init_cart_calculator() {
		require_once SESH_PLUGIN_DIR . 'includes/class-sesh-cart-calculator.php';
		$this->cart_calculator = new SESH_Cart_Calculator( $this->settings, $this->database );
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
		// Get delivery options from legacy function.
		$delivery_options = function_exists( 'seshDelivOptions' ) ? seshDelivOptions() : array();

		// Get field selectors from legacy globals.
		global $speedy_region_sel, $speedy_city_sel, $speedy_office_sel;
		global $econt_region_sel, $econt_city_sel, $econt_office_sel;
		global $speedy_region_field, $speedy_city_field, $speedy_office_field;
		global $econt_region_field, $econt_city_field, $econt_office_field;
		global $address_region_sel, $address_city_sel, $address_address_sel;
		global $address_region_field, $address_city_field, $address_address_field;
		global $shipping_to_field, $shipping_to_id;

		return array(
			'ajax_url'                => admin_url( 'admin-ajax.php' ),
			'nonce'                   => wp_create_nonce( 'sesh_frontend_nonce' ),
			'delivery_options'        => $delivery_options,
			'default_shipping_method' => function_exists( 'seshDefaultDelivOpt' ) ? seshDefaultDelivOpt() : '',
			'currency_symbol'         => html_entity_decode( get_woocommerce_currency_symbol() ),
			'shop_url'                => get_permalink( wc_get_page_id( 'shop' ) ),
			'calculate_final_price'   => function_exists( 'isCalculateFinalPrice' ) ? isCalculateFinalPrice() : false,
			'delivery_price_selector' => function_exists( 'getDeliveryPriceSelector' ) ? getDeliveryPriceSelector() : '.cart-subtotal .woocommerce-Price-amount.amount',
			'free_shipping_suffix'    => function_exists( 'getFreeShippingLabelSuffix' ) ? getFreeShippingLabelSuffix() : '',
			'show_store_messages'     => function_exists( 'showStoreMessages' ) ? explode( ',', showStoreMessages() ) : array(),
			'shipping_to_id'          => $shipping_to_id,
			'selectors'               => array(
				'speedy_region_sel'    => $speedy_region_sel,
				'speedy_city_sel'      => $speedy_city_sel,
				'speedy_office_sel'    => $speedy_office_sel,
				'speedy_region_field'  => $speedy_region_field,
				'speedy_city_field'    => $speedy_city_field,
				'speedy_office_field'  => $speedy_office_field,
				'econt_region_sel'     => $econt_region_sel,
				'econt_city_sel'       => $econt_city_sel,
				'econt_office_sel'     => $econt_office_sel,
				'econt_region_field'   => $econt_region_field,
				'econt_city_field'     => $econt_city_field,
				'econt_office_field'   => $econt_office_field,
				'address_region_sel'   => $address_region_sel,
				'address_city_sel'     => $address_city_sel,
				'address_office_sel'   => $address_address_sel,
				'address_region_field' => $address_region_field,
				'address_city_field'   => $address_city_field,
				'address_office_field' => $address_address_field,
				'shipping_to_field'    => $shipping_to_field,
			),
			'i18n'                    => array(
				'select_region'          => __( 'Select region', 'speedy_econt_shipping' ),
				'select_city'            => __( 'Select city', 'speedy_econt_shipping' ),
				'select_office'          => __( 'Select office', 'speedy_econt_shipping' ),
				'loading'                => __( 'Loading...', 'speedy_econt_shipping' ),
				'error'                  => __( 'Error loading data', 'speedy_econt_shipping' ),
				'delivery'               => __( 'delivery', 'speedy_econt_shipping' ),
				'free'                   => __( 'for free', 'speedy_econt_shipping' ),
				'congrats_free_delivery' => __( 'Congrats, you won free delivery using %s!', 'speedy_econt_shipping' ),
				'left_till_free'         => __( 'Still left %s', 'speedy_econt_shipping' ),
				'to_shop'                => __( 'To shop', 'speedy_econt_shipping' ),
				'no_free_shipping'       => __( 'Sorry, there is no free shipping available for the option chosen: %s', 'speedy_econt_shipping' ),
			),
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
	 * Get regions for a carrier.
	 *
	 * @param string $carrier Carrier (speedy or econt).
	 * @return array
	 */
	public function get_regions( $carrier ) {
		return $this->database->get_regions( $carrier );
	}

	/**
	 * Get settings instance.
	 *
	 * @return SESH_Settings
	 */
	public function get_settings() {
		return $this->settings;
	}
}
