<?php
/**
 * API Exception classes.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base API exception class.
 */
class SESH_API_Exception extends Exception {

	/**
	 * API error code from the carrier.
	 *
	 * @var string
	 */
	protected $api_error_code;

	/**
	 * API error context/details.
	 *
	 * @var array
	 */
	protected $api_error_context;

	/**
	 * Carrier identifier.
	 *
	 * @var string
	 */
	protected $carrier;

	/**
	 * Constructor.
	 *
	 * @param string     $message           Error message.
	 * @param string     $api_error_code    API-specific error code.
	 * @param array      $api_error_context Additional error context.
	 * @param string     $carrier           Carrier identifier.
	 * @param int        $code              Exception code.
	 * @param \Throwable $previous          Previous exception.
	 */
	public function __construct(
		$message = '',
		$api_error_code = '',
		$api_error_context = array(),
		$carrier = '',
		$code = 0,
		$previous = null
	) {
		parent::__construct( $message, $code, $previous );

		$this->api_error_code    = $api_error_code;
		$this->api_error_context = $api_error_context;
		$this->carrier           = $carrier;
	}

	/**
	 * Get API error code.
	 *
	 * @return string
	 */
	public function get_api_error_code() {
		return $this->api_error_code;
	}

	/**
	 * Get API error context.
	 *
	 * @return array
	 */
	public function get_api_error_context() {
		return $this->api_error_context;
	}

	/**
	 * Get carrier identifier.
	 *
	 * @return string
	 */
	public function get_carrier() {
		return $this->carrier;
	}

	/**
	 * Get user-friendly error message.
	 *
	 * @return string
	 */
	public function get_user_message() {
		return $this->message;
	}

	/**
	 * Convert to array for logging.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'message'    => $this->message,
			'code'       => $this->code,
			'api_code'   => $this->api_error_code,
			'context'    => $this->api_error_context,
			'carrier'    => $this->carrier,
			'file'       => $this->file,
			'line'       => $this->line,
		);
	}
}

/**
 * Speedy API specific exception.
 */
class SESH_Speedy_API_Exception extends SESH_API_Exception {

	/**
	 * Constructor.
	 *
	 * @param string     $message           Error message.
	 * @param string     $api_error_code    API-specific error code.
	 * @param array      $api_error_context Additional error context.
	 * @param int        $code              Exception code.
	 * @param \Throwable $previous          Previous exception.
	 */
	public function __construct(
		$message = '',
		$api_error_code = '',
		$api_error_context = array(),
		$code = 0,
		$previous = null
	) {
		parent::__construct( $message, $api_error_code, $api_error_context, 'speedy', $code, $previous );
	}

	/**
	 * Create exception from API response.
	 *
	 * @param array $response API response containing error.
	 * @return self
	 */
	public static function from_response( $response ) {
		$error = $response['error'] ?? array();

		$message = $error['message'] ?? __( 'Unknown Speedy API error', 'speedy_econt_shipping' );
		$code    = $error['code'] ?? '';
		$context = $error['context'] ?? array();

		// Map common error codes to user-friendly messages.
		$user_message = self::get_user_friendly_message( $code, $message );

		return new self( $user_message, $code, $context );
	}

	/**
	 * Create exception from WP_Error.
	 *
	 * @param WP_Error $wp_error WordPress error.
	 * @return self
	 */
	public static function from_wp_error( $wp_error ) {
		return new self(
			$wp_error->get_error_message(),
			$wp_error->get_error_code(),
			$wp_error->get_error_data() ?? array()
		);
	}

	/**
	 * Get user-friendly message for common error codes.
	 *
	 * @param string $code    Error code.
	 * @param string $default Default message.
	 * @return string
	 */
	private static function get_user_friendly_message( $code, $default ) {
		$messages = array(
			'INVALID_CREDENTIALS'      => __( 'Invalid Speedy API credentials. Please check your username and password.', 'speedy_econt_shipping' ),
			'INVALID_SITE_ID'          => __( 'Invalid city/site selected.', 'speedy_econt_shipping' ),
			'INVALID_OFFICE_ID'        => __( 'Invalid office selected.', 'speedy_econt_shipping' ),
			'INVALID_PHONE'            => __( 'Invalid phone number format.', 'speedy_econt_shipping' ),
			'INVALID_ADDRESS'          => __( 'Invalid delivery address.', 'speedy_econt_shipping' ),
			'SERVICE_NOT_AVAILABLE'    => __( 'Shipping service is not available for this route.', 'speedy_econt_shipping' ),
			'WEIGHT_EXCEEDED'          => __( 'Package weight exceeds the allowed limit.', 'speedy_econt_shipping' ),
			'DIMENSIONS_EXCEEDED'      => __( 'Package dimensions exceed the allowed limits.', 'speedy_econt_shipping' ),
			'SHIPMENT_NOT_FOUND'       => __( 'Shipment not found.', 'speedy_econt_shipping' ),
			'SHIPMENT_ALREADY_PICKED'  => __( 'Shipment has already been picked up and cannot be cancelled.', 'speedy_econt_shipping' ),
			'RATE_LIMIT_EXCEEDED'      => __( 'Too many requests. Please try again later.', 'speedy_econt_shipping' ),
			'SERVER_ERROR'             => __( 'Speedy service is temporarily unavailable. Please try again.', 'speedy_econt_shipping' ),
		);

		return $messages[ $code ] ?? $default;
	}
}

/**
 * Econt API specific exception.
 */
class SESH_Econt_API_Exception extends SESH_API_Exception {

	/**
	 * Constructor.
	 *
	 * @param string     $message           Error message.
	 * @param string     $api_error_code    API-specific error code.
	 * @param array      $api_error_context Additional error context.
	 * @param int        $code              Exception code.
	 * @param \Throwable $previous          Previous exception.
	 */
	public function __construct(
		$message = '',
		$api_error_code = '',
		$api_error_context = array(),
		$code = 0,
		$previous = null
	) {
		parent::__construct( $message, $api_error_code, $api_error_context, 'econt', $code, $previous );
	}

	/**
	 * Create exception from API response.
	 *
	 * @param array $response API response containing error.
	 * @return self
	 */
	public static function from_response( $response ) {
		$message = $response['errorMessage'] ?? $response['message'] ?? __( 'Unknown Econt API error', 'speedy_econt_shipping' );
		$code    = $response['errorCode'] ?? $response['code'] ?? '';

		return new self( $message, $code, $response );
	}
}
