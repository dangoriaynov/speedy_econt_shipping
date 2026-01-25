<?php
/**
 * Customer tracking class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Customer tracking class.
 *
 * Handles customer-facing tracking information display on:
 * - My Account order details page
 * - Order confirmation emails
 * - Customer order emails
 */
class SESH_Customer_Tracking {

	/**
	 * Database instance.
	 *
	 * @var SESH_Database
	 */
	private $database;

	/**
	 * Label manager instance.
	 *
	 * @var SESH_Label_Manager
	 */
	private $label_manager;

	/**
	 * Cache duration for tracking info (30 minutes).
	 *
	 * @var int
	 */
	const CACHE_DURATION = 1800;

	/**
	 * Constructor.
	 *
	 * @param SESH_Database      $database      Database instance.
	 * @param SESH_Label_Manager $label_manager Label manager instance.
	 */
	public function __construct( $database, $label_manager ) {
		$this->database      = $database;
		$this->label_manager = $label_manager;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Enqueue frontend scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Display tracking info on My Account order details page.
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_tracking_info' ), 10, 1 );

		// Add tracking info to order emails.
		add_action( 'woocommerce_email_order_meta', array( $this, 'email_tracking_info' ), 10, 4 );

		// AJAX handler for refreshing tracking info.
		add_action( 'wp_ajax_sesh_refresh_tracking', array( $this, 'ajax_refresh_tracking' ) );
		add_action( 'wp_ajax_nopriv_sesh_refresh_tracking', array( $this, 'ajax_refresh_tracking' ) );
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts() {
		// Only load on My Account pages and thank you page.
		if ( ! is_account_page() && ! is_order_received_page() ) {
			return;
		}

		wp_enqueue_style(
			'sesh-customer-tracking',
			SESH_PLUGIN_URL . 'assets/css/sesh-customer-tracking.css',
			array(),
			SESH_VERSION
		);

		wp_enqueue_script(
			'sesh-customer-tracking',
			SESH_PLUGIN_URL . 'assets/js/sesh-customer-tracking.js',
			array( 'jquery' ),
			SESH_VERSION,
			true
		);

		wp_localize_script(
			'sesh-customer-tracking',
			'sesh_tracking_params',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'sesh_tracking_nonce' ),
				'i18n'     => array(
					'refreshing' => __( 'Refreshing...', 'speedy_econt_shipping' ),
					'error'      => __( 'Failed to refresh tracking information.', 'speedy_econt_shipping' ),
				),
			)
		);
	}

	/**
	 * Display tracking information on My Account order details page.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function display_tracking_info( $order ) {
		if ( ! $order ) {
			return;
		}

		// Get labels for this order.
		$labels = $this->label_manager->get_labels_for_order( $order->get_id() );

		if ( empty( $labels ) ) {
			return;
		}

		// Get the latest label.
		$label = reset( $labels );

		// Skip if label is cancelled or pending.
		if ( in_array( $label->status, array( 'cancelled', 'pending' ), true ) ) {
			return;
		}

		// Get tracking info (cached).
		$tracking_info = $this->get_cached_tracking_info( $label->id );

		// Load template.
		$this->load_template(
			'myaccount/tracking.php',
			array(
				'order'         => $order,
				'label'         => $label,
				'tracking_info' => $tracking_info,
			)
		);
	}

	/**
	 * Add tracking info to order emails.
	 *
	 * @param WC_Order $order         Order object.
	 * @param bool     $sent_to_admin Whether sent to admin.
	 * @param bool     $plain_text    Whether plain text email.
	 * @param WC_Email $email         Email object.
	 */
	public function email_tracking_info( $order, $sent_to_admin, $plain_text, $email ) {
		// Only show to customers, not admins.
		if ( $sent_to_admin ) {
			return;
		}

		// Only show on certain email types.
		$allowed_emails = array(
			'customer_completed_order',
			'customer_processing_order',
		);

		if ( ! in_array( $email->id, $allowed_emails, true ) ) {
			return;
		}

		// Get labels for this order.
		$labels = $this->label_manager->get_labels_for_order( $order->get_id() );

		if ( empty( $labels ) ) {
			return;
		}

		// Get the latest label.
		$label = reset( $labels );

		// Skip if label is cancelled or pending.
		if ( in_array( $label->status, array( 'cancelled', 'pending' ), true ) ) {
			return;
		}

		// Determine template based on email type.
		if ( 'customer_completed_order' === $email->id ) {
			// Full tracking info with timeline.
			$tracking_info = $this->get_cached_tracking_info( $label->id );
			$template      = $plain_text ? 'emails/plain/tracking-info.php' : 'emails/tracking-info.php';
		} else {
			// Simple tracking number only for processing emails.
			$tracking_info = null;
			$template      = $plain_text ? 'emails/plain/tracking-info-simple.php' : 'emails/tracking-info-simple.php';
		}

		// Load template.
		$this->load_template(
			$template,
			array(
				'order'         => $order,
				'label'         => $label,
				'tracking_info' => $tracking_info,
			)
		);
	}

	/**
	 * AJAX handler for refreshing tracking info.
	 */
	public function ajax_refresh_tracking() {
		check_ajax_referer( 'sesh_tracking_nonce', 'nonce' );

		$label_id = isset( $_POST['label_id'] ) ? absint( $_POST['label_id'] ) : 0;

		if ( ! $label_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid label ID.', 'speedy_econt_shipping' ) ) );
		}

		// Get label.
		$label = $this->database->get_label( $label_id );

		if ( ! $label ) {
			wp_send_json_error( array( 'message' => __( 'Label not found.', 'speedy_econt_shipping' ) ) );
		}

		// Verify user has access to this order.
		$order = wc_get_order( $label->order_id );
		if ( ! $order || ! current_user_can( 'view_order', $label->order_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'speedy_econt_shipping' ) ) );
		}

		// Clear cached tracking info.
		delete_transient( 'sesh_tracking_' . $label_id );

		// Get fresh tracking info.
		$tracking_info = $this->get_cached_tracking_info( $label_id );

		if ( is_wp_error( $tracking_info ) ) {
			wp_send_json_error(
				array(
					'message' => $tracking_info->get_error_message(),
				)
			);
		}

		wp_send_json_success(
			array(
				'tracking_info' => $tracking_info,
				'updated_time'  => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			)
		);
	}

	/**
	 * Get cached tracking info for a label.
	 *
	 * @param int $label_id Label ID.
	 * @return array|WP_Error|null Tracking info array or null if not available.
	 */
	private function get_cached_tracking_info( $label_id ) {
		// Try to get from cache first.
		$cache_key     = 'sesh_tracking_' . $label_id;
		$tracking_info = get_transient( $cache_key );

		if ( false !== $tracking_info ) {
			return $tracking_info;
		}

		// Fetch fresh tracking info.
		$tracking_info = $this->label_manager->get_tracking_info( $label_id );

		// Cache the result (even if it's an error or null).
		if ( ! is_wp_error( $tracking_info ) && ! empty( $tracking_info ) ) {
			set_transient( $cache_key, $tracking_info, self::CACHE_DURATION );
		}

		return $tracking_info;
	}

	/**
	 * Load template file.
	 *
	 * @param string $template_name Template file name relative to templates dir.
	 * @param array  $args          Template arguments.
	 */
	private function load_template( $template_name, $args = array() ) {
		// Allow themes to override templates.
		$template_path = locate_template(
			array(
				'speedy-econt-shipping/' . $template_name,
				'sesh/' . $template_name,
			)
		);

		// Fall back to plugin template.
		if ( ! $template_path ) {
			$template_path = SESH_PLUGIN_DIR . 'templates/' . $template_name;
		}

		// Load template if it exists.
		if ( file_exists( $template_path ) ) {
			// Extract args to variables.
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args );

			include $template_path;
		}
	}

	/**
	 * Get tracking URL for a label.
	 *
	 * @param object $label Label object.
	 * @return string Tracking URL.
	 */
	public static function get_tracking_url( $label ) {
		if ( ! $label || empty( $label->carrier ) || empty( $label->tracking_number ) ) {
			return '';
		}

		return SESH_Tracking_URLs::get_tracking_url( $label->carrier, $label->tracking_number );
	}
}
