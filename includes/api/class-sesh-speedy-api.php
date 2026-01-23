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
	 * Bulgaria country ID in Speedy system.
	 *
	 * @var int
	 */
	const BULGARIA_COUNTRY_ID = 100;

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
	 * Constructor.
	 *
	 * @param string $username API username.
	 * @param string $password API password.
	 */
	public function __construct( $username, $password ) {
		$this->username = $username;
		$this->password = $password;
		$this->debug    = defined( 'WP_DEBUG' ) && WP_DEBUG;
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
	 * Make an API request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $params   Request parameters.
	 * @return array|WP_Error Response data or error.
	 */
	private function request( $endpoint, $params = array() ) {
		// Add authentication.
		$params['userName'] = $this->username;
		$params['password'] = $this->password;
		$params['language'] = 'BG';

		$url = self::API_BASE_URL . '/' . ltrim( $endpoint, '/' );

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
			$this->log( "Request to {$endpoint}: " . wp_json_encode( $params ) );
			$this->log( "Response ({$code}): " . $body );
		}

		if ( $code !== 200 ) {
			$error_message = isset( $data['error']['message'] )
				? $data['error']['message']
				: __( 'Unknown API error', 'speedy_econt_shipping' );
			return new WP_Error( 'speedy_api_error', $error_message, array( 'status' => $code ) );
		}

		// Check for API-level errors.
		if ( isset( $data['error'] ) ) {
			return new WP_Error(
				'speedy_api_error',
				$data['error']['message'] ?? __( 'API Error', 'speedy_econt_shipping' ),
				$data['error']
			);
		}

		return $data;
	}

	/**
	 * Get sites/cities.
	 *
	 * @param array $args Optional arguments (name, countryId).
	 * @return array|WP_Error
	 */
	public function get_sites( $args = array() ) {
		$params = array(
			'countryId' => isset( $args['country_id'] ) ? $args['country_id'] : self::BULGARIA_COUNTRY_ID,
		);

		if ( ! empty( $args['name'] ) ) {
			$params['name'] = $args['name'];
		}

		$response = $this->request( 'location/site/', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['sites'] ) ? $response['sites'] : array();
	}

	/**
	 * Get site by ID.
	 *
	 * @param int $site_id Site ID.
	 * @return array|WP_Error
	 */
	public function get_site( $site_id ) {
		$params = array(
			'countryId' => self::BULGARIA_COUNTRY_ID,
		);

		$response = $this->request( 'location/site/' . $site_id, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['site'] ) ? $response['site'] : null;
	}

	/**
	 * Get offices.
	 *
	 * @param array $args Optional arguments (siteId, name).
	 * @return array|WP_Error
	 */
	public function get_offices( $args = array() ) {
		$params = array(
			'countryId' => isset( $args['country_id'] ) ? $args['country_id'] : self::BULGARIA_COUNTRY_ID,
		);

		if ( ! empty( $args['site_id'] ) ) {
			$params['siteId'] = $args['site_id'];
		}

		if ( ! empty( $args['name'] ) ) {
			$params['name'] = $args['name'];
		}

		$response = $this->request( 'location/office/', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$offices = isset( $response['offices'] ) ? $response['offices'] : array();

		// Filter out closed offices.
		return array_filter(
			$offices,
			function ( $office ) {
				return ! empty( $office['pickUpAllowed'] ) && ! empty( $office['dropOffAllowed'] );
			}
		);
	}

	/**
	 * Calculate shipping price.
	 *
	 * @param array $params Calculation parameters.
	 * @return SESH_Shipping_Quote|WP_Error
	 */
	public function calculate_shipping( $params ) {
		$response = $this->request( 'calculate/', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['calculations'] ) ) {
			return new WP_Error( 'no_calculations', __( 'No shipping calculations available', 'speedy_econt_shipping' ) );
		}

		$calculation = $response['calculations'][0];

		return new SESH_Shipping_Quote(
			array(
				'price'             => $calculation['price']['total'] ?? 0,
				'price_with_vat'    => $calculation['price']['total'] ?? 0,
				'currency'          => 'BGN',
				'service_name'      => $calculation['serviceId'] ?? '',
				'delivery_deadline' => $calculation['deliveryDeadline'] ?? null,
				'raw_response'      => $calculation,
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
		$response = $this->request( 'shipment/', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

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
	 * @return bool|WP_Error
	 */
	public function cancel_shipment( $tracking_number ) {
		$params = array(
			'shipmentId' => $tracking_number,
		);

		$response = $this->request( 'shipment/cancel/', $params );

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
			'parcels' => array(
				array(
					'parcel' => array(
						'id' => $tracking_number,
					),
				),
			),
		);

		$response = $this->request( 'print/', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['parcels'][0]['pdf'] ) ) {
			return new WP_Error( 'no_label', __( 'Label not available', 'speedy_econt_shipping' ) );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return base64_decode( $response['parcels'][0]['pdf'] );
	}

	/**
	 * Track a shipment.
	 *
	 * @param string $tracking_number Tracking number.
	 * @return array|WP_Error
	 */
	public function track_shipment( $tracking_number ) {
		$params = array(
			'parcels' => array( $tracking_number ),
		);

		$response = $this->request( 'track/', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['parcels'][0]['operations'] ) ? $response['parcels'][0]['operations'] : array();
	}

	/**
	 * Validate credentials.
	 *
	 * @return bool|WP_Error
	 */
	public function validate_credentials() {
		$response = $this->request( 'client/contract/' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Get available services.
	 *
	 * @param array $params Service lookup parameters.
	 * @return array|WP_Error
	 */
	public function get_services( $params = array() ) {
		$response = $this->request( 'services/', $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['services'] ) ? $response['services'] : array();
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
