<?php
/**
 * Label generator class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Label generator class.
 *
 * Handles automatic and manual shipping label generation.
 */
class SESH_Label_Generator {

	/**
	 * Settings instance.
	 *
	 * @var SESH_Settings
	 */
	private $settings;

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
	 * @param SESH_Settings        $settings   Settings instance.
	 * @param SESH_Database        $database   Database instance.
	 * @param SESH_Speedy_API|null $speedy_api Speedy API client.
	 * @param SESH_Econt_API|null  $econt_api  Econt API client.
	 */
	public function __construct( $settings, $database, $speedy_api = null, $econt_api = null ) {
		$this->settings   = $settings;
		$this->database   = $database;
		$this->speedy_api = $speedy_api;
		$this->econt_api  = $econt_api;
	}

	/**
	 * Generate label for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return SESH_Label_Result
	 */
	public function generate_label( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new SESH_Label_Result(
				false,
				null,
				'',
				__( 'Order not found.', 'speedy_econt_shipping' )
			);
		}

		// Validate order.
		$validation = $this->validate_order( $order );
		if ( ! $validation->is_valid() ) {
			return new SESH_Label_Result(
				false,
				null,
				'',
				$validation->get_errors_string( ' ' )
			);
		}

		// Determine carrier from shipping method.
		$carrier = $this->get_order_carrier( $order );
		if ( ! $carrier ) {
			return new SESH_Label_Result(
				false,
				null,
				'',
				__( 'Could not determine shipping carrier from order.', 'speedy_econt_shipping' )
			);
		}

		// Build shipment parameters.
		$params = $this->build_shipment_params( $order, $carrier );

