<?php
/**
 * Abstract shipping method class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Abstract base class for all shipping methods.
 *
 * Extends WC_Shipping_Method to integrate with WooCommerce
 * shipping zones and rate calculation.
 */
abstract class SESH_Shipping_Method extends WC_Shipping_Method {

	/**
	 * API client instance.
	 *
	 * @var SESH_API_Client_Interface|null
	 */
	protected $api_client = null;

	/**
	 * Settings instance.
	 *
	 * @var SESH_Settings|null
	 */
	protected $settings = null;

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Shipping method instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->instance_id = absint( $instance_id );
		$this->supports    = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		$this->init();
	}

	/**
	 * Initialize the shipping method.
	 */
	protected function init() {
		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Get settings from plugin settings.
		$plugin = SESH_Plugin::instance();
		if ( $plugin ) {
			$this->settings = $plugin->get_settings();
		}

		// Actions.
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Initialize form fields for the shipping method settings.
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'enabled'    => array(
				'title'   => __( 'Enable/Disable', 'speedy_econt_shipping' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this shipping method', 'speedy_econt_shipping' ),
				'default' => 'yes',
			),
			'title'      => array(
				'title'       => __( 'Method Title', 'speedy_econt_shipping' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'speedy_econt_shipping' ),
				'default'     => $this->method_title,
				'desc_tip'    => true,
			),
			'cost'       => array(
				'title'       => __( 'Cost', 'speedy_econt_shipping' ),
				'type'        => 'price',
				'placeholder' => '',
				'description' => __( 'Enter a cost (excl. tax) or leave blank to use API pricing.', 'speedy_econt_shipping' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'free_from'  => array(
				'title'       => __( 'Free Shipping Threshold', 'speedy_econt_shipping' ),
				'type'        => 'price',
				'placeholder' => '',
				'description' => __( 'Minimum order amount for free shipping. Leave blank to disable.', 'speedy_econt_shipping' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'tax_status' => array(
				'title'   => __( 'Tax Status', 'speedy_econt_shipping' ),
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'default' => 'taxable',
				'options' => array(
					'taxable' => __( 'Taxable', 'speedy_econt_shipping' ),
					'none'    => __( 'None', 'speedy_econt_shipping' ),
				),
			),
		);
	}

	/**
	 * Get the title for the shipping method.
	 *
	 * @return string
	 */
	public function get_title() {
		$title = $this->get_option( 'title', $this->method_title );
		return apply_filters( 'woocommerce_shipping_method_title', $title, $this );
	}

	/**
	 * Check if the shipping method is available.
	 *
	 * @param array $package Shipping package.
	 * @return bool
	 */
	public function is_available( $package ) {
		$is_available = $this->is_enabled();

		/**
		 * Filter whether the shipping method is available.
		 *
		 * @since 2.0.0
		 * @param bool                 $is_available Whether available.
		 * @param array                $package      Shipping package.
		 * @param SESH_Shipping_Method $method       Shipping method instance.
		 */
		return apply_filters( 'sesh_shipping_method_is_available', $is_available, $package, $this );
	}

	/**
	 * Calculate shipping rates.
	 *
	 * @param array $package Shipping package.
	 */
	public function calculate_shipping( $package = array() ) {
		$cost = $this->get_shipping_cost( $package );

		if ( false === $cost ) {
			return; // Unable to calculate.
		}

		$rate = array(
			'id'        => $this->get_rate_id(),
			'label'     => $this->get_title(),
			'cost'      => $cost,
			'package'   => $package,
			'meta_data' => array(
				'carrier'       => $this->get_carrier_id(),
				'delivery_type' => $this->get_delivery_type(),
			),
		);

		// Add free shipping label if applicable.
		if ( 0 == $cost ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			$suffix = $this->get_free_shipping_suffix();
			if ( ! empty( $suffix ) ) {
				$rate['label'] .= ' (' . $suffix . ')';
			}
		}

		$this->add_rate( $rate );
	}

	/**
	 * Get shipping cost for a package.
	 *
	 * @param array $package Shipping package.
	 * @return float|false Cost or false if unable to calculate.
	 */
	protected function get_shipping_cost( $package ) {
		// Check for free shipping first.
		if ( $this->is_free_shipping( $package ) ) {
			return 0;
		}

		// Use configured flat rate if set.
		$flat_cost = $this->get_option( 'cost' );
		if ( ! empty( $flat_cost ) ) {
			return (float) $flat_cost;
		}

		// Try API pricing (to be implemented in Phase 2).
		$api_cost = $this->get_api_shipping_cost( $package );
		if ( false !== $api_cost ) {
			return $api_cost;
		}

		// Fallback to settings-based rate.
		return $this->get_fallback_rate();
	}

	/**
	 * Check if order qualifies for free shipping.
	 *
	 * @param array $package Shipping package.
	 * @return bool
	 */
	protected function is_free_shipping( $package ) {
		$threshold = $this->get_free_shipping_threshold();

		if ( $threshold <= 0 ) {
			return false;
		}

		$cart_total = $this->get_cart_total( $package );

		return $cart_total >= $threshold;
	}

	/**
	 * Get cart total from package.
	 *
	 * @param array $package Shipping package.
	 * @return float
	 */
	protected function get_cart_total( $package ) {
		if ( isset( $package['contents_cost'] ) ) {
			return (float) $package['contents_cost'];
		}

		// Fallback to WooCommerce cart.
		if ( WC()->cart ) {
			return (float) WC()->cart->get_subtotal();
		}

		return 0;
	}

	/**
	 * Get package weight.
	 *
	 * @param array $package Shipping package.
	 * @return float Weight in kg.
	 */
	protected function get_package_weight( $package ) {
		$weight = 0;

		if ( isset( $package['contents'] ) ) {
			foreach ( $package['contents'] as $item ) {
				$product = $item['data'];
				if ( $product && $product->has_weight() ) {
					$item_weight = wc_get_weight( (float) $product->get_weight(), 'kg' );
					$weight     += $item_weight * $item['quantity'];
				}
			}
		}

		// Apply minimum weight.
		return max( $weight, 0.5 );
	}

	/**
	 * Get API-based shipping cost.
	 *
	 * This will be implemented fully in Phase 2.
	 *
	 * @param array $package Shipping package.
	 * @return float|false Cost or false if not available.
	 */
	protected function get_api_shipping_cost( $package ) {
		// Placeholder - to be implemented in Phase 2.
		return false;
	}

	/**
	 * Get free shipping threshold.
	 *
	 * @return float Returns -1 if free shipping is disabled.
	 */
	abstract protected function get_free_shipping_threshold();

	/**
	 * Get fallback shipping rate.
	 *
	 * @return float
	 */
	abstract protected function get_fallback_rate();

	/**
	 * Get carrier identifier.
	 *
	 * @return string
	 */
	abstract public function get_carrier_id();

	/**
	 * Get delivery type (office, address).
	 *
	 * @return string
	 */
	abstract public function get_delivery_type();

	/**
	 * Get offices for a city.
	 *
	 * @param int $city_id City ID.
	 * @return array
	 */
	abstract public function get_offices( $city_id );

	/**
	 * Get free shipping suffix text.
	 *
	 * @return string
	 */
	protected function get_free_shipping_suffix() {
		if ( $this->settings ) {
			return $this->settings->get_free_shipping_label_suffix();
		}
		return __( 'for free', 'speedy_econt_shipping' );
	}

	/**
	 * Set the API client.
	 *
	 * @param SESH_API_Client_Interface $client API client.
	 */
	public function set_api_client( SESH_API_Client_Interface $client ) {
		$this->api_client = $client;
	}

	/**
	 * Get the API client.
	 *
	 * @return SESH_API_Client_Interface|null
	 */
	public function get_api_client() {
		return $this->api_client;
	}
}
