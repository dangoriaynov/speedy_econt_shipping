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
	 * Generate shipping label for an order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array|WP_Error Label data or error.
	 */
	public function generate_label( $order_id ) {
		if ( ! $this->api_client ) {
			return new WP_Error( 'no_api_client', __( 'Econt API client not configured.', 'speedy_econt_shipping' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Order not found.', 'speedy_econt_shipping' ) );
		}

		// Build shipment data from order - to be fully implemented in Phase 4.
		$shipment_data = $this->build_shipment_data( $order );

		return $this->api_client->create_shipment( $shipment_data );
	}

	/**
	 * Build shipment data from WooCommerce order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array Shipment data for API.
	 */
	protected function build_shipment_data( $order ) {
		// Placeholder - will be fully implemented in Phase 4.
		return array(
			'order_id'    => $order->get_id(),
			'recipient'   => array(
				'name'    => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
				'phone'   => $order->get_billing_phone(),
				'email'   => $order->get_billing_email(),
				'address' => $order->get_shipping_address_1(),
				'city'    => $order->get_shipping_city(),
			),
			'cod_amount'  => $order->get_payment_method() === 'cod' ? $order->get_total() : 0,
			'description' => $this->get_order_contents_description( $order ),
		);
	}

	/**
	 * Get order contents description.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string
	 */
	protected function get_order_contents_description( $order ) {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			$items[] = $item->get_name() . ' x ' . $item->get_quantity();
		}

		return implode( ', ', $items );
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

	/**
	 * Format calculation parameters for Econt API.
	 *
	 * @param array $data Common calculation data.
	 * @return array Econt-formatted parameters.
	 */
	protected function format_calculation_params( $data ) {
		$package = isset( $data['package'] ) ? $data['package'] : array();

		// Econt requires sender and receiver city info.
		$params = array(
			'senderCity'    => 'София', // Default sender city - should come from settings.
			'receiverCity'  => $this->get_receiver_city_name( $data ),
			'weight'        => $data['weight'],
			'shipmentType'  => 'PACK', // Package type.
			'deliveryType'  => 'office', // Office delivery.
		);

		// Add office code if available.
		if ( ! empty( $data['office_id'] ) ) {
			$params['officeCode'] = $data['office_id'];
		}

		// Add COD amount if payment method is COD.
		if ( WC()->cart && WC()->cart->needs_payment() ) {
			$payment_method = WC()->session ? WC()->session->get( 'chosen_payment_method' ) : '';
			if ( 'cod' === $payment_method ) {
				$params['cdAmount'] = WC()->cart->get_total( 'raw' );
			}
		}

		return $params;
	}

	/**
	 * Get receiver city name from data.
	 *
	 * @param array $data Calculation data.
	 * @return string City name.
	 */
	private function get_receiver_city_name( $data ) {
		// Try to get city name from session or package.
		if ( WC()->session ) {
			$city_name = WC()->session->get( 'econt_city_name' );
			if ( ! empty( $city_name ) ) {
				return sanitize_text_field( $city_name );
			}
		}

		// Fallback to package destination city.
		$package = isset( $data['package'] ) ? $data['package'] : array();
		if ( isset( $package['destination']['city'] ) ) {
			return sanitize_text_field( $package['destination']['city'] );
		}

		return '';
	}

	/**
	 * Check if dynamic pricing is enabled for Econt.
	 *
	 * @return bool
	 */
	protected function is_dynamic_pricing_enabled() {
		if ( $this->settings ) {
			// Check if Econt has dynamic pricing setting.
			$use_dynamic = $this->settings->get( 'econt', 'use_dynamic_pricing', false );
			return (bool) $use_dynamic;
		}
		return false;
	}
}
