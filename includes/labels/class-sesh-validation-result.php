<?php
/**
 * Validation result class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validation result class.
 *
 * Holds the result of order validation for label generation.
 */
class SESH_Validation_Result {

	/**
	 * Whether validation passed.
	 *
	 * @var bool
	 */
	private $valid;

	/**
	 * Validation errors.
	 *
	 * @var array
	 */
	private $errors;

	/**
	 * Constructor.
	 *
	 * @param bool  $valid  Whether validation passed.
	 * @param array $errors Array of error messages.
	 */
	public function __construct( $valid, $errors = array() ) {
		$this->valid  = $valid;
		$this->errors = $errors;
	}

	/**
	 * Check if validation passed.
	 *
	 * @return bool
	 */
	public function is_valid() {
		return $this->valid;
	}

	/**
	 * Get validation errors.
	 *
	 * @return array
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * Get errors as a formatted string.
	 *
	 * @param string $separator Separator between errors.
	 * @return string
	 */
	public function get_errors_string( $separator = "\n" ) {
		return implode( $separator, $this->errors );
	}

	/**
	 * Add an error.
	 *
	 * @param string $error Error message.
	 */
	public function add_error( $error ) {
		$this->errors[] = $error;
		$this->valid    = false;
	}

	/**
	 * Check if there are errors.
	 *
	 * @return bool
	 */
	public function has_errors() {
		return ! empty( $this->errors );
	}
}
