<?php
/**
 * Speedy API Client.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Speedy API client class.
 *
 * Handles all communication with the Speedy REST API.
 *
 * @see https://api.speedy.bg/web-api.html
 */
class SESH_Speedy_API implements SESH_API_Client_Interface {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	const API_BASE_URL = 'https://api.speedy.bg/v1';

	/**
	 * Default timeout in seconds.
	 *
	 * @var int
	 */
	const TIMEOUT = 30;

	/**
	 * Maximum retry attempts.
	 *
	 * @var int
	 */
	const MAX_RETRIES = 3;

	/**
	 * Bulgaria country ID in Speedy system.
	 *
	 * @var int
	 */
	const BULGARIA_COUNTRY_ID = 100;

	/**
	 * Cache TTL constants (in seconds).
	 */
	const CACHE_TTL_SITES    = 86400; // 24 hours.
	const CACHE_TTL_OFFICES  = 86400; // 24 hours.
	const CACHE_TTL_SERVICES = 3600;  // 1 hour.
	const CACHE_TTL_PRICES   = 300;   // 5 minutes.

	/**
	 * API username.
	 *
	 * @var string
	 */
	private $username;

	/**
	 * API password.
	 *
	 * @var string
	 */
	private $password;

	/**
	 * Debug mode.
	 *
	 * @var bool
	 */
	private $debug = false;

	/**
	 * Database instance for caching.
	 *
	 * @var SESH_Database|null
	 */
	private $db = null;

	/**
	 * Whether caching is enabled.
	 *
	 * @var bool
	 */
	private $cache_enabled = true;

	/**
	 * Constructor.
	 *
	 * @param string             $username API username.
	 * @param string             $password API password.
	 * @param SESH_Database|null $database Optional database instance for caching.
	 */
	public function __construct( $username, $password, $database = null ) {
		$this->username = $username;
		$this->password = $password;
		$this->debug    = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$this->db       = $database;
	}

	/**
	 * Set cache enabled/disabled.
	 *
	 * @param bool $enabled Whether caching should be enabled.
	 */
	public function set_cache_enabled( $enabled ) {
		$this->cache_enabled = (bool) $enabled;
	}

	/**
	 * Set database instance for caching.
	 *
	 * @param SESH_Database $database Database instance.
	 */
	public function set_database( SESH_Database $database ) {
		$this->db = $database;
	}

	/**
	 * Get carrier ID.
	 *
	 * @return string
	 */
	public function get_carrier_id() {
		return 'speedy';
	}

	/**
	 * Get carrier name.
	 *
	 * @return string
	 */
	public function get_carrier_name() {
		return __( 'Speedy', 'speedy_econt_shipping' );
	}

