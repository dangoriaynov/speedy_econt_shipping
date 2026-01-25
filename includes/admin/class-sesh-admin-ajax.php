<?php
/**
 * Admin AJAX handlers class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin AJAX class.
 *
 * Handles all AJAX requests for admin order operations.
 */
class SESH_Admin_AJAX {

	/**
	 * Settings instance.
	 *
	 * @var SESH_Settings
	 */
	private $settings;

	/**
	 * Label manager instance.
	 *
	 * @var SESH_Label_Manager
	 */
	private $label_manager;

	/**
	 * Label generator instance.
	 *
	 * @var SESH_Label_Generator
	 */
	private $label_generator;

	/**
	 * Constructor.
	 *
	 * @param SESH_Settings        $settings        Settings instance.
	 * @param SESH_Label_Manager   $label_manager   Label manager instance.
	 * @param SESH_Label_Generator $label_generator Label generator instance.
	 */
	public function __construct( $settings, $label_manager, $label_generator ) {
		$this->settings        = $settings;
		$this->label_manager   = $label_manager;
		$this->label_generator = $label_generator;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Label generation.
		add_action( 'wp_ajax_sesh_generate_label', array( $this, 'ajax_generate_label' ) );

		// Label download.
		add_action( 'wp_ajax_sesh_download_label', array( $this, 'ajax_download_label' ) );

		// Label printing.
		add_action( 'wp_ajax_sesh_print_label', array( $this, 'ajax_print_label' ) );
		add_action( 'wp_ajax_sesh_bulk_print_labels', array( $this, 'ajax_bulk_print_labels' ) );

		// Label cancellation.
		add_action( 'wp_ajax_sesh_cancel_label', array( $this, 'ajax_cancel_label' ) );

		// Tracking refresh.
		add_action( 'wp_ajax_sesh_refresh_tracking', array( $this, 'ajax_refresh_tracking' ) );
	}

