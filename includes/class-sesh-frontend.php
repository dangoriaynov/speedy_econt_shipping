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
 * Enqueues modern checkout UI styles (sesh-checkout.css) that provide:
 * - Visual carrier selection cards with branding
 * - Styled Select2 dropdowns for region/city/office selection
 * - Clear shipping cost display with free shipping badges
 * - Loading and error states for better UX
 * - Mobile responsive design (mobile-first approach)
 * - WCAG 2.1 AA accessibility compliance
 * - Theme-independent styling with CSS custom properties
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
		// Enqueue frontend scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// AJAX handlers for office selection (will be expanded in Phase 3).
		add_action( 'wp_ajax_sesh_get_offices', array( $this, 'ajax_get_offices' ) );
		add_action( 'wp_ajax_nopriv_sesh_get_offices', array( $this, 'ajax_get_offices' ) );

		add_action( 'wp_ajax_sesh_get_cities', array( $this, 'ajax_get_cities' ) );
		add_action( 'wp_ajax_nopriv_sesh_get_cities', array( $this, 'ajax_get_cities' ) );
	}

	/**
	 * Enqueue frontend scripts and styles.
	 */
	public function enqueue_scripts() {
		// Only load on checkout page.
		if ( ! is_checkout() ) {
			return;
		}

		// Modern checkout UI styles.
		wp_enqueue_style(
			'sesh-checkout',
			SESH_PLUGIN_URL . 'assets/css/sesh-checkout.css',
			array(),
			SESH_VERSION
		);

		// Legacy frontend styles (for backward compatibility).
		wp_enqueue_style(
			'sesh-frontend',
			SESH_PLUGIN_URL . 'assets/css/frontend.css',
			array( 'sesh-checkout' ),
			SESH_VERSION
		);

		// Frontend scripts (will be added in future phases).
		wp_enqueue_script(
			'sesh-frontend',
			SESH_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery', 'wc-checkout' ),
			SESH_VERSION,
			true
		);

		// Localize script with data.
		wp_localize_script(
			'sesh-frontend',
			'sesh_params',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'sesh_frontend_nonce' ),
				'speedy_enabled' => $this->settings->is_speedy_enabled(),
				'econt_enabled'  => $this->settings->is_econt_enabled(),
				'i18n'           => array(
					'select_region' => __( 'Select region', 'speedy_econt_shipping' ),
					'select_city'   => __( 'Select city', 'speedy_econt_shipping' ),
					'select_office' => __( 'Select office', 'speedy_econt_shipping' ),
					'loading'       => __( 'Loading...', 'speedy_econt_shipping' ),
					'error'         => __( 'Error loading data', 'speedy_econt_shipping' ),
				),
			)
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
