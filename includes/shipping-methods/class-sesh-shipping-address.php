<?php
/**
 * Address delivery shipping method.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Address/home delivery shipping method.
 *
 * This shipping method handles delivery directly to customer's address
 * rather than to a courier office pickup point.
 */
class SESH_Shipping_Address extends WC_Shipping_Method {

	/**
	 * Settings instance.
	 *
	 * @var SESH_Settings|null
	 */
	protected $settings = null;

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'sesh_address';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Address Delivery', 'speedy_econt_shipping' );
		$this->method_description = __( 'Delivery to customer address.', 'speedy_econt_shipping' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		$this->init();
	}

	/**
	 * Initialize.
	 */
	protected function init() {
		$this->init_form_fields();
		$this->init_settings();

		// Get settings from plugin settings.
		$plugin = SESH_Plugin::instance();
		if ( $plugin ) {
			$this->settings = $plugin->get_settings();
		}

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Initialize form fields.
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
				'default'     => $this->get_default_title(),
				'desc_tip'    => true,
			),
			'cost'       => array(
				'title'       => __( 'Cost', 'speedy_econt_shipping' ),
				'type'        => 'price',
				'placeholder' => '',
				'description' => __( 'Fixed cost for address delivery.', 'speedy_econt_shipping' ),
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
	 * Get default title from settings.
	 *
	 * @return string
	 */
	private function get_default_title() {
		if ( $this->settings ) {
			return $this->settings->get_address_label();
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

	/**
	 * Check if method is available.
	 *
	 * @param array $package Shipping package.
	 * @return bool
	 */
	public function is_available( $package ) {
		$is_available = $this->is_enabled();

		// Check if address delivery is enabled in settings.
		if ( $this->settings && ! $this->settings->is_address_enabled() ) {
			$is_available = false;
		}

		return apply_filters( 'sesh_shipping_address_is_available', $is_available, $package, $this );
	}

	/**
	 * Calculate shipping.
	 *
	 * @param array $package Shipping package.
	 */
	public function calculate_shipping( $package = array() ) {
		$cost = $this->get_shipping_cost( $package );

		$rate = array(
			'id'        => $this->get_rate_id(),
			'label'     => $this->get_title(),
			'cost'      => $cost,
			'package'   => $package,
			'meta_data' => array(
				'carrier'       => 'address',
				'delivery_type' => 'address',
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
	 * Get shipping cost.
	 *
	 * @param array $package Shipping package.
	 * @return float
	 */
	protected function get_shipping_cost( $package ) {
		// Check for free shipping.
		if ( $this->is_free_shipping( $package ) ) {
			return 0;
		}

		// Use configured cost.
		$cost = $this->get_option( 'cost' );
		if ( '' !== $cost ) {
			return (float) $cost;
		}

		// Fall back to global settings.
		if ( $this->settings ) {
			return $this->settings->get_address_shipping();
		}

		return 0;
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

		$cart_total = isset( $package['contents_cost'] )
			? (float) $package['contents_cost']
			: ( WC()->cart ? (float) WC()->cart->get_subtotal() : 0 );

		return $cart_total >= $threshold;
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
		if ( $this->settings ) {
			return $this->settings->get_address_free_from();
		}

		return -1;
	}

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
}
