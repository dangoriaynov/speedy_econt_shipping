<?php
/**
 * Label manager class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Label manager class.
 *
 * Manages shipping labels - retrieval, download, cancellation.
 */
class SESH_Label_Manager {

	/**
	 * Database instance.
	 *
	 * @var SESH_Database
	 */
	private $database;

	/**
	 * Speedy API client.
	 *
	 * @var SESH_Speedy_API|null
	 */
	private $speedy_api;

	/**
	 * Econt API client.
	 *
	 * @var SESH_Econt_API|null
	 */
	private $econt_api;

	/**
	 * Constructor.
	 *
	 * @param SESH_Database        $database   Database instance.
	 * @param SESH_Speedy_API|null $speedy_api Speedy API client.
	 * @param SESH_Econt_API|null  $econt_api  Econt API client.
	 */
	public function __construct( $database, $speedy_api = null, $econt_api = null ) {
		$this->database   = $database;
		$this->speedy_api = $speedy_api;
		$this->econt_api  = $econt_api;
	}

	/**
	 * Get label by ID.
	 *
	 * @param int $label_id Label ID.
	 * @return object|null Label object or null.
	 */
	public function get_label( $label_id ) {
		return $this->database->get_label( $label_id );
	}

	/**
	 * Get labels for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array Array of label objects.
	 */
	public function get_labels_for_order( $order_id ) {
		return $this->database->get_labels_by_order( $order_id );
	}

	/**
	 * Get label PDF data.
	 *
	 * @param int $label_id Label ID.
	 * @return string|WP_Error PDF binary data or error.
	 */
	public function get_label_pdf( $label_id ) {
		$label = $this->get_label( $label_id );

		if ( ! $label ) {
			return new WP_Error( 'label_not_found', __( 'Label not found.', 'speedy_econt_shipping' ) );
		}

		// Return cached PDF if available.
		if ( ! empty( $label->label_data ) ) {
			return $label->label_data;
		}

		// Fetch from API if not cached.
		if ( empty( $label->tracking_number ) ) {
			return new WP_Error( 'no_tracking_number', __( 'Tracking number not available.', 'speedy_econt_shipping' ) );
		}

		try {
			$api_client = $this->get_api_client( $label->carrier );
			if ( ! $api_client ) {
				return new WP_Error( 'no_api_client', __( 'API client not available.', 'speedy_econt_shipping' ) );
			}

			$pdf_data = $api_client->get_label( $label->tracking_number );

			// Cache the PDF for future requests.
			$this->database->update_label(
				$label_id,
				array(
					'label_data' => $pdf_data,
					'status'     => 'generated',
				)
			);

			return $pdf_data;

		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage() );
		}
	}

	/**
	 * Download label PDF.
	 *
	 * @param int $label_id Label ID.
	 */
	public function download_label( $label_id ) {
		$pdf_data = $this->get_label_pdf( $label_id );

		if ( is_wp_error( $pdf_data ) ) {
			wp_die( esc_html( $pdf_data->get_error_message() ) );
		}

		$label = $this->get_label( $label_id );

		// Set headers for PDF download.
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="label-' . $label->tracking_number . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf_data ) );
		header( 'Cache-Control: private, max-age=0, must-revalidate' );
		header( 'Pragma: public' );

		// Output PDF.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $pdf_data;

		// Mark as printed.
		$this->database->update_label_status( $label_id, 'printed' );

		exit;
	}

	/**
	 * Cancel a label.
	 *
	 * @param int $label_id Label ID.
	 * @return bool|WP_Error True on success or error.
	 */
	public function cancel_label( $label_id ) {
		$label = $this->get_label( $label_id );

		if ( ! $label ) {
			return new WP_Error( 'label_not_found', __( 'Label not found.', 'speedy_econt_shipping' ) );
		}

		if ( 'cancelled' === $label->status ) {
			return new WP_Error( 'already_cancelled', __( 'Label already cancelled.', 'speedy_econt_shipping' ) );
		}

		try {
			$api_client = $this->get_api_client( $label->carrier );
			if ( ! $api_client ) {
				return new WP_Error( 'no_api_client', __( 'API client not available.', 'speedy_econt_shipping' ) );
			}

			$result = $api_client->cancel_shipment( $label->tracking_number );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Update label status.
			$this->database->update_label_status( $label_id, 'cancelled' );

			// Add order note.
			$order = wc_get_order( $label->order_id );
			if ( $order ) {
				$order->add_order_note(
					sprintf(
						/* translators: %s: tracking number */
						__( 'Shipping label cancelled. Tracking: %s', 'speedy_econt_shipping' ),
						$label->tracking_number
					)
				);
			}

			return true;

		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage() );
		}
	}

	/**
	 * Print label (output to browser).
	 *
	 * @param int $label_id Label ID.
	 */
	public function print_label( $label_id ) {
		$pdf_data = $this->get_label_pdf( $label_id );

		if ( is_wp_error( $pdf_data ) ) {
			wp_die( esc_html( $pdf_data->get_error_message() ) );
		}

		$label = $this->get_label( $label_id );

		// Set headers for inline PDF display.
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="label-' . $label->tracking_number . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf_data ) );
		header( 'Cache-Control: private, max-age=0, must-revalidate' );
		header( 'Pragma: public' );

		// Output PDF.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $pdf_data;

		// Mark as printed.
		$this->database->update_label_status( $label_id, 'printed' );

		exit;
	}

	/**
	 * Get tracking info for a label.
	 *
	 * @param int $label_id Label ID.
	 * @return array|WP_Error Tracking events or error.
	 */
	public function get_tracking_info( $label_id ) {
		$label = $this->get_label( $label_id );

		if ( ! $label ) {
			return new WP_Error( 'label_not_found', __( 'Label not found.', 'speedy_econt_shipping' ) );
		}

		try {
			$api_client = $this->get_api_client( $label->carrier );
			if ( ! $api_client ) {
				return new WP_Error( 'no_api_client', __( 'API client not available.', 'speedy_econt_shipping' ) );
			}

			return $api_client->track_shipment( $label->tracking_number );

		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage() );
		}
	}

	/**
	 * Get API client for carrier.
	 *
	 * @param string $carrier Carrier name.
	 * @return SESH_API_Client_Interface|null
	 */
	private function get_api_client( $carrier ) {
		if ( 'speedy' === $carrier ) {
			return $this->speedy_api;
		} elseif ( 'econt' === $carrier ) {
			return $this->econt_api;
		}

		return null;
	}

	/**
	 * Check if order has a label.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function has_label( $order_id ) {
		$labels = $this->get_labels_for_order( $order_id );
		return ! empty( $labels );
	}

	/**
	 * Get latest label for order.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null
	 */
	public function get_latest_label( $order_id ) {
		$labels = $this->get_labels_for_order( $order_id );
		return ! empty( $labels ) ? $labels[0] : null;
	}

	/**
	 * Bulk generate labels for multiple orders.
	 *
	 * @param array                $order_ids Order IDs.
	 * @param SESH_Label_Generator $generator Label generator instance.
	 * @return array Results keyed by order ID.
	 */
	public function bulk_generate_labels( $order_ids, $generator ) {
		$results = array();

		foreach ( $order_ids as $order_id ) {
			$results[ $order_id ] = $generator->generate_label( $order_id );
		}

		return $results;
	}
}