		// Create shipment via API.
		try {
			$response = $this->create_shipment( $carrier, $params );

			if ( is_wp_error( $response ) ) {
				$this->log_error( $order, 'API Error: ' . $response->get_error_message() );
				return new SESH_Label_Result(
					false,
					null,
					'',
					$response->get_error_message()
				);
			}

			// Store label in database.
			$label_id = $this->store_label( $order, $carrier, $response );

			if ( ! $label_id ) {
				return new SESH_Label_Result(
					false,
					null,
					'',
					__( 'Failed to store label in database.', 'speedy_econt_shipping' )
				);
			}

			// Add order note.
			$order->add_order_note(
				sprintf(
					/* translators: 1: carrier name, 2: tracking number */
					__( 'Shipping label generated via %1$s. Tracking number: %2$s', 'speedy_econt_shipping' ),
					ucfirst( $carrier ),
					$response->get_tracking_number()
				)
			);

			// Store tracking number in order meta.
			$order->update_meta_data( '_sesh_tracking_number', $response->get_tracking_number() );
			$order->update_meta_data( '_sesh_carrier', $carrier );
			$order->update_meta_data( '_sesh_label_id', $label_id );
			$order->save();

			return new SESH_Label_Result(
				true,
				$label_id,
				$response->get_tracking_number(),
				'',
				$response->get_raw_response()
			);

		} catch ( Exception $e ) {
			$this->log_error( $order, 'Exception: ' . $e->getMessage() );
			return new SESH_Label_Result(
				false,
				null,
				'',
				$e->getMessage()
			);
		}
	}

	/**
	 * Validate order for label generation.
	 *
	 * @param WC_Order $order Order object.
	 * @return SESH_Validation_Result
	 */
	public function validate_order( $order ) {
		$errors = array();

		// Check shipping address.
		if ( empty( $order->get_shipping_city() ) ) {
			$errors[] = __( 'Shipping city is required.', 'speedy_econt_shipping' );
		}

		if ( empty( $order->get_shipping_first_name() ) && empty( $order->get_shipping_last_name() ) ) {
			$errors[] = __( 'Recipient name is required.', 'speedy_econt_shipping' );
		}

		// Check for delivery details meta.
		$delivery_type = $order->get_meta( '_sesh_delivery_type' );
		if ( empty( $delivery_type ) ) {
			$errors[] = __( 'Delivery type not set. Customer must complete checkout with delivery details.', 'speedy_econt_shipping' );
		}

		// Validate office delivery.
		if ( 'office' === $delivery_type ) {
			$office_id = $order->get_meta( '_sesh_office_id' );
			if ( empty( $office_id ) ) {
				$errors[] = __( 'Office ID is required for office delivery.', 'speedy_econt_shipping' );
			}
		}

		// Check product weights.
		$items           = $order->get_items();
		$missing_weights = array();

		foreach ( $items as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$weight = $product->get_weight();
			if ( empty( $weight ) ) {
				$missing_weights[] = $product->get_name();
			}
		}

		if ( ! empty( $missing_weights ) ) {
			$errors[] = sprintf(
				/* translators: %s: comma-separated product names */
				__( 'Missing product weights for: %s', 'speedy_econt_shipping' ),
				implode( ', ', $missing_weights )
			);
		}

		// Check sender address configuration.
		$sender_name = $this->settings->get_sender_name();
		if ( empty( $sender_name ) ) {
			$errors[] = __( 'Sender address not configured. Please configure sender details in plugin settings.', 'speedy_econt_shipping' );
		}

		$sender_city = $this->settings->get_sender_city();
		if ( empty( $sender_city ) ) {
			$errors[] = __( 'Sender city not configured.', 'speedy_econt_shipping' );
		}

		$sender_phone = $this->settings->get_sender_phone();
		if ( empty( $sender_phone ) ) {
			$errors[] = __( 'Sender phone not configured.', 'speedy_econt_shipping' );
		}

		$valid = empty( $errors );
		return new SESH_Validation_Result( $valid, $errors );
	}

	/**
	 * Build shipment parameters for API request.
	 *
	 * @param WC_Order $order   Order object.
	 * @param string   $carrier Carrier (speedy or econt).
	 * @return array
	 */
	public function build_shipment_params( $order, $carrier ) {
		$sender_params = $this->settings->get_sender_params();

		$params = array(
			'sender'    => array(
				'contactName' => $sender_params['name'],
				'phone'       => $sender_params['phone'],
				'email'       => $sender_params['email'],
				'city'        => $sender_params['city'],
				'address'     => $sender_params['address'],
				'postCode'    => $sender_params['postcode'],
			),
			'recipient' => $this->get_recipient_params( $order ),
			'service'   => $this->get_service_params( $order, $carrier ),
			'content'   => $this->get_content_params( $order ),
			'payment'   => $this->get_payment_params( $order ),
		);

		/**
		 * Filter shipment parameters before API request.
		 *
		 * @param array    $params  Shipment parameters.
		 * @param WC_Order $order   Order object.
		 * @param string   $carrier Carrier name.
		 */
		return apply_filters( 'sesh_shipment_params', $params, $order, $carrier );
	}

	/**
	 * Get recipient parameters from order.
	 *
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	private function get_recipient_params( $order ) {
		$recipient = array(
			'contactName' => trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ),
			'phone'       => $order->get_billing_phone(),
		);

		$delivery_type = $order->get_meta( '_sesh_delivery_type' );

		if ( 'office' === $delivery_type ) {
			$recipient['officeId'] = $order->get_meta( '_sesh_office_id' );
		} else {
			$recipient['city']    = $order->get_shipping_city();
			$recipient['address'] = $order->get_shipping_address_1();
			if ( $order->get_shipping_address_2() ) {
				$recipient['address'] .= ', ' . $order->get_shipping_address_2();
			}
			$recipient['postCode'] = $order->get_shipping_postcode();
		}

		return $recipient;
	}

	/**
	 * Get service parameters.
	 *
	 * @param WC_Order $order   Order object.
	 * @param string   $carrier Carrier name.
	 * @return array
	 */
	private function get_service_params( $order, $carrier ) {
		$delivery_type = $order->get_meta( '_sesh_delivery_type' );

		$params = array(
			'deliveryType' => 'office' === $delivery_type ? 'OFFICE' : 'ADDRESS',
		);

		// Add carrier-specific service IDs.
		if ( 'speedy' === $carrier ) {
			$service_id = $order->get_meta( '_sesh_speedy_service_id' );
			if ( empty( $service_id ) ) {
				// Fallback to default service.
				$service_id = $this->settings->get( 'speedy', 'default_service_id', '505' );
			}
			$params['serviceId'] = $service_id;
		} elseif ( 'econt' === $carrier ) {
			$service_type = $order->get_meta( '_sesh_econt_service_type' );
			if ( empty( $service_type ) ) {
				$service_type = $this->settings->get( 'econt', 'default_service_type', 'courier_standard' );
			}
			$params['serviceType'] = $service_type;
		}

		return $params;
	}

	/**
	 * Get content (parcels) parameters.
	 *
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	private function get_content_params( $order ) {
		$items        = $order->get_items();
		$total_weight = 0;
		$descriptions = array();

		foreach ( $items as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$quantity = $item->get_quantity();
			$weight   = $product->get_weight();

			if ( $weight ) {
				$total_weight += (float) $weight * $quantity;
			}

			$descriptions[] = $product->get_name() . ' x' . $quantity;
		}

		// Default to 1kg if no weight specified.
		if ( empty( $total_weight ) ) {
			$total_weight = 1.0;
		}

		return array(
			'parcelsCount' => 1,
			'totalWeight'  => $total_weight,
			'contents'     => implode( ', ', array_slice( $descriptions, 0, 3 ) ),
		);
	}

	/**
	 * Get payment parameters.
	 *
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	private function get_payment_params( $order ) {
		$payment_method = $order->get_payment_method();

		// COD (Cash on Delivery) handling.
		$is_cod = in_array( $payment_method, array( 'cod', 'cash_on_delivery' ), true );

		$params = array(
			'paymentType' => $is_cod ? 'RECIPIENT' : 'SENDER',
		);

		if ( $is_cod ) {
			$params['codAmount'] = $order->get_total();
		}

		return $params;
	}

	/**
	 * Get carrier from order shipping method.
	 *
	 * @param WC_Order $order Order object.
	 * @return string|null Carrier (speedy or econt) or null.
	 */
	private function get_order_carrier( $order ) {
		$shipping_methods = $order->get_shipping_methods();

		if ( empty( $shipping_methods ) ) {
			return null;
		}

		$shipping_method = reset( $shipping_methods );
		$method_id       = $shipping_method->get_method_id();

		if ( 'sesh_speedy' === $method_id ) {
			return 'speedy';
		} elseif ( 'sesh_econt' === $method_id ) {
			return 'econt';
		}

		return null;
	}

	/**
	 * Create shipment via carrier API.
	 *
	 * @param string $carrier Carrier name.
	 * @param array  $params  Shipment parameters.
	 * @return SESH_Shipment_Response|WP_Error
	 */
	private function create_shipment( $carrier, $params ) {
		if ( 'speedy' === $carrier && $this->speedy_api ) {
			return $this->speedy_api->create_shipment( $params );
		} elseif ( 'econt' === $carrier && $this->econt_api ) {
			return $this->econt_api->create_shipment( $params );
		}

		return new WP_Error(
			'no_api_client',
			sprintf(
				/* translators: %s: carrier name */
				__( 'API client not available for %s', 'speedy_econt_shipping' ),
				$carrier
			)
		);
	}

	/**
	 * Store label in database.
	 *
	 * @param WC_Order              $order    Order object.
	 * @param string                $carrier  Carrier name.
	 * @param SESH_Shipment_Response $response API response.
	 * @return int|false Label ID or false on failure.
	 */
	private function store_label( $order, $carrier, $response ) {
		// Get label PDF data.
		$label_data = null;
		try {
			$api_client = 'speedy' === $carrier ? $this->speedy_api : $this->econt_api;
			if ( $api_client ) {
				$label_data = $api_client->get_label( $response->get_tracking_number() );
			}
		} catch ( Exception $e ) {
			// Log but continue - we can fetch label later.
			$this->log_error( $order, 'Failed to fetch label PDF: ' . $e->getMessage() );
		}

		$label_id = $this->database->insert_label(
			array(
				'order_id'        => $order->get_id(),
				'carrier'         => $carrier,
				'tracking_number' => $response->get_tracking_number(),
				'label_data'      => $label_data,
				'label_format'    => 'pdf',
				'status'          => 'generated',
				'api_response'    => $response->get_raw_response(),
			)
		);

		return $label_id;
	}

	/**
	 * Log error to order notes and debug log.
	 *
	 * @param WC_Order $order   Order object.
	 * @param string   $message Error message.
	 */
	private function log_error( $order, $message ) {
		$order->add_order_note(
			sprintf(
				/* translators: %s: error message */
				__( 'Label generation failed: %s', 'speedy_econt_shipping' ),
				$message
			)
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'SESH Label Generation Error (Order #' . $order->get_id() . '): ' . $message );
		}
	}
}