	/**
	 * AJAX handler for label generation.
	 */
	public function ajax_generate_label() {
		check_ajax_referer( 'sesh_generate_label', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions.', 'speedy_econt_shipping' ),
				)
			);
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid order ID.', 'speedy_econt_shipping' ),
				)
			);
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error(
				array(
					'message' => __( 'Order not found.', 'speedy_econt_shipping' ),
				)
			);
		}

		// Generate label.
		$result = $this->label_generator->generate_label( $order_id );

		if ( ! $result->is_success() ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error(),
				)
			);
		}

		// Add order note.
		$order->add_order_note(
			sprintf(
				/* translators: %s: tracking number */
				__( 'Shipping label generated successfully. Tracking: %s', 'speedy_econt_shipping' ),
				$result->get_tracking_number()
			)
		);

		wp_send_json_success(
			array(
				'message'         => __( 'Label generated successfully!', 'speedy_econt_shipping' ),
				'tracking_number' => $result->get_tracking_number(),
				'label_id'        => $result->get_label_id(),
			)
		);
	}

	/**
	 * AJAX handler for label download.
	 */
	public function ajax_download_label() {
		check_ajax_referer( 'sesh_download_label', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'speedy_econt_shipping' ) );
		}

		$label_id = isset( $_GET['label_id'] ) ? absint( $_GET['label_id'] ) : 0;

		if ( ! $label_id ) {
			wp_die( esc_html__( 'Invalid label ID.', 'speedy_econt_shipping' ) );
		}

		// Use label manager to handle download.
		$this->label_manager->download_label( $label_id );
	}

	/**
	 * AJAX handler for label printing.
	 */
	public function ajax_print_label() {
		check_ajax_referer( 'sesh_print_label', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'speedy_econt_shipping' ) );
		}

		$label_id = isset( $_GET['label_id'] ) ? absint( $_GET['label_id'] ) : 0;

		if ( ! $label_id ) {
			wp_die( esc_html__( 'Invalid label ID.', 'speedy_econt_shipping' ) );
		}

		// Use label manager to handle printing.
		$this->label_manager->print_label( $label_id );
	}

	/**
	 * AJAX handler for bulk label printing.
	 */
	public function ajax_bulk_print_labels() {
		check_ajax_referer( 'sesh_bulk_print_labels', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'speedy_econt_shipping' ) );
		}

		// Accept both POST array and GET comma-separated string.
		$label_ids = array();
		if ( isset( $_POST['label_ids'] ) && is_array( $_POST['label_ids'] ) ) {
			$label_ids = array_map( 'absint', $_POST['label_ids'] );
		} elseif ( isset( $_GET['label_ids'] ) && is_string( $_GET['label_ids'] ) ) {
			$label_ids = array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['label_ids'] ) ) ) );
		}

		if ( empty( $label_ids ) ) {
			wp_die( esc_html__( 'No labels selected.', 'speedy_econt_shipping' ) );
		}

		// Combine PDFs.
		$combined_pdf = $this->combine_label_pdfs( $label_ids );

		if ( is_wp_error( $combined_pdf ) ) {
			wp_die( esc_html( $combined_pdf->get_error_message() ) );
		}

		// Set headers for PDF output.
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="labels-' . gmdate( 'Y-m-d-His' ) . '.pdf"' );
		header( 'Content-Length: ' . strlen( $combined_pdf ) );
		header( 'Cache-Control: private, max-age=0, must-revalidate' );
		header( 'Pragma: public' );

		// Output combined PDF.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $combined_pdf;

		exit;
	}

	/**
	 * AJAX handler for label cancellation.
	 */
	public function ajax_cancel_label() {
		check_ajax_referer( 'sesh_cancel_label', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions.', 'speedy_econt_shipping' ),
				)
			);
		}

		$label_id = isset( $_POST['label_id'] ) ? absint( $_POST['label_id'] ) : 0;

		if ( ! $label_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid label ID.', 'speedy_econt_shipping' ),
				)
			);
		}

		// Cancel label.
		$result = $this->label_manager->cancel_label( $label_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Label cancelled successfully!', 'speedy_econt_shipping' ),
			)
		);
	}

	/**
	 * AJAX handler for tracking refresh.
	 */
	public function ajax_refresh_tracking() {
		check_ajax_referer( 'sesh_refresh_tracking', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions.', 'speedy_econt_shipping' ),
				)
			);
		}

		$label_id = isset( $_POST['label_id'] ) ? absint( $_POST['label_id'] ) : 0;

		if ( ! $label_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid label ID.', 'speedy_econt_shipping' ),
				)
			);
		}

		// Get tracking info.
		$tracking_info = $this->label_manager->get_tracking_info( $label_id );

		if ( is_wp_error( $tracking_info ) ) {
			wp_send_json_error(
				array(
					'message' => $tracking_info->get_error_message(),
				)
			);
		}

		// Cache tracking info for 30 minutes.
		set_transient( 'sesh_tracking_' . $label_id, $tracking_info, 30 * MINUTE_IN_SECONDS );

		// Format tracking events for display.
		$formatted_events = $this->format_tracking_events( $tracking_info );

		wp_send_json_success(
			array(
				'message'    => __( 'Tracking information updated!', 'speedy_econt_shipping' ),
				'events'     => $formatted_events,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Combine multiple label PDFs into one.
	 *
	 * @param array $label_ids Array of label IDs.
	 * @return string|WP_Error Combined PDF data or error.
	 */
	private function combine_label_pdfs( $label_ids ) {
		$pdf_data = array();

		foreach ( $label_ids as $label_id ) {
			$label_pdf = $this->label_manager->get_label_pdf( $label_id );

			if ( is_wp_error( $label_pdf ) ) {
				return $label_pdf;
			}

			$pdf_data[] = $label_pdf;
		}

		// For now, just concatenate PDFs.
		// TODO: Use a PDF library to properly merge PDFs if needed.
		return implode( '', $pdf_data );
	}

	/**
	 * Format tracking events for display.
	 *
	 * @param array $tracking_info Raw tracking info from API.
	 * @return array Formatted events.
	 */
	private function format_tracking_events( $tracking_info ) {
		if ( empty( $tracking_info ) || ! is_array( $tracking_info ) ) {
			return array();
		}

		$formatted = array();

		// Handle different API response structures.
		$events = isset( $tracking_info['events'] ) ? $tracking_info['events'] : $tracking_info;

		foreach ( $events as $event ) {
			$formatted[] = array(
				'date'        => isset( $event['date'] ) ? $event['date'] : '',
				'status'      => isset( $event['status'] ) ? $event['status'] : '',
				'description' => isset( $event['description'] ) ? $event['description'] : '',
				'location'    => isset( $event['location'] ) ? $event['location'] : '',
			);
		}

		return $formatted;
	}
}
