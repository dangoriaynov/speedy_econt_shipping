<?php
/**
 * Econt shipping method.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Econt office delivery shipping method.
 */
class SESH_Shipping_Econt extends SESH_Shipping_Method {

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'sesh_econt';
		$this->method_title       = __( 'Econt Office', 'speedy_econt_shipping' );
		$this->method_description = __( 'Delivery to Econt courier office.', 'speedy_econt_shipping' );

		parent::__construct( $instance_id );

		// Set API client from plugin instance.
		$plugin = SESH_Plugin::instance();
		if ( $plugin && $plugin->get_econt_api() ) {
			$this->set_api_client( $plugin->get_econt_api() );
		}
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
	 * Get delivery type.
	 *
	 * @return string
	 */
	public function get_delivery_type() {
		return 'office';
	}

	/**
	 * Get free shipping threshold.
	 *
	 * @return float
	 */
	protected function get_free_shipping_threshold() {
		// First check instance setting.
		$threshold = $this->get_option( 'free_from' );
		if ( '' !== $threshold ) {
			return (float) $threshold;
		}

		// Fall back to global settings.
		if ( $this->settings ) {
			return $this->settings->get_econt_free_from();
		}

		return -1;
	}

	/**
	 * Get fallback shipping rate.
	 *
	 * @return float
	 */
	protected function get_fallback_rate() {
		if ( $this->settings ) {
			return $this->settings->get_econt_shipping();
		}
		return 0;
	}

	/**
	 * Check if method is available.
	 *
	 * @param array $package Shipping package.
	 * @return bool
	 */
	public function is_available( $package ) {
		if ( ! parent::is_available( $package ) ) {
			return false;
		}

		// Check if Econt is enabled in settings.
		if ( $this->settings && ! $this->settings->is_econt_enabled() ) {
			return false;
		}

		return true;
	}

	/**
	 * Get offices for a city.
	 *
	 * @param int $city_id City ID.
	 * @return array
	 */
	public function get_offices( $city_id ) {
		if ( ! $this->api_client ) {
			return array();
		}

		$offices = $this->api_client->get_offices( array( 'city_id' => $city_id ) );

		if ( is_wp_error( $offices ) ) {
			return array();
		}

		return $offices;
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields() {
		parent::init_form_fields();

		// Add Econt-specific fields.
		$this->instance_form_fields['service_type'] = array(
			'title'       => __( 'Default Service Type', 'speedy_econt_shipping' ),
			'type'        => 'select',
			'description' => __( 'Select the default Econt service type.', 'speedy_econt_shipping' ),
			'default'     => 'courier_standard',
			'options'     => array(
				'courier_standard' => __( 'Standard Courier', 'speedy_econt_shipping' ),
			),
			'desc_tip'    => true,
		);
	}
}
