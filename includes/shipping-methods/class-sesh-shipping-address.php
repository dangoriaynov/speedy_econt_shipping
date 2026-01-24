<?php
/**
 * Address delivery shipping method.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Address/home delivery shipping method via Speedy.
 *
 * This shipping method handles delivery directly to customer's address
 * using the Speedy courier service. Address delivery in Bulgaria is
 * performed exclusively through Speedy.
 */
class SESH_Shipping_Address extends SESH_Shipping_Method {

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'sesh_address';
		$this->method_title       = __( 'Address Delivery (Speedy)', 'speedy_econt_shipping' );
		$this->method_description = __( 'Delivery to customer address via Speedy courier.', 'speedy_econt_shipping' );

		parent::__construct( $instance_id );

		// Set Speedy API client - address delivery uses Speedy.
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
		return 'speedy'; // Address delivery uses Speedy courier.
	}

	/**
	 * Get delivery type.
	 *
	 * @return string
	 */
	public function get_delivery_type() {
		return 'address';
	}

	/**
	 * Get free shipping threshold.
	 *
	 * @return float Returns -1 if free shipping is disabled.
	 */
	protected function get_free_shipping_threshold() {
		// First check instance setting.
		$threshold = $this->get_option( 'free_from' );
		if ( '' !== $threshold ) {
			return (float) $threshold;
		}

		// Fall back to global settings.
		if ( $this->plugin_settings ) {
			return $this->plugin_settings->get_address_free_from();
		}

		return -1;
	}

	/**
	 * Get fallback shipping rate.
	 *
	 * @return float
	 */
	protected function get_fallback_rate() {
		if ( $this->plugin_settings ) {
			return $this->plugin_settings->get_address_shipping();
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

		// Check if address delivery is enabled in settings.
		if ( $this->plugin_settings && ! $this->plugin_settings->is_address_enabled() ) {
			return false;
		}

		// Address delivery requires Speedy to be enabled and configured.
		if ( $this->plugin_settings ) {
			if ( ! $this->plugin_settings->is_speedy_enabled() ) {
				return false;
			}

			$username = $this->plugin_settings->get_speedy_username();
			if ( empty( $username ) ) {
				return false;
			}
		}

		return apply_filters( 'sesh_shipping_address_is_available', true, $package, $this );
	}

	/**
	 * Get offices for a city.
	 *
	 * Address delivery doesn't use offices, but method is required by abstract class.
	 *
	 * @param int $city_id City ID.
	 * @return array Always returns empty array for address delivery.
	 */
	public function get_offices( $city_id ) {
		// Address delivery doesn't use offices.
		return array();
	}

	/**
	 * Generate shipping label for an order.
	 *
	 * Uses Speedy API for label generation.
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

		// Build shipment data for address delivery.
		$shipment_data = $this->build_shipment_data( $order );

		return $this->api_client->create_shipment( $shipment_data );
	}

	/**
	 * Build shipment data from WooCommerce order for address delivery.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array Shipment data for API.
	 */
	protected function build_shipment_data( $order ) {
		return array(
			'order_id'      => $order->get_id(),
			'delivery_type' => 'address', // Indicates address delivery, not office.
			'recipient'     => array(
				'name'     => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
				'phone'    => $order->get_billing_phone(),
				'email'    => $order->get_billing_email(),
				'address'  => $order->get_shipping_address_1(),
				'city'     => $order->get_shipping_city(),
				'postcode' => $order->get_shipping_postcode(),
				'country'  => $order->get_shipping_country(),
			),
			'cod_amount'    => $order->get_payment_method() === 'cod' ? $order->get_total() : 0,
			'weight'        => $this->get_order_weight( $order ),
			'description'   => $this->get_order_contents_description( $order ),
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

		// Update title default to use settings.
		$this->instance_form_fields['title']['default'] = $this->get_default_title();
	}

	/**
	 * Get default title from settings.
	 *
	 * @return string
	 */
	private function get_default_title() {
		if ( $this->plugin_settings ) {
			return $this->plugin_settings->get_address_label();
		}
		return __( 'Address Delivery', 'speedy_econt_shipping' );
	}

	/**
	 * Get the title.
	 *
	 * @return string
	 */
	public function get_title() {
		$title = $this->get_option( 'title', $this->get_default_title() );
		return apply_filters( 'woocommerce_shipping_method_title', $title, $this );
	}
}
