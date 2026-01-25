<?php
/**
 * Label result class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Label result class.
 *
 * Holds the result of label generation.
 */
class SESH_Label_Result {

	/**
	 * Whether generation was successful.
	 *
	 * @var bool
	 */
	private $success;

	/**
	 * Label ID in database.
	 *
	 * @var int|null
	 */
	private $label_id;

	/**
	 * Tracking number.
	 *
	 * @var string
	 */
	private $tracking_number;

	/**
	 * Error message if failed.
	 *
	 * @var string
	 */
	private $error;

	/**
	 * Raw API response.
	 *
	 * @var array
	 */
	private $raw_response;

	/**
	 * Constructor.
	 *
	 * @param bool   $success         Whether generation succeeded.
	 * @param int    $label_id        Label ID.
	 * @param string $tracking_number Tracking number.
	 * @param string $error           Error message.
	 * @param array  $raw_response    Raw API response.
	 */
	public function __construct( $success, $label_id = null, $tracking_number = '', $error = '', $raw_response = array() ) {
		$this->success         = $success;
		$this->label_id        = $label_id;
		$this->tracking_number = $tracking_number;
		$this->error           = $error;
		$this->raw_response    = $raw_response;
	}

	/**
	 * Check if generation was successful.
	 *
	 * @return bool
	 */
	public function is_success() {
		return $this->success;
	}

	/**
	 * Get label ID.
	 *
	 * @return int|null
	 */
	public function get_label_id() {
		return $this->label_id;
	}

	/**
	 * Get tracking number.
	 *
	 * @return string
	 */
	public function get_tracking_number() {
		return $this->tracking_number;
	}

	/**
	 * Get error message.
	 *
	 * @return string
	 */
	public function get_error() {
		return $this->error;
	}

	/**
	 * Get raw API response.
	 *
	 * @return array
	 */
	public function get_raw_response() {
		return $this->raw_response;
	}

	/**
	 * Check if there's an error.
	 *
	 * @return bool
	 */
	public function has_error() {
		return ! empty( $this->error );
	}
}
