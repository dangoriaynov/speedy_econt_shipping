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
}
