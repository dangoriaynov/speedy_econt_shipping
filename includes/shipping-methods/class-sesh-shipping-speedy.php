<?php
/**
 * Speedy shipping method.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Speedy office delivery shipping method.
 */
class SESH_Shipping_Speedy extends SESH_Shipping_Method {

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'sesh_speedy';
		$this->method_title       = __( 'Speedy Office', 'speedy_econt_shipping' );
		$this->method_description = __( 'Delivery to Speedy courier office.', 'speedy_econt_shipping' );

		parent::__construct( $instance_id );

		// Set API client from plugin instance.
		$plugin = SESH_Plugin::instance();
		if ( $plugin && $plugin->get_speedy_api() ) {
			$this->set_api_client( $plugin->get_speedy_api() );
		}
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
			return $this->settings->get_speedy_free_from();
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
			return $this->settings->get_speedy_shipping();
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

		// Check if Speedy is enabled in settings.
		if ( $this->settings && ! $this->settings->is_speedy_enabled() ) {
			return false;
		}

		// Check if we have API credentials.
		if ( $this->settings ) {
			$username = $this->settings->get_speedy_username();
			if ( empty( $username ) ) {
				return false;
			}
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

		$offices = $this->api_client->get_offices( array( 'site_id' => $city_id ) );

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
			return new WP_Error( 'no_api_client', __( 'Speedy API client not configured.', 'speedy_econt_shipping' ) );
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
			'weight'      => $this->get_order_weight( $order ),
			'description' => $this->get_order_contents_description( $order ),
		);
	}

	/**
	 * Get total weight of order items.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return float Weight in kg.
	 */
	protected function get_order_weight( $order ) {
		$weight = 0;

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product && $product->has_weight() ) {
				$item_weight = wc_get_weight( (float) $product->get_weight(), 'kg' );
				$weight     += $item_weight * $item->get_quantity();
			}
		}

		return max( $weight, 0.5 ); // Minimum 0.5 kg.
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

		// Add Speedy-specific fields.
		$this->instance_form_fields['service_id'] = array(
			'title'       => __( 'Default Service', 'speedy_econt_shipping' ),
			'type'        => 'select',
			'description' => __( 'Select the default Speedy service to use.', 'speedy_econt_shipping' ),
			'default'     => '',
			'options'     => $this->get_service_options(),
			'desc_tip'    => true,
		);
	}

	/**
	 * Get available service options.
	 *
	 * @return array
	 */
	private function get_service_options() {
		// Default options - can be expanded with API call.
		return array(
			''    => __( 'Auto-select', 'speedy_econt_shipping' ),
			'505' => __( 'Standard 24h', 'speedy_econt_shipping' ),
		);
	}

	/**
	 * Format calculation parameters for Speedy API.
	 *
	 * @param array $data Common calculation data.
	 * @return array Speedy-formatted parameters.
	 */
	protected function format_calculation_params( $data ) {
		$params = array(
			'mode'        => 'calculate',
			'sender'      => $this->get_sender_params(),
			'recipient'   => $this->get_recipient_params( $data ),
			'service'     => $this->get_service_params(),
			'content'     => $this->get_content_params( $data ),
			'payment'     => array(
				'courierServicePayer' => 'SENDER',
			),
		);

		return $params;
	}

	/**
	 * Get sender parameters for API calculation.
	 *
	 * @return array Sender data.
	 */
	private function get_sender_params() {
		// Use site/office from settings or default.
		// This should be configured in plugin settings (sender office).
		return array(
			'siteId' => 68134, // Sofia (default - should come from settings).
		);
	}

	/**
	 * Get recipient parameters for API calculation.
	 *
	 * @param array $data Calculation data.
	 * @return array Recipient data.
	 */
	private function get_recipient_params( $data ) {
		$params = array();

		// Office delivery.
		if ( ! empty( $data['office_id'] ) ) {
			$params['officeId'] = (int) $data['office_id'];
		} elseif ( ! empty( $data['city_id'] ) ) {
			$params['siteId'] = (int) $data['city_id'];
		}

		return $params;
	}

	/**
	 * Get service parameters for API calculation.
	 *
	 * @return array Service configuration.
	 */
	private function get_service_params() {
		// Get configured service ID or use default.
		$service_id = $this->get_option( 'service_id', '505' );

		return array(
			'serviceId' => ! empty( $service_id ) ? (int) $service_id : 505,
		);
	}

	/**
	 * Get content parameters for API calculation.
	 *
	 * @param array $data Calculation data.
	 * @return array Content data.
	 */
	private function get_content_params( $data ) {
		return array(
			'parcelsCount' => 1,
			'totalWeight'  => $data['weight'],
		);
	}

	/**
	 * Check if dynamic pricing is enabled for Speedy.
	 *
	 * @return bool
	 */
	protected function is_dynamic_pricing_enabled() {
		if ( $this->settings ) {
			return $this->settings->is_speedy_dynamic_pricing();
		}
		return false;
	}
}
