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
	 * Bulgaria country code.
	 *
	 * @var string
	 */
	const BULGARIA_COUNTRY_CODE = 'BGR';

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
	 * Constructor.
	 *
	 * @param string $username API username (optional).
	 * @param string $password API password (optional).
	 */
	public function __construct( $username = '', $password = '' ) {
		$this->username = $username;
		$this->password = $password;
		$this->debug    = defined( 'WP_DEBUG' ) && WP_DEBUG;
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
	 * Make a nomenclatures API request (JSON).
	 *
	 * @param string $method API method.
	 * @param array  $params Request parameters.
	 * @return array|WP_Error Response data or error.
	 */
	private function nomenclatures_request( $method, $params = array() ) {
		$url = self::NOMENCLATURES_URL . '.' . $method . '.json';

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

		if ( is_wp_error( $response ) ) {
			$this->log( 'API Error: ' . $response->get_error_message(), 'error' );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $this->debug ) {
			$this->log( "Nomenclatures request to {$method}: " . wp_json_encode( $params ) );
			$this->log( "Response ({$code}): " . substr( $body, 0, 1000 ) );
		}

		if ( 200 !== $code ) {
			return new WP_Error(
				'econt_api_error',
				isset( $data['errorMessage'] ) ? $data['errorMessage'] : __( 'API Error', 'speedy_econt_shipping' ),
				array( 'status' => $code )
			);
		}

		return $data;
	}

	/**
	 * Make a shipments API request.
	 *
	 * @param string $method API method.
	 * @param array  $params Request parameters.
	 * @return array|WP_Error Response data or error.
	 */
	private function shipments_request( $method, $params = array() ) {
		// Add credentials if available.
		if ( ! empty( $this->username ) && ! empty( $this->password ) ) {
			$params['credentials'] = array(
				'username' => $this->username,
				'password' => $this->password,
			);
		}

		$url = self::SHIPMENTS_URL . '.' . $method . '.json';

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

		if ( is_wp_error( $response ) ) {
			$this->log( 'Shipments API Error: ' . $response->get_error_message(), 'error' );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $this->debug ) {
			$this->log( "Shipments request to {$method}: " . wp_json_encode( $params ) );
			$this->log( "Response ({$code}): " . substr( $body, 0, 1000 ) );
		}

		if ( 200 !== $code ) {
			return new WP_Error(
				'econt_api_error',
				isset( $data['errorMessage'] ) ? $data['errorMessage'] : __( 'API Error', 'speedy_econt_shipping' ),
				array( 'status' => $code )
			);
		}

		return $data;
	}

	/**
	 * Get cities/sites.
	 *
	 * @param array $args Optional arguments (country_code).
	 * @return array|WP_Error
	 */
	public function get_sites( $args = array() ) {
		$params = array(
			'countryCode' => isset( $args['country_code'] ) ? $args['country_code'] : self::BULGARIA_COUNTRY_CODE,
		);

		$response = $this->nomenclatures_request( 'getCities', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['cities'] ) ? $response['cities'] : array();
	}

	/**
	 * Get offices.
	 *
	 * @param array $args Optional arguments (country_code, city_id).
	 * @return array|WP_Error
	 */
	public function get_offices( $args = array() ) {
		$params = array(
			'countryCode' => isset( $args['country_code'] ) ? $args['country_code'] : self::BULGARIA_COUNTRY_CODE,
		);

		$response = $this->nomenclatures_request( 'getOffices', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$offices = isset( $response['offices'] ) ? $response['offices'] : array();

		// Filter by city if specified.
		if ( ! empty( $args['city_id'] ) ) {
			$city_id = $args['city_id'];
			$offices = array_filter(
				$offices,
				function ( $office ) use ( $city_id ) {
					return isset( $office['address']['city']['id'] ) &&
						$office['address']['city']['id'] == $city_id;
				}
			);
		}

		return array_values( $offices );
	}

	/**
	 * Get streets for a city.
	 *
	 * @param string $city_name City name.
	 * @return array|WP_Error
	 */
	public function get_streets( $city_name ) {
		$params = array(
			'countryCode' => self::BULGARIA_COUNTRY_CODE,
			'cityName'    => $city_name,
		);

		$response = $this->nomenclatures_request( 'getStreets', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['streets'] ) ? $response['streets'] : array();
	}

	/**
	 * Get quarters for a city.
	 *
	 * @param string $city_name City name.
	 * @return array|WP_Error
	 */
	public function get_quarters( $city_name ) {
		$params = array(
			'countryCode' => self::BULGARIA_COUNTRY_CODE,
			'cityName'    => $city_name,
		);

		$response = $this->nomenclatures_request( 'getQuarters', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['quarters'] ) ? $response['quarters'] : array();
	}

	/**
	 * Calculate shipping price.
	 *
	 * @param array $params Calculation parameters.
	 * @return SESH_Shipping_Quote|WP_Error
	 */
	public function calculate_shipping( $params ) {
		// Transform params to Econt format if needed.
		$econt_params = $this->transform_shipping_params( $params );

		$response = $this->shipments_request( 'calculateLabel', $econt_params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$price = isset( $response['price'] ) ? $response['price'] : 0;

		return new SESH_Shipping_Quote(
			array(
				'price'             => $price,
				'price_with_vat'    => $price,
				'currency'          => 'BGN',
				'service_name'      => isset( $response['tariffCode'] ) ? $response['tariffCode'] : '',
				'delivery_deadline' => null,
				'raw_response'      => $response,
			)
		);
	}

	/**
	 * Create a shipment.
	 *
	 * @param array $params Shipment parameters.
	 * @return SESH_Shipment_Response|WP_Error
	 */
	public function create_shipment( $params ) {
		$econt_params = $this->transform_shipment_params( $params );

		$response = $this->shipments_request( 'createLabel', $econt_params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return new SESH_Shipment_Response(
			array(
				'tracking_number' => isset( $response['shipmentNumber'] ) ? $response['shipmentNumber'] : '',
				'barcode'         => isset( $response['shipmentNumber'] ) ? $response['shipmentNumber'] : '',
				'price'           => isset( $response['price'] ) ? $response['price'] : 0,
				'pickup_date'     => null,
				'raw_response'    => $response,
			)
		);
	}

	/**
	 * Cancel a shipment.
	 *
	 * @param string $tracking_number Tracking number.
	 * @return bool|WP_Error
	 */
	public function cancel_shipment( $tracking_number ) {
		$params = array(
			'shipmentNumber' => $tracking_number,
		);

		$response = $this->shipments_request( 'deleteLabels', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Get shipping label.
	 *
	 * @param string $tracking_number Tracking number.
	 * @param string $format          Label format.
	 * @return string|WP_Error
	 */
	public function get_label( $tracking_number, $format = 'pdf' ) {
		$params = array(
			'shipmentNumber' => $tracking_number,
		);

		$response = $this->shipments_request( 'createLabel', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['label'] ) ) {
			return new WP_Error( 'no_label', __( 'Label not available', 'speedy_econt_shipping' ) );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return base64_decode( $response['label'] );
	}

	/**
	 * Track a shipment.
	 *
	 * @param string $tracking_number Tracking number.
	 * @return array|WP_Error
	 */
	public function track_shipment( $tracking_number ) {
		// Econt tracking is typically done via their website.
		// This is a placeholder for future implementation.
		return array();
	}

	/**
	 * Validate credentials.
	 *
	 * @return bool|WP_Error
	 */
	public function validate_credentials() {
		// Econt doesn't require credentials for basic nomenclature operations.
		// For shipment operations, credentials are validated on first use.
		if ( empty( $this->username ) || empty( $this->password ) ) {
			return true; // Nomenclature-only mode.
		}

		// Try to get client info to validate credentials.
		$response = $this->shipments_request( 'getClientProfiles', array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Transform generic shipping params to Econt format.
	 *
	 * @param array $params Generic parameters.
	 * @return array Econt-formatted parameters.
	 */
	private function transform_shipping_params( $params ) {
		// This will be implemented fully when dynamic pricing is added.
		return $params;
	}

	/**
	 * Transform generic shipment params to Econt format.
	 *
	 * @param array $params Generic parameters.
	 * @return array Econt-formatted parameters.
	 */
	private function transform_shipment_params( $params ) {
		// This will be implemented fully when label generation is added.
		return $params;
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
