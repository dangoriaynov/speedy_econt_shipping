<?php
/**
 * Autocomplete AJAX Handler.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Autocomplete handler class.
 *
 * Handles AJAX requests for city, office, and address autocomplete functionality.
 */
class SESH_Autocomplete_Handler {

	/**
	 * Database instance.
	 *
	 * @var SESH_Database
	 */
	private $database;

	/**
	 * Settings instance.
	 *
	 * @var SESH_Settings
	 */
	private $settings;

	/**
	 * Speedy API instance.
	 *
	 * @var SESH_Speedy_API|null
	 */
	private $speedy_api = null;

	/**
	 * Econt API instance.
	 *
	 * @var SESH_Econt_API|null
	 */
	private $econt_api = null;

	/**
	 * Constructor.
	 *
	 * @param SESH_Database $database Database instance.
	 * @param SESH_Settings $settings Settings instance.
	 */
	public function __construct( SESH_Database $database, SESH_Settings $settings ) {
		$this->database = $database;
		$this->settings = $settings;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// City autocomplete (DB lookup).
		add_action( 'wp_ajax_sesh_autocomplete_cities', array( $this, 'autocomplete_cities' ) );
		add_action( 'wp_ajax_nopriv_sesh_autocomplete_cities', array( $this, 'autocomplete_cities' ) );

		// Office autocomplete (DB lookup).
		add_action( 'wp_ajax_sesh_autocomplete_offices', array( $this, 'autocomplete_offices' ) );
		add_action( 'wp_ajax_nopriv_sesh_autocomplete_offices', array( $this, 'autocomplete_offices' ) );

		// Street autocomplete (Speedy API).
		add_action( 'wp_ajax_sesh_autocomplete_streets', array( $this, 'autocomplete_streets' ) );
		add_action( 'wp_ajax_nopriv_sesh_autocomplete_streets', array( $this, 'autocomplete_streets' ) );

		// Combined city + office autocomplete.
		add_action( 'wp_ajax_sesh_autocomplete_locations', array( $this, 'autocomplete_locations' ) );
		add_action( 'wp_ajax_nopriv_sesh_autocomplete_locations', array( $this, 'autocomplete_locations' ) );
	}

	/**
	 * Get or create Speedy API instance.
	 *
	 * @return SESH_Speedy_API|null
	 */
	private function get_speedy_api() {
		if ( null !== $this->speedy_api ) {
			return $this->speedy_api;
		}

		if ( ! $this->settings->is_speedy_enabled() ) {
			return null;
		}

		$username = $this->settings->get_speedy_username();
		$password = $this->settings->get_speedy_password();

		if ( empty( $username ) || empty( $password ) ) {
			return null;
		}

		require_once SESH_PLUGIN_DIR . 'includes/api/class-sesh-api-exception.php';
		require_once SESH_PLUGIN_DIR . 'includes/api/class-sesh-speedy-api.php';

		$this->speedy_api = new SESH_Speedy_API( $username, $password, $this->database );

		return $this->speedy_api;
	}

