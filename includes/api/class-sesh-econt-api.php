<?php
/**
 * Econt API Client.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Econt API client class.
 *
 * Handles all communication with the Econt API.
 *
 * @see https://www.econt.com/developers/xml-api.html
 */
class SESH_Econt_API implements SESH_API_Client_Interface {

	/**
	 * API base URL for nomenclatures (JSON).
	 *
	 * @var string
	 */
	const NOMENCLATURES_URL = 'https://ee.econt.com/services/Nomenclatures/NomenclaturesService';

	/**
	 * API base URL for shipments.
	 *
	 * @var string
	 */
	const SHIPMENTS_URL = 'https://ee.econt.com/services/Shipments/LabelService';

	/**
	 * Demo API URL.
	 *
	 * @var string
	 */
	const DEMO_URL = 'http://demo.econt.com';

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
	 * Bulgaria country code.
	 *
	 * @var string
	 */
	const BULGARIA_COUNTRY_CODE = 'BGR';

	/**
	 * Cache TTL constants (in seconds).
	 */
	const CACHE_TTL_SITES    = 86400; // 24 hours.
	const CACHE_TTL_OFFICES  = 86400; // 24 hours.
	const CACHE_TTL_STREETS  = 86400; // 24 hours.
	const CACHE_TTL_QUARTERS = 86400; // 24 hours.
	const CACHE_TTL_PRICES   = 300;   // 5 minutes.

	/**
	 * API username (optional for nomenclatures).
	 *
	 * @var string
	 */
	private $username = '';

	/**
	 * API password (optional for nomenclatures).
	 *
	 * @var string
	 */
	private $password = '';

	/**
	 * Test mode flag.
	 *
	 * @var bool
	 */
	private $test_mode = false;

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
	 * @param string             $username API username (optional).
	 * @param string             $password API password (optional).
	 * @param SESH_Database|null $database Optional database instance for caching.
	 */
	public function __construct( $username = '', $password = '', $database = null ) {
		$this->username = $username;
		$this->password = $password;
		$this->debug    = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$this->db       = $database;
	}

	/**
	 * Set test mode.
	 *
	 * @param bool $enabled Whether to enable test mode.
	 */
	public function set_test_mode( $enabled ) {
		$this->test_mode = (bool) $enabled;
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
		return 'econt';
	}

	/**
	 * Get carrier name.
	 *
	 * @return string
	 */
	public function get_carrier_name() {
		return __( 'Econt', 'speedy_econt_shipping' );
	}

	/**
	 * Make a nomenclatures API request (JSON) with retry logic.
	 *
	 * @param string $method API method.
	 * @param array  $params Request parameters.
	 * @return array Response data.
	 * @throws SESH_Econt_API_Exception On API error.
	 */
	private function nomenclatures_request( $method, $params = array() ) {
		$url           = self::NOMENCLATURES_URL . '.' . $method . '.json';
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
				$this->log( "Nomenclatures API Error (attempt {$attempt}): " . $response->get_error_message(), 'error' );

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
				$this->log( "Nomenclatures request to {$method} (attempt {$attempt}): " . wp_json_encode( $params ) );
				$this->log( "Response ({$code}): " . substr( $body, 0, 1000 ) );
			}

			// Handle HTTP errors.
			if ( $code >= 500 ) {
				// Server errors are retryable.
				$last_error = new WP_Error(
					'econt_server_error',
					__( 'Econt server error', 'speedy_econt_shipping' ),
					array( 'status' => $code )
				);

				if ( $attempt < self::MAX_RETRIES ) {
					$this->sleep_with_backoff( $attempt );
				}
				continue;
			}

			if ( 200 !== $code ) {
				$error_message = isset( $data['errorMessage'] )
					? $data['errorMessage']
					: __( 'Unknown API error', 'speedy_econt_shipping' );

				throw new SESH_Econt_API_Exception(
					$error_message,
					isset( $data['errorCode'] ) ? $data['errorCode'] : 'HTTP_' . $code,
					$data
				);
			}

