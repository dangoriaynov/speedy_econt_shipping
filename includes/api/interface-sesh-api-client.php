<?php
/**
 * API Client Interface.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Interface for courier API clients.
 *
 * All API clients (Speedy, Econt) must implement this interface
 * to ensure consistent functionality across different carriers.
 */
interface SESH_API_Client_Interface {

	/**
	 * Get list of cities/sites.
	 *
	 * @param array $args Optional arguments.
	 * @return array Array of cities/sites.
	 */
	public function get_sites( $args = array() );

	/**
	 * Get list of offices.
	 *
	 * @param array $args Optional arguments.
	 * @return array Array of offices.
	 */
	public function get_offices( $args = array() );

	/**
	 * Calculate shipping price.
	 *
	 * @param array $params Calculation parameters.
	 * @return SESH_Shipping_Quote|WP_Error Quote object or error.
	 */
	public function calculate_shipping( $params );

	/**
	 * Create a shipment.
	 *
	 * @param array $params Shipment parameters.
	 * @return SESH_Shipment_Response|WP_Error Response object or error.
	 */
	public function create_shipment( $params );

	/**
	 * Cancel a shipment.
	 *
	 * @param string $tracking_number Tracking number.
	 * @return bool|WP_Error True on success or error.
	 */
	public function cancel_shipment( $tracking_number );

	/**
	 * Get shipping label.
	 *
	 * @param string $tracking_number Tracking number.
	 * @param string $format          Label format (pdf, zpl).
	 * @return string|WP_Error Label data or error.
	 */
	public function get_label( $tracking_number, $format = 'pdf' );

	/**
	 * Track a shipment.
	 *
	 * @param string $tracking_number Tracking number.
	 * @return array|WP_Error Tracking events or error.
	 */
	public function track_shipment( $tracking_number );

	/**
	 * Validate API credentials.
	 *
	 * @return bool|WP_Error True if valid or error.
	 */
	public function validate_credentials();

	/**
	 * Get carrier identifier.
	 *
	 * @return string Carrier ID (e.g., 'speedy', 'econt').
	 */
	public function get_carrier_id();

	/**
	 * Get carrier display name.
	 *
	 * @return string Carrier name.
	 */
	public function get_carrier_name();
}