	/**
	 * Autocomplete cities endpoint.
	 *
	 * Searches cities in local database based on search term.
	 */
	public function autocomplete_cities() {
		check_ajax_referer( 'sesh_frontend_nonce', 'nonce' );

		$carrier = isset( $_GET['carrier'] ) ? sanitize_text_field( wp_unslash( $_GET['carrier'] ) ) : '';
		$term    = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		// Validate carrier.
		if ( ! in_array( $carrier, array( 'speedy', 'econt' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid carrier', 'speedy_econt_shipping' ) ) );
		}

		// Require minimum search length.
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		// Search sites (cities).
		$sites = $this->database->search_sites( $carrier, $term, 20 );

		$results = array();
		foreach ( $sites as $site ) {
			$results[] = array(
				'id'           => (int) $site->id,
				'text'         => $site->name,
				'region'       => isset( $site->region ) ? $site->region : '',
				'municipality' => isset( $site->municipality ) ? $site->municipality : '',
				'post_code'    => isset( $site->post_code ) ? $site->post_code : '',
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * Autocomplete offices endpoint.
	 *
	 * Searches offices in local database based on search term.
	 */
	public function autocomplete_offices() {
		check_ajax_referer( 'sesh_frontend_nonce', 'nonce' );

		$carrier = isset( $_GET['carrier'] ) ? sanitize_text_field( wp_unslash( $_GET['carrier'] ) ) : '';
		$term    = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$city    = isset( $_GET['city'] ) ? sanitize_text_field( wp_unslash( $_GET['city'] ) ) : '';

		// Validate carrier.
		if ( ! in_array( $carrier, array( 'speedy', 'econt' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid carrier', 'speedy_econt_shipping' ) ) );
		}

		// Require minimum search length.
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		// If city is provided, filter by city.
		if ( ! empty( $city ) ) {
			$offices = $this->database->get_offices_by_city( $carrier, $city );

			// Filter by search term.
			$term_lower = mb_strtolower( $term, 'UTF-8' );
			$offices    = array_filter(
				$offices,
				function ( $office ) use ( $term_lower ) {
					$name_lower    = mb_strtolower( $office->name, 'UTF-8' );
					$address_lower = mb_strtolower( $office->address, 'UTF-8' );
					return false !== mb_strpos( $name_lower, $term_lower, 0, 'UTF-8' )
						|| false !== mb_strpos( $address_lower, $term_lower, 0, 'UTF-8' );
				}
			);
		} else {
			// Search all offices.
			$offices = $this->database->search_offices( $carrier, $term, 20 );
		}

		$results = array();
		foreach ( $offices as $office ) {
			$label = $office->name;

			// Add office number for Speedy.
			if ( 'speedy' === $carrier ) {
				$label = sprintf( '№%d, %s', $office->id, $label );
			}

			// Add address.
			$label .= ' (' . $office->address . ')';

			$results[] = array(
				'id'      => (int) $office->id,
				'text'    => $label,
				'name'    => $office->name,
				'address' => $office->address,
				'city'    => isset( $office->city ) ? $office->city : '',
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * Autocomplete streets endpoint.
	 *
	 * Queries Speedy API for street suggestions.
	 * Fails silently if API is unavailable.
	 */
	public function autocomplete_streets() {
		check_ajax_referer( 'sesh_frontend_nonce', 'nonce' );

		$term    = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$site_id = isset( $_GET['site_id'] ) ? absint( $_GET['site_id'] ) : 0;

		// Require minimum search length.
		if ( strlen( $term ) < 3 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		// Require site ID.
		if ( $site_id <= 0 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		$api = $this->get_speedy_api();
		if ( null === $api ) {
			// API not configured - fail silently.
			wp_send_json_success( array( 'results' => array() ) );
		}

		try {
			$streets = $api->get_streets( $site_id, $term );

			$results = array();
			foreach ( $streets as $street ) {
				$results[] = array(
					'id'   => isset( $street['id'] ) ? (int) $street['id'] : 0,
					'text' => isset( $street['name'] ) ? $street['name'] : '',
					'type' => isset( $street['type'] ) ? $street['type'] : '',
				);
			}

			wp_send_json_success( array( 'results' => $results ) );

		} catch ( Exception $e ) {
			// Fail silently - return empty results.
			$this->log_error( 'Street autocomplete failed: ' . $e->getMessage() );
			wp_send_json_success( array( 'results' => array() ) );
		}
	}

	/**
	 * Autocomplete locations endpoint (cities + offices combined).
	 *
	 * Returns both cities and offices matching the search term.
	 * Used for single-field location search.
	 */
	public function autocomplete_locations() {
		check_ajax_referer( 'sesh_frontend_nonce', 'nonce' );

		$carrier = isset( $_GET['carrier'] ) ? sanitize_text_field( wp_unslash( $_GET['carrier'] ) ) : '';
		$term    = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		// Validate carrier.
		if ( ! in_array( $carrier, array( 'speedy', 'econt' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid carrier', 'speedy_econt_shipping' ) ) );
		}

		// Require minimum search length.
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		$results = array();

		// Search cities.
		$sites = $this->database->search_sites( $carrier, $term, 10 );
		foreach ( $sites as $site ) {
			$results[] = array(
				'id'       => 'city_' . $site->id,
				'text'     => $site->name,
				'type'     => 'city',
				'city_id'  => (int) $site->id,
				'region'   => isset( $site->region ) ? $site->region : '',
			);
		}

		// Search offices.
		$offices = $this->database->search_offices( $carrier, $term, 10 );
		foreach ( $offices as $office ) {
			$label = $office->name;

			if ( 'speedy' === $carrier ) {
				$label = sprintf( '№%d, %s', $office->id, $label );
			}

			$label .= ' (' . $office->address . ')';

			$results[] = array(
				'id'        => 'office_' . $office->id,
				'text'      => $label,
				'type'      => 'office',
				'office_id' => (int) $office->id,
				'city'      => isset( $office->city ) ? $office->city : '',
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * Log error message.
	 *
	 * @param string $message Error message.
	 */
	private function log_error( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->error( $message, array( 'source' => 'sesh-autocomplete' ) );
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[SESH Autocomplete] ' . $message );
		}
	}
}