	/**
	 * Make an API request with retry logic.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $params   Request parameters.
	 * @return array Response data.
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	private function request( $endpoint, $params = array() ) {
		// Add authentication.
		$params['userName'] = $this->username;
		$params['password'] = $this->password;
		$params['language'] = 'BG';

		$url           = self::API_BASE_URL . '/' . ltrim( $endpoint, '/' );
		$last_error    = null;
		$attempt       = 0;

		while ( $attempt < self::MAX_RETRIES ) {
			$attempt++;

			$response = wp_remote_post(
				$url,
				array(
					'headers' => array(
						'Content-Type' => 'application/json; charset=utf-8',
					),
					'body'    => wp_json_encode( $params ),
					'timeout' => self::TIMEOUT,
				)
			);

			// Handle WordPress HTTP errors (network issues, timeouts).
			if ( is_wp_error( $response ) ) {
				$last_error = $response;
				$this->log( "API Error (attempt {$attempt}): " . $response->get_error_message(), 'error' );

				// Don't retry on certain errors.
				if ( $this->is_non_retryable_error( $response ) ) {
					break;
				}

				// Exponential backoff: 1s, 2s, 4s.
				if ( $attempt < self::MAX_RETRIES ) {
					$this->sleep_with_backoff( $attempt );
				}
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( $this->debug ) {
				// Mask password in logs.
				$log_params             = $params;
				$log_params['password'] = '***';
				$this->log( "Request to {$endpoint} (attempt {$attempt}): " . wp_json_encode( $log_params ) );
				$this->log( "Response ({$code}): " . substr( $body, 0, 1000 ) );
			}

			// Handle HTTP errors.
			if ( $code >= 500 ) {
				// Server errors are retryable.
				$last_error = new WP_Error(
					'speedy_server_error',
					__( 'Speedy server error', 'speedy_econt_shipping' ),
					array( 'status' => $code )
				);

				if ( $attempt < self::MAX_RETRIES ) {
					$this->sleep_with_backoff( $attempt );
				}
				continue;
			}

			if ( $code !== 200 ) {
				$error_message = isset( $data['error']['message'] )
					? $data['error']['message']
					: __( 'Unknown API error', 'speedy_econt_shipping' );

				throw new SESH_Speedy_API_Exception(
					$error_message,
					isset( $data['error']['code'] ) ? $data['error']['code'] : 'HTTP_' . $code,
					$data['error'] ?? array()
				);
			}

			// Check for API-level errors in response body.
			if ( isset( $data['error'] ) ) {
				throw SESH_Speedy_API_Exception::from_response( $data );
			}

			// Success!
			return $data;
		}

		// All retries exhausted.
		if ( $last_error instanceof WP_Error ) {
			throw SESH_Speedy_API_Exception::from_wp_error( $last_error );
		}

		throw new SESH_Speedy_API_Exception(
			__( 'API request failed after multiple attempts', 'speedy_econt_shipping' ),
			'MAX_RETRIES_EXCEEDED'
		);
	}

	/**
	 * Check if error is non-retryable.
	 *
	 * @param WP_Error $error WordPress error.
	 * @return bool
	 */
	private function is_non_retryable_error( $error ) {
		$non_retryable_codes = array(
			'http_request_failed', // SSL issues, DNS failures.
		);

		// Timeout errors are retryable.
		$message = $error->get_error_message();
		if ( stripos( $message, 'timeout' ) !== false ) {
			return false;
		}

		return in_array( $error->get_error_code(), $non_retryable_codes, true );
	}

	/**
	 * Sleep with exponential backoff.
	 *
	 * @param int $attempt Current attempt number (1-based).
	 */
	private function sleep_with_backoff( $attempt ) {
		$seconds = pow( 2, $attempt - 1 ); // 1, 2, 4 seconds.
		sleep( $seconds );
	}

	/**
	 * Get cached value.
	 *
	 * @param string $cache_key Cache key.
	 * @return mixed|false Cached value or false if not found.
	 */
	private function get_cache( $cache_key ) {
		if ( ! $this->cache_enabled || ! $this->db ) {
			return false;
		}

		return $this->db->get_cache( $cache_key );
	}

	/**
	 * Set cached value.
	 *
	 * @param string $cache_key    Cache key.
	 * @param mixed  $value        Value to cache.
	 * @param string $request_type Request type for categorization.
	 * @param int    $ttl          Time to live in seconds.
	 * @return bool
	 */
	private function set_cache( $cache_key, $value, $request_type, $ttl ) {
		if ( ! $this->cache_enabled || ! $this->db ) {
			return false;
		}

		return $this->db->set_cache( $cache_key, $value, 'speedy', $request_type, $ttl );
	}

	/**
	 * Generate cache key.
	 *
	 * @param string $method Method name.
	 * @param array  $params Parameters.
	 * @return string
	 */
	private function cache_key( $method, $params = array() ) {
		// Remove auth from cache key.
		unset( $params['userName'], $params['password'], $params['language'] );

		$hash = md5( wp_json_encode( $params ) );
		return "speedy_{$method}_{$hash}";
	}