			// Check for API-level errors in response body.
			if ( isset( $data['error'] ) || isset( $data['errorMessage'] ) ) {
				throw SESH_Econt_API_Exception::from_response( $data );
			}

			// Success!
			return $data;
		}

		// All retries exhausted.
		if ( $last_error instanceof WP_Error ) {
			throw new SESH_Econt_API_Exception(
				$last_error->get_error_message(),
				$last_error->get_error_code(),
				$last_error->get_error_data() ?? array()
			);
		}

		throw new SESH_Econt_API_Exception(
			__( 'API request failed after multiple attempts', 'speedy_econt_shipping' ),
			'MAX_RETRIES_EXCEEDED'
		);
	}

	/**
	 * Make a shipments API request with retry logic.
	 *
	 * @param string $method API method.
	 * @param array  $params Request parameters.
	 * @return array Response data.
	 * @throws SESH_Econt_API_Exception On API error.
	 */
	private function shipments_request( $method, $params = array() ) {
		// Add credentials if available.
		if ( ! empty( $this->username ) && ! empty( $this->password ) ) {
			$params['credentials'] = array(
				'username' => $this->username,
				'password' => $this->password,
			);
		}

		$url           = self::SHIPMENTS_URL . '.' . $method . '.json';
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

			// Handle WordPress HTTP errors.
			if ( is_wp_error( $response ) ) {
				$last_error = $response;
				$this->log( "Shipments API Error (attempt {$attempt}): " . $response->get_error_message(), 'error' );

				if ( $this->is_non_retryable_error( $response ) ) {
					break;
				}

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
				$log_params = $params;
				if ( isset( $log_params['credentials']['password'] ) ) {
					$log_params['credentials']['password'] = '***';
				}
				$this->log( "Shipments request to {$method} (attempt {$attempt}): " . wp_json_encode( $log_params ) );
				$this->log( "Response ({$code}): " . substr( $body, 0, 1000 ) );
			}

			// Handle HTTP errors.
			if ( $code >= 500 ) {
				$last_error = new WP_Error(
					'econt_server_error',
					__( 'Econt server error', 'speedy_econt_shipping' ),
					array( 'status' => $code )
				);

				if ( $attempt < self::MAX_RETRIES ) {
					$this->sleep_with_backoff( $attempt );
				}
				continue;
			}

			if ( 200 !== $code ) {
				$error_message = isset( $data['errorMessage'] )
					? $data['errorMessage']
					: __( 'Unknown API error', 'speedy_econt_shipping' );

				throw new SESH_Econt_API_Exception(
					$error_message,
					isset( $data['errorCode'] ) ? $data['errorCode'] : 'HTTP_' . $code,
					$data
				);
			}

			// Check for API-level errors.
			if ( isset( $data['error'] ) || isset( $data['errorMessage'] ) ) {
				throw SESH_Econt_API_Exception::from_response( $data );
			}

			// Success!
			return $data;
		}

		// All retries exhausted.
		if ( $last_error instanceof WP_Error ) {
			throw new SESH_Econt_API_Exception(
				$last_error->get_error_message(),
				$last_error->get_error_code(),
				$last_error->get_error_data() ?? array()
			);
		}

		throw new SESH_Econt_API_Exception(
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

		return $this->db->set_cache( $cache_key, $value, 'econt', $request_type, $ttl );
	}

	/**
	 * Generate cache key.
	 *
	 * @param string $method Method name.
	 * @param array  $params Parameters.
	 * @return string
	 */
	private function cache_key( $method, $params = array() ) {
		// Remove credentials from cache key.
		unset( $params['credentials'] );

		$hash = md5( wp_json_encode( $params ) );
		return "econt_{$method}_{$hash}";
	}

	/**
	 * Get cities/sites.
	 *
	 * @param array $args Optional arguments (country_code).
	 * @return array
	 * @throws SESH_Econt_API_Exception On API error.
	 */
	public function get_sites( $args = array() ) {
		$params = array(
			'countryCode' => isset( $args['country_code'] ) ? $args['country_code'] : self::BULGARIA_COUNTRY_CODE,
		);

		$cache_key = $this->cache_key( 'sites', $params );
		$cached    = $this->get_cache( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->nomenclatures_request( 'getCities', $params );
		$sites    = isset( $response['cities'] ) ? $response['cities'] : array();

		$this->set_cache( $cache_key, $sites, 'sites', self::CACHE_TTL_SITES );

		return $sites;
	}

	/**
	 * Get offices.
	 *
	 * @param array $args Optional arguments (country_code, city_id).
	 * @return array
	 * @throws SESH_Econt_API_Exception On API error.
	 */
	public function get_offices( $args = array() ) {
		$params = array(
			'countryCode' => isset( $args['country_code'] ) ? $args['country_code'] : self::BULGARIA_COUNTRY_CODE,
		);

		$cache_key = $this->cache_key( 'offices', $params );
		$cached    = $this->get_cache( $cache_key );

		if ( false !== $cached ) {
			$offices = $cached;
		} else {
			$response = $this->nomenclatures_request( 'getOffices', $params );
			$offices  = isset( $response['offices'] ) ? $response['offices'] : array();

			$this->set_cache( $cache_key, $offices, 'offices', self::CACHE_TTL_OFFICES );
		}

		// Filter by city if specified.
		if ( ! empty( $args['city_id'] ) ) {
			$city_id = absint( $args['city_id'] );
			$offices = array_filter(
				$offices,
				function ( $office ) use ( $city_id ) {
					return isset( $office['address']['city']['id'] ) &&
						absint( $office['address']['city']['id'] ) === $city_id;
				}
			);
		}

		return array_values( $offices );
	}

	/**
	 * Get streets for a city.
	 *
	 * @param string $city_name City name.
	 * @return array
	 * @throws SESH_Econt_API_Exception On API error.
	 */
	public function get_streets( $city_name ) {
		$params = array(
			'countryCode' => self::BULGARIA_COUNTRY_CODE,
			'cityName'    => sanitize_text_field( $city_name ),
		);

		$cache_key = $this->cache_key( 'streets', $params );
		$cached    = $this->get_cache( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->nomenclatures_request( 'getStreets', $params );
		$streets  = isset( $response['streets'] ) ? $response['streets'] : array();

		$this->set_cache( $cache_key, $streets, 'streets', self::CACHE_TTL_STREETS );

		return $streets;
	}

	/**
	 * Get quarters for a city.
	 *
	 * @param string $city_name City name.
	 * @return array
	 * @throws SESH_Econt_API_Exception On API error.
	 */
	public function get_quarters( $city_name ) {
		$params = array(
			'countryCode' => self::BULGARIA_COUNTRY_CODE,
			'cityName'    => sanitize_text_field( $city_name ),
		);

		$cache_key = $this->cache_key( 'quarters', $params );
		$cached    = $this->get_cache( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->nomenclatures_request( 'getQuarters', $params );
		$quarters = isset( $response['quarters'] ) ? $response['quarters'] : array();

		$this->set_cache( $cache_key, $quarters, 'quarters', self::CACHE_TTL_QUARTERS );

		return $quarters;
	}

	/**
	 * Calculate shipping price.
	 *
	 * @param array $params Calculation parameters.
	 * @return SESH_Shipping_Quote
	 * @throws SESH_Econt_API_Exception On API error.
	 */
	public function calculate_shipping( $params ) {
		// Transform params to Econt format if needed.
		$econt_params = $this->transform_shipping_params( $params );

		// Short cache for prices.
		$cache_key = $this->cache_key( 'calculate', $econt_params );
		$cached    = $this->get_cache( $cache_key );

		if ( false !== $cached ) {
			return new SESH_Shipping_Quote( $cached );
		}

		$response = $this->shipments_request( 'calculateLabel', $econt_params );

		$price          = isset( $response['price'] ) ? floatval( $response['price'] ) : 0;
		$price_with_vat = isset( $response['totalPrice'] ) ? floatval( $response['totalPrice'] ) : $price;

		$quote_data = array(
			'price'             => $price,
			'price_with_vat'    => $price_with_vat,
			'currency'          => 'BGN',
			'service_name'      => isset( $response['tariffCode'] ) ? $response['tariffCode'] : '',
			'delivery_deadline' => isset( $response['deliveryDeadline'] ) ? $response['deliveryDeadline'] : null,
			'raw_response'      => $response,
		);

		$this->set_cache( $cache_key, $quote_data, 'prices', self::CACHE_TTL_PRICES );

		return new SESH_Shipping_Quote( $quote_data );
	}

	/**
	 * Create a shipment.
	 *
	 * @param array $params Shipment parameters.
	 * @return SESH_Shipment_Response
	 * @throws SESH_Econt_API_Exception On API error.
	 */
	public function create_shipment( $params ) {
		$econt_params = $this->transform_shipment_params( $params );

		$response = $this->shipments_request( 'createLabel', $econt_params );

		if ( empty( $response['shipmentNumber'] ) ) {
			throw new SESH_Econt_API_Exception(
				__( 'Failed to create shipment: No shipment number returned', 'speedy_econt_shipping' ),
				'NO_SHIPMENT_NUMBER',
				$response
			);
		}

		return new SESH_Shipment_Response(
			array(
				'tracking_number' => $response['shipmentNumber'],
				'barcode'         => $response['shipmentNumber'],
				'price'           => isset( $response['price'] ) ? floatval( $response['price'] ) : 0,
				'pickup_date'     => isset( $response['expectedDeliveryDate'] ) ? $response['expectedDeliveryDate'] : null,
				'raw_response'    => $response,
			)
		);
	}

	/**
	 * Cancel a shipment.
	 *
	 * @param string $tracking_number Tracking number.
	 * @return bool
	 * @throws SESH_Econt_API_Exception On API error.
	 */
	public function cancel_shipment( $tracking_number ) {
		$params = array(
			'shipmentNumbers' => array( sanitize_text_field( $tracking_number ) ),
		);

		$response = $this->shipments_request( 'deleteLabels', $params );

		// Check if deletion was successful.
		if ( isset( $response['result'] ) && ! empty( $response['result']['deleted'] ) ) {
			return true;
		}

		// If no clear success indicator, check for errors.
		if ( isset( $response['errors'] ) && ! empty( $response['errors'] ) ) {
			$error_msg = is_array( $response['errors'] )
				? implode( ', ', $response['errors'] )
				: $response['errors'];

			throw new SESH_Econt_API_Exception(
				$error_msg,
				'CANCELLATION_FAILED',
				$response
			);
		}

		return true;
	}

	/**
	 * Get shipping label.
	 *
	 * @param string $tracking_number Tracking number.
	 * @param string $format          Label format (pdf, zpl).
	 * @return string PDF/ZPL binary data.
	 * @throws SESH_Econt_API_Exception On API error.
	 */
	public function get_label( $tracking_number, $format = 'pdf' ) {
		$params = array(
			'shipmentNumbers' => array( sanitize_text_field( $tracking_number ) ),
			'type'            => strtoupper( sanitize_text_field( $format ) ), // PDF or ZPL.
		);

		$response = $this->shipments_request( 'createLabel', $params );

		// Label might be in 'label' or 'labels' array.
		$label_data = null;

		if ( isset( $response['label'] ) ) {
			$label_data = $response['label'];
		} elseif ( isset( $response['labels'][0] ) ) {
			$label_data = $response['labels'][0];
		}

		if ( empty( $label_data ) ) {
			throw new SESH_Econt_API_Exception(
				__( 'Label not available', 'speedy_econt_shipping' ),
				'NO_LABEL',
				$response
			);
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return base64_decode( $label_data );
	}

	/**
	 * Track a shipment.
	 *
	 * @param string $tracking_number Tracking number.
	 * @return array Tracking events.
	 * @throws SESH_Econt_API_Exception On API error.
	 */
	public function track_shipment( $tracking_number ) {
		$params = array(
			'shipmentNumbers' => array( sanitize_text_field( $tracking_number ) ),
		);

		try {
			$response = $this->shipments_request( 'trackShipment', $params );

			// Extract tracking events.
			if ( isset( $response['shipments'][0]['trackingEvents'] ) ) {
				return $response['shipments'][0]['trackingEvents'];
			}

			if ( isset( $response['trackingEvents'] ) ) {
				return $response['trackingEvents'];
			}

			return array();

		} catch ( SESH_Econt_API_Exception $e ) {
			// If tracking endpoint is not available, return empty array.
			// Econt tracking is sometimes only available via their website.
			if ( $e->get_api_error_code() === 'HTTP_404' ) {
				return array();
			}

			throw $e;
		}
	}

	/**
	 * Validate credentials.
	 *
	 * @return bool True if credentials are valid.
	 * @throws SESH_Econt_API_Exception On invalid credentials.
	 */
	public function validate_credentials() {
		// Econt doesn't require credentials for basic nomenclature operations.
		// For shipment operations, credentials are validated on first use.
		if ( empty( $this->username ) || empty( $this->password ) ) {
			return true; // Nomenclature-only mode.
		}

		// Try to get client profiles to validate credentials.
		try {
			$response = $this->shipments_request( 'getClientProfiles', array() );

			// If we get a response, credentials are valid.
			if ( isset( $response['profiles'] ) || isset( $response['profile'] ) ) {
				return true;
			}

			return true;

		} catch ( SESH_Econt_API_Exception $e ) {
			// Re-throw authentication errors.
			if ( stripos( $e->getMessage(), 'credential' ) !== false ||
				 stripos( $e->getMessage(), 'authentication' ) !== false ||
				 stripos( $e->getMessage(), 'unauthorized' ) !== false ) {
				throw $e;
			}

			// For other errors, we can't determine if credentials are valid.
			throw new SESH_Econt_API_Exception(
				__( 'Unable to validate Econt credentials', 'speedy_econt_shipping' ),
				'VALIDATION_FAILED',
				$e->get_api_error_context()
			);
		}
	}

	/**
	 * Transform generic shipping params to Econt format.
	 *
	 * @param array $params Generic parameters.
	 * @return array Econt-formatted parameters.
	 */
	private function transform_shipping_params( $params ) {
		$econt_params = array();

		// Sender information.
		if ( isset( $params['sender'] ) ) {
			$econt_params['senderAddress'] = $this->format_address( $params['sender'] );
		}

		// Receiver information.
		if ( isset( $params['receiver'] ) ) {
			$econt_params['receiverAddress'] = $this->format_address( $params['receiver'] );
		}

		// Shipment details.
		if ( isset( $params['weight'] ) ) {
			$econt_params['weight'] = floatval( $params['weight'] );
		}

		if ( isset( $params['cod_amount'] ) ) {
			$econt_params['paymentReceiverAmount'] = floatval( $params['cod_amount'] );
			$econt_params['paymentReceiverMethod'] = 'CASH';
		}

		// Delivery type (office or address).
		if ( isset( $params['delivery_type'] ) ) {
			$econt_params['deliveryType'] = strtoupper( $params['delivery_type'] );
		}

		return array_merge( $params, $econt_params );
	}

	/**
	 * Transform generic shipment params to Econt format.
	 *
	 * @param array $params Generic parameters.
	 * @return array Econt-formatted parameters.
	 */
	private function transform_shipment_params( $params ) {
		$econt_params = array(
			'shipmentType' => 'PACK',
			'mode'         => 'CALCULATE',
		);

		// Sender information.
		if ( isset( $params['sender'] ) ) {
			$econt_params['sender'] = $this->format_contact( $params['sender'] );
		}

		// Receiver information.
		if ( isset( $params['receiver'] ) ) {
			$econt_params['receiver'] = $this->format_contact( $params['receiver'] );
		}

		// Shipment details.
		if ( isset( $params['weight'] ) ) {
			$econt_params['weight'] = floatval( $params['weight'] );
		}

		if ( isset( $params['packages'] ) && is_array( $params['packages'] ) ) {
			$econt_params['packCount'] = count( $params['packages'] );
		}

		if ( isset( $params['cod_amount'] ) && floatval( $params['cod_amount'] ) > 0 ) {
			$econt_params['paymentReceiverAmount'] = floatval( $params['cod_amount'] );
			$econt_params['paymentReceiverMethod'] = 'CASH';
		}

		// Service type.
		if ( isset( $params['service_type'] ) ) {
			$econt_params['serviceType'] = strtoupper( $params['service_type'] );
		}

		// Instructions.
		if ( isset( $params['instructions'] ) ) {
			$econt_params['instructions'] = sanitize_textarea_field( $params['instructions'] );
		}

		return array_merge( $params, $econt_params );
	}

	/**
	 * Format address for API request.
	 *
	 * @param array $address Address data.
	 * @return array Formatted address.
	 */
	private function format_address( $address ) {
		$formatted = array();

		if ( ! empty( $address['city_id'] ) ) {
			$formatted['city'] = array(
				'id' => absint( $address['city_id'] ),
			);
		} elseif ( ! empty( $address['city_name'] ) ) {
			$formatted['city'] = array(
				'name' => sanitize_text_field( $address['city_name'] ),
			);
		}

		if ( ! empty( $address['office_id'] ) ) {
			$formatted['office'] = array(
				'id' => absint( $address['office_id'] ),
			);
		}

		if ( ! empty( $address['street'] ) ) {
			$formatted['street'] = sanitize_text_field( $address['street'] );
		}

		if ( ! empty( $address['street_number'] ) ) {
			$formatted['num'] = sanitize_text_field( $address['street_number'] );
		}

		if ( ! empty( $address['quarter'] ) ) {
			$formatted['quarter'] = sanitize_text_field( $address['quarter'] );
		}

		if ( ! empty( $address['postcode'] ) ) {
			$formatted['zipCode'] = sanitize_text_field( $address['postcode'] );
		}

		return $formatted;
	}

	/**
	 * Format contact information for API request.
	 *
	 * @param array $contact Contact data.
	 * @return array Formatted contact.
	 */
	private function format_contact( $contact ) {
		$formatted = array();

		if ( ! empty( $contact['name'] ) ) {
			$formatted['name'] = sanitize_text_field( $contact['name'] );
		}

		if ( ! empty( $contact['phone'] ) ) {
			$formatted['phone'] = sanitize_text_field( $contact['phone'] );
		}

		if ( ! empty( $contact['email'] ) ) {
			$formatted['email'] = sanitize_email( $contact['email'] );
		}

		// Add address.
		if ( isset( $contact['address'] ) ) {
			$formatted['address'] = $this->format_address( $contact['address'] );
		} else {
			// Try to build address from contact fields.
			$address_fields = array( 'city_id', 'city_name', 'office_id', 'street', 'street_number', 'quarter', 'postcode' );
			$has_address    = false;

			foreach ( $address_fields as $field ) {
				if ( ! empty( $contact[ $field ] ) ) {
					$has_address = true;
					break;
				}
			}

			if ( $has_address ) {
				$formatted['address'] = $this->format_address( $contact );
			}
		}

		return $formatted;
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
	 * Log message.
	 *
	 * @param string $message Message to log.
	 * @param string $level   Log level.
	 */
	private function log( $message, $level = 'info' ) {
		if ( $this->debug && function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->log( $level, $message, array( 'source' => 'econt-api' ) );
		} elseif ( $this->debug ) {
			// Fallback to error_log.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Econt API [' . $level . ']: ' . $message );
		}
	}
}
