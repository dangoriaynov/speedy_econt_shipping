<?php
/**
 * Admin class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin class.
 *
 * Handles all admin functionality including settings pages,
 * order meta boxes, and admin notices.
 *
 * Note: The legacy admin (SeshSpeedyEcontShippingAdmin) is still active
 * for backward compatibility. This class will gradually take over
 * admin functionality in future updates.
 */
class SESH_Admin {

	/**
	 * Settings instance.
	 *
	 * @var SESH_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param SESH_Settings $settings Settings instance.
	 */
	public function __construct( SESH_Settings $settings ) {
		$this->settings = $settings;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin notices.
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add order meta box (will be expanded in Phase 4).
		add_action( 'add_meta_boxes', array( $this, 'add_order_meta_boxes' ), 10, 2 );
	}

	/**
	 * Display admin notices.
	 */
	public function admin_notices() {
		// Check if Speedy credentials are missing when Speedy is enabled.
		if ( $this->settings->is_speedy_enabled() ) {
			$username = $this->settings->get_speedy_username();
			if ( empty( $username ) ) {
				$this->show_notice(
					sprintf(
						/* translators: %s: settings page URL */
						__( 'Speedy shipping is enabled but API credentials are missing. Please <a href="%s">configure your settings</a>.', 'speedy_econt_shipping' ),
						esc_url( admin_url( 'options-general.php?page=speedy-econt-shipping' ) )
					),
					'warning'
				);
			}
		}
	}

	/**
	 * Show admin notice.
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type (error, warning, success, info).
	 */
	private function show_notice( $message, $type = 'info' ) {
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			wp_kses_post( $message )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on our settings page or WooCommerce order pages.
		$allowed_pages = array(
			'settings_page_speedy-econt-shipping',
			'post.php',
			'edit.php',
			'woocommerce_page_wc-orders',
		);

		if ( ! in_array( $hook, $allowed_pages, true ) ) {
			return;
		}

		// Admin styles (will be added in future phases).
		wp_enqueue_style(
			'sesh-admin',
			SESH_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SESH_VERSION
		);
	}

	/**
	 * Add order meta boxes.
	 *
	 * @param string           $post_type Post type.
	 * @param WP_Post|WC_Order $post_or_order Post or order object.
	 */
	public function add_order_meta_boxes( $post_type, $post_or_order ) {
		// Support both legacy and HPOS order screens.
		$screen = $this->get_order_screen_id();

		if ( in_array( $post_type, array( 'shop_order', $screen ), true ) ) {
			add_meta_box(
				'sesh_shipping_info',
				__( 'Shipping Info', 'speedy_econt_shipping' ),
				array( $this, 'render_shipping_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Get the correct screen ID for orders (HPOS compatible).
	 *
	 * @return string
	 */
	private function get_order_screen_id() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			if ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
				return wc_get_page_screen_id( 'shop-order' );
			}
		}
		return 'shop_order';
	}

	/**
	 * Render shipping info meta box.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post or order object.
	 */
	public function render_shipping_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order
			? $post_or_order
			: wc_get_order( $post_or_order->ID );

		if ( ! $order ) {
			echo '<p>' . esc_html__( 'Order not found.', 'speedy_econt_shipping' ) . '</p>';
			return;
		}

		// Get shipping method info.
		$shipping_methods = $order->get_shipping_methods();

		if ( empty( $shipping_methods ) ) {
			echo '<p>' . esc_html__( 'No shipping method selected.', 'speedy_econt_shipping' ) . '</p>';
			return;
		}

		foreach ( $shipping_methods as $shipping_method ) {
			echo '<p><strong>' . esc_html__( 'Method:', 'speedy_econt_shipping' ) . '</strong> ';
			echo esc_html( $shipping_method->get_method_title() ) . '</p>';
		}

		// Display tracking number if available.
		$tracking_number = $order->get_meta( '_sesh_tracking_number' );
		if ( $tracking_number ) {
			echo '<p><strong>' . esc_html__( 'Tracking:', 'speedy_econt_shipping' ) . '</strong> ';
			echo esc_html( $tracking_number ) . '</p>';
		}

		// Placeholder for label generation button (Phase 4).
		echo '<p class="sesh-meta-box-info">' .
			esc_html__( 'Label generation will be available in a future update.', 'speedy_econt_shipping' ) .
			'</p>';
	}

	/**
	 * Get settings instance.
	 *
	 * @return SESH_Settings
	 */
	public function get_settings() {
		return $this->settings;
	}
}