	/**
	 * Get countries.
	 *
	 * @return array
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function get_countries() {
		$cache_key = $this->cache_key( 'countries' );
		$cached    = $this->get_cache( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response  = $this->request( 'location/country/' );
		$countries = isset( $response['countries'] ) ? $response['countries'] : array();

		$this->set_cache( $cache_key, $countries, 'countries', self::CACHE_TTL_SITES );

		return $countries;
	}

	/**
	 * Get sites/cities.
	 *
	 * @param array $args Optional arguments (name, country_id).
	 * @return array
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function get_sites( $args = array() ) {
		$params = array(
			'countryId' => isset( $args['country_id'] ) ? (int) $args['country_id'] : self::BULGARIA_COUNTRY_ID,
		);

		if ( ! empty( $args['name'] ) ) {
			$params['name'] = sanitize_text_field( $args['name'] );
		}

		// Only cache full site lists (no name filter).
		$use_cache = empty( $args['name'] );
		$cache_key = $this->cache_key( 'sites', $params );

		if ( $use_cache ) {
			$cached = $this->get_cache( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$response = $this->request( 'location/site/', $params );
		$sites    = isset( $response['sites'] ) ? $response['sites'] : array();

		if ( $use_cache ) {
			$this->set_cache( $cache_key, $sites, 'sites', self::CACHE_TTL_SITES );
		}

		return $sites;
	}

	/**
	 * Get site by ID.
	 *
	 * @param int $site_id Site ID.
	 * @return array|null
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function get_site( $site_id ) {
		$params = array(
			'countryId' => self::BULGARIA_COUNTRY_ID,
		);

		$cache_key = $this->cache_key( 'site', array( 'id' => $site_id ) );
		$cached    = $this->get_cache( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->request( 'location/site/' . absint( $site_id ), $params );
		$site     = isset( $response['site'] ) ? $response['site'] : null;

		if ( $site ) {
			$this->set_cache( $cache_key, $site, 'sites', self::CACHE_TTL_SITES );
		}

		return $site;
	}

	/**
	 * Get offices.
	 *
	 * @param array $args Optional arguments (site_id, name, country_id).
	 * @return array
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function get_offices( $args = array() ) {
		$params = array(
			'countryId' => isset( $args['country_id'] ) ? (int) $args['country_id'] : self::BULGARIA_COUNTRY_ID,
		);

		if ( ! empty( $args['site_id'] ) ) {
			$params['siteId'] = (int) $args['site_id'];
		}

		if ( ! empty( $args['name'] ) ) {
			$params['name'] = sanitize_text_field( $args['name'] );
		}

		// Only cache if no name filter.
		$use_cache = empty( $args['name'] );
		$cache_key = $this->cache_key( 'offices', $params );

		if ( $use_cache ) {
			$cached = $this->get_cache( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$response = $this->request( 'location/office/', $params );
		$offices  = isset( $response['offices'] ) ? $response['offices'] : array();

		// Filter out closed offices.
		$offices = array_filter(
			$offices,
			function ( $office ) {
				return ! empty( $office['pickUpAllowed'] ) && ! empty( $office['dropOffAllowed'] );
			}
		);

		// Re-index array.
		$offices = array_values( $offices );

		if ( $use_cache ) {
			$this->set_cache( $cache_key, $offices, 'offices', self::CACHE_TTL_OFFICES );
		}

		return $offices;
	}

	/**
	 * Get streets for a site.
	 *
	 * @param int    $site_id Site ID.
	 * @param string $name    Optional street name filter.
	 * @return array
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function get_streets( $site_id, $name = '' ) {
		$params = array(
			'siteId' => (int) $site_id,
		);

		if ( ! empty( $name ) ) {
			$params['name'] = sanitize_text_field( $name );
		}

		// Streets are not cached due to high variability.
		$response = $this->request( 'location/street/', $params );

		return isset( $response['streets'] ) ? $response['streets'] : array();
	}

	/**
	 * Find nearest offices by coordinates.
	 *
	 * @param float $lat      Latitude.
	 * @param float $lng      Longitude.
	 * @param int   $distance Maximum distance in meters (default 5000).
	 * @return array
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function find_nearest_offices( $lat, $lng, $distance = 5000 ) {
		$params = array(
			'countryId'        => self::BULGARIA_COUNTRY_ID,
			'coordinateX'      => (float) $lng,
			'coordinateY'      => (float) $lat,
			'distanceLimit'    => (int) $distance,
			'officeType'       => 'ALL',
		);

		$response = $this->request( 'location/office/', $params );
		$offices  = isset( $response['offices'] ) ? $response['offices'] : array();

		// Filter active offices.
		return array_values(
			array_filter(
				$offices,
				function ( $office ) {
					return ! empty( $office['pickUpAllowed'] ) || ! empty( $office['dropOffAllowed'] );
				}
			)
		);
	}

	/**
	 * Calculate shipping price.
	 *
	 * @param array $params Calculation parameters.
	 * @return SESH_Shipping_Quote
	 * @throws SESH_Speedy_API_Exception On API error or no calculations.
	 */
	public function calculate_shipping( $params ) {
		// Short cache for prices.
		$cache_key = $this->cache_key( 'calculate', $params );
		$cached    = $this->get_cache( $cache_key );

		if ( false !== $cached ) {
			return new SESH_Shipping_Quote( $cached );
		}

		$response = $this->request( 'calculate/', $params );

		if ( empty( $response['calculations'] ) ) {
			throw new SESH_Speedy_API_Exception(
				__( 'No shipping calculations available', 'speedy_econt_shipping' ),
				'NO_CALCULATIONS'
			);
		}

		$calculation = $response['calculations'][0];

		$quote_data = array(
			'price'             => $calculation['price']['total'] ?? 0,
			'price_with_vat'    => $calculation['price']['total'] ?? 0,
			'currency'          => 'BGN',
			'service_name'      => $calculation['serviceId'] ?? '',
			'delivery_deadline' => $calculation['deliveryDeadline'] ?? null,
			'raw_response'      => $calculation,
		);

		$this->set_cache( $cache_key, $quote_data, 'prices', self::CACHE_TTL_PRICES );

		return new SESH_Shipping_Quote( $quote_data );
	}

	/**
	 * Create a shipment.
	 *
	 * @param array $params Shipment parameters.
	 * @return SESH_Shipment_Response
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function create_shipment( $params ) {
		$response = $this->request( 'shipment/', $params );

		return new SESH_Shipment_Response(
			array(
				'tracking_number' => $response['id'] ?? '',
				'barcode'         => $response['parcels'][0]['id'] ?? '',
				'price'           => $response['price']['total'] ?? 0,
				'pickup_date'     => $response['pickupDate'] ?? null,
				'raw_response'    => $response,
			)
		);
	}

	/**
	 * Cancel a shipment.
	 *
	 * @param string $tracking_number Tracking number.
	 * @return bool
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function cancel_shipment( $tracking_number ) {
		$params = array(
			'shipmentId' => sanitize_text_field( $tracking_number ),
		);

		$this->request( 'shipment/cancel/', $params );

		return true;
	}

	/**
	 * Get shipping label (PDF).
	 *
	 * @param string $tracking_number Tracking number.
	 * @param string $format          Label format (pdf).
	 * @return string PDF binary data.
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function get_label( $tracking_number, $format = 'pdf' ) {
		$params = array(
			'parcels' => array(
				array(
					'parcel' => array(
						'id' => sanitize_text_field( $tracking_number ),
					),
				),
			),
		);

		$response = $this->request( 'print/', $params );

		if ( empty( $response['parcels'][0]['pdf'] ) ) {
			throw new SESH_Speedy_API_Exception(
				__( 'Label not available', 'speedy_econt_shipping' ),
				'NO_LABEL'
			);
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return base64_decode( $response['parcels'][0]['pdf'] );
	}

	/**
	 * Get shipping label with extended info.
	 *
	 * @param string $tracking_number Tracking number.
	 * @return array Label data including PDF and metadata.
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function get_label_extended( $tracking_number ) {
		$params = array(
			'parcels' => array(
				array(
					'parcel' => array(
						'id' => sanitize_text_field( $tracking_number ),
					),
				),
			),
		);

		$response = $this->request( 'print/', $params );

		if ( empty( $response['parcels'][0] ) ) {
			throw new SESH_Speedy_API_Exception(
				__( 'Label not available', 'speedy_econt_shipping' ),
				'NO_LABEL'
			);
		}

		$parcel_data = $response['parcels'][0];

		return array(
			'pdf'         => isset( $parcel_data['pdf'] )
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
				? base64_decode( $parcel_data['pdf'] )
				: null,
			'error'       => $parcel_data['error'] ?? null,
			'raw_response' => $parcel_data,
		);
	}

	/**
	 * Track a shipment.
	 *
	 * @param string $tracking_number Tracking number.
	 * @return array Tracking events.
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function track_shipment( $tracking_number ) {
		$params = array(
			'parcels' => array( sanitize_text_field( $tracking_number ) ),
		);

		$response = $this->request( 'track/', $params );

		return isset( $response['parcels'][0]['operations'] )
			? $response['parcels'][0]['operations']
			: array();
	}

	/**
	 * Track multiple shipments.
	 *
	 * @param array $tracking_numbers Array of tracking numbers.
	 * @return array Tracking info keyed by tracking number.
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function track_bulk( array $tracking_numbers ) {
		$sanitized = array_map( 'sanitize_text_field', $tracking_numbers );

		$params = array(
			'parcels' => $sanitized,
		);

		$response = $this->request( 'track/', $params );

		$results = array();
		if ( isset( $response['parcels'] ) && is_array( $response['parcels'] ) ) {
			foreach ( $response['parcels'] as $parcel ) {
				$id             = $parcel['id'] ?? '';
				$results[ $id ] = $parcel['operations'] ?? array();
			}
		}

		return $results;
	}

	/**
	 * Validate address.
	 *
	 * @param array $address Address data (siteId, streetId, streetNo, etc.).
	 * @return array Validation result with 'valid' boolean and 'suggestions' array.
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function validate_address( array $address ) {
		$params = array(
			'address' => array(
				'countryId' => $address['country_id'] ?? self::BULGARIA_COUNTRY_ID,
				'siteId'    => isset( $address['site_id'] ) ? (int) $address['site_id'] : null,
				'streetId'  => isset( $address['street_id'] ) ? (int) $address['street_id'] : null,
				'streetNo'  => isset( $address['street_no'] ) ? sanitize_text_field( $address['street_no'] ) : null,
				'blockNo'   => isset( $address['block_no'] ) ? sanitize_text_field( $address['block_no'] ) : null,
				'entranceNo' => isset( $address['entrance_no'] ) ? sanitize_text_field( $address['entrance_no'] ) : null,
				'floorNo'   => isset( $address['floor_no'] ) ? sanitize_text_field( $address['floor_no'] ) : null,
				'apartmentNo' => isset( $address['apartment_no'] ) ? sanitize_text_field( $address['apartment_no'] ) : null,
			),
		);

		// Remove null values.
		$params['address'] = array_filter( $params['address'], function( $value ) {
			return $value !== null;
		});

		$response = $this->request( 'location/address/', $params );

		return array(
			'valid'       => isset( $response['address'] ) && ! empty( $response['address'] ),
			'address'     => $response['address'] ?? null,
			'suggestions' => $response['suggestions'] ?? array(),
			'raw_response' => $response,
		);
	}

	/**
	 * Validate phone number.
	 *
	 * @param string $phone Phone number.
	 * @return bool True if valid.
	 */
	public function validate_phone( $phone ) {
		$phone = sanitize_text_field( $phone );

		// Remove common formatting characters.
		$cleaned = preg_replace( '/[\s\-\(\)]+/', '', $phone );

		// Bulgarian phone patterns.
		$patterns = array(
			'/^(\+359|00359|0)[87-9][0-9]{8}$/', // Mobile.
			'/^(\+359|00359|0)[2-9][0-9]{6,8}$/', // Landline.
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $cleaned ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate credentials.
	 *
	 * @return bool True if credentials are valid.
	 * @throws SESH_Speedy_API_Exception On invalid credentials.
	 */
	public function validate_credentials() {
		$this->request( 'client/contract/' );
		return true;
	}

	/**
	 * Get available services.
	 *
	 * @param array $params Optional service filter parameters.
	 * @return array Available services.
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function get_services( $params = array() ) {
		$cache_key = $this->cache_key( 'services', $params );
		$cached    = $this->get_cache( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->request( 'services/', $params );
		$services = isset( $response['services'] ) ? $response['services'] : array();

		$this->set_cache( $cache_key, $services, 'services', self::CACHE_TTL_SERVICES );

		return $services;
	}

	/**
	 * Get service details by ID.
	 *
	 * @param int $service_id Service ID.
	 * @return array|null Service details or null if not found.
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function get_service_details( $service_id ) {
		$services = $this->get_services();

		foreach ( $services as $service ) {
			if ( isset( $service['id'] ) && (int) $service['id'] === (int) $service_id ) {
				return $service;
			}
		}

		return null;
	}

	/**
	 * Get available services for a route.
	 *
	 * @param array $sender    Sender location (site_id, office_id, or address).
	 * @param array $recipient Recipient location (site_id, office_id, or address).
	 * @return array Available services for this route.
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function get_available_services( array $sender, array $recipient ) {
		$params = array(
			'sender'    => $this->format_location_params( $sender ),
			'recipient' => $this->format_location_params( $recipient ),
		);

		$cache_key = $this->cache_key( 'route_services', $params );
		$cached    = $this->get_cache( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->request( 'services/', $params );
		$services = isset( $response['services'] ) ? $response['services'] : array();

		$this->set_cache( $cache_key, $services, 'services', self::CACHE_TTL_SERVICES );

		return $services;
	}

	/**
	 * Format location parameters for API.
	 *
	 * @param array $location Location data.
	 * @return array Formatted location.
	 */
	private function format_location_params( array $location ) {
		$formatted = array();

		if ( ! empty( $location['site_id'] ) ) {
			$formatted['siteId'] = (int) $location['site_id'];
		}

		if ( ! empty( $location['office_id'] ) ) {
			$formatted['officeId'] = (int) $location['office_id'];
		}

		if ( ! empty( $location['address'] ) ) {
			$formatted['address'] = $location['address'];
		}

		return $formatted;
	}

	/**
	 * Get client/contract info.
	 *
	 * @return array Client contract info.
	 * @throws SESH_Speedy_API_Exception On API error.
	 */
	public function get_client_info() {
		$cache_key = $this->cache_key( 'client_info' );
		$cached    = $this->get_cache( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->request( 'client/contract/' );

		$this->set_cache( $cache_key, $response, 'client', self::CACHE_TTL_SERVICES );

		return $response;
	}

	/**
	 * Log message.
	 *
	 * @param string $message Message to log.
	 * @param string $level   Log level.
	 */
	private function log( $message, $level = 'info' ) {
		if ( $this->debug && function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->log( $level, $message, array( 'source' => 'speedy-api' ) );
		} elseif ( $this->debug ) {
			// Fallback to error_log.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Speedy API [' . $level . ']: ' . $message );
		}
	}
}

/**
 * Shipping quote data object.
 */
class SESH_Shipping_Quote {

	/**
	 * Quote data.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param array $data Quote data.
	 */
	public function __construct( $data ) {
		$this->data = wp_parse_args(
			$data,
			array(
				'price'             => 0,
				'price_with_vat'    => 0,
				'currency'          => 'BGN',
				'service_name'      => '',
				'delivery_deadline' => null,
				'raw_response'      => array(),
			)
		);
	}

	/**
	 * Get price.
	 *
	 * @return float
	 */
	public function get_price() {
		return (float) $this->data['price'];
	}

	/**
	 * Get price with VAT.
	 *
	 * @return float
	 */
	public function get_price_with_vat() {
		return (float) $this->data['price_with_vat'];
	}

	/**
	 * Get currency.
	 *
	 * @return string
	 */
	public function get_currency() {
		return $this->data['currency'];
	}

	/**
	 * Get service name.
	 *
	 * @return string
	 */
	public function get_service_name() {
		return $this->data['service_name'];
	}

	/**
	 * Get delivery deadline.
	 *
	 * @return string|null
	 */
	public function get_delivery_deadline() {
		return $this->data['delivery_deadline'];
	}

	/**
	 * Get raw response.
	 *
	 * @return array
	 */
	public function get_raw_response() {
		return $this->data['raw_response'];
	}
}

/**
 * Shipment response data object.
 */
class SESH_Shipment_Response {

	/**
	 * Response data.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param array $data Response data.
	 */
	public function __construct( $data ) {
		$this->data = wp_parse_args(
			$data,
			array(
				'tracking_number' => '',
				'barcode'         => '',
				'price'           => 0,
				'pickup_date'     => null,
				'raw_response'    => array(),
			)
		);
	}

	/**
	 * Get tracking number.
	 *
	 * @return string
	 */
	public function get_tracking_number() {
		return $this->data['tracking_number'];
	}

	/**
	 * Get barcode.
	 *
	 * @return string
	 */
	public function get_barcode() {
		return $this->data['barcode'];
	}

	/**
	 * Get price.
	 *
	 * @return float
	 */
	public function get_price() {
		return (float) $this->data['price'];
	}

	/**
	 * Get pickup date.
	 *
	 * @return string|null
	 */
	public function get_pickup_date() {
		return $this->data['pickup_date'];
	}

	/**
	 * Get raw response.
	 *
	 * @return array
	 */
	public function get_raw_response() {
		return $this->data['raw_response'];
	}
}
