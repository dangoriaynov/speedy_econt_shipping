<?php
/**
 * Admin Orders class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin Orders class.
 *
 * Handles order list columns, meta boxes, and order-related UI.
 */
class SESH_Admin_Orders {

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
	 * Constructor.
	 *
	 * @param SESH_Settings      $settings      Settings instance.
	 * @param SESH_Label_Manager $label_manager Label manager instance.
	 */
	public function __construct( $settings, $label_manager ) {
		$this->settings      = $settings;
		$this->label_manager = $label_manager;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Add custom columns to orders list.
		add_filter( 'manage_shop_order_posts_columns', array( $this, 'add_orders_list_column' ) );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_orders_list_column' ) );

		// Populate custom columns.
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_orders_list_column' ), 10, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_hpos_orders_list_column' ), 10, 2 );

		// Add meta boxes.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );

		// Enqueue scripts for order pages.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add bulk actions.
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_bulk_actions' ) );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_action_admin_notice' ) );
	}

	/**
	 * Add tracking column to orders list.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_orders_list_column( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			// Add tracking column after status.
			if ( 'order_status' === $key ) {
				$new_columns['sesh_tracking'] = __( 'Tracking', 'speedy_econt_shipping' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render tracking column for legacy orders.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function render_orders_list_column( $column, $post_id ) {
		if ( 'sesh_tracking' !== $column ) {
			return;
		}

		$order = wc_get_order( $post_id );
		if ( ! $order ) {
			return;
		}

		$this->render_tracking_column_content( $order );
	}

	/**
	 * Render tracking column for HPOS orders.
	 *
	 * @param string   $column Column name.
	 * @param WC_Order $order  Order object.
	 */
	public function render_hpos_orders_list_column( $column, $order ) {
		if ( 'sesh_tracking' !== $column ) {
			return;
		}

		$this->render_tracking_column_content( $order );
	}

	/**
	 * Render tracking column content.
	 *
	 * @param WC_Order $order Order object.
	 */
	private function render_tracking_column_content( $order ) {
		$label = $this->label_manager->get_latest_label( $order->get_id() );

		if ( ! $label || empty( $label->tracking_number ) ) {
			echo '<span class="sesh-no-tracking">&mdash;</span>';
			return;
		}

		// Display tracking number with copy button.
		printf(
			'<div class="sesh-tracking-cell">
				<span class="sesh-tracking-number" data-tracking="%s">%s</span>
				<button type="button" class="sesh-copy-tracking button button-small" data-tracking="%s" title="%s">
					<span class="dashicons dashicons-admin-page"></span>
				</button>
			</div>',
			esc_attr( $label->tracking_number ),
			esc_html( $label->tracking_number ),
			esc_attr( $label->tracking_number ),
			esc_attr__( 'Copy tracking number', 'speedy_econt_shipping' )
		);
	}

	/**
	 * Add meta boxes to order edit page.
	 *
	 * @param string           $post_type     Post type.
	 * @param WP_Post|WC_Order $post_or_order Post or order object.
	 */
	public function add_meta_boxes( $post_type, $post_or_order ) {
		$screen = $this->get_order_screen_id();

		if ( ! in_array( $post_type, array( 'shop_order', $screen ), true ) ) {
			return;
		}

		// Get order object.
		$order = $post_or_order instanceof WC_Order
			? $post_or_order
			: wc_get_order( $post_or_order->ID );

		if ( ! $order ) {
			return;
		}

		// Only show meta boxes if order uses our shipping methods.
		if ( ! $this->order_uses_sesh_shipping( $order ) ) {
			return;
		}

		// Delivery details meta box (side, high priority) - shows customer's delivery selection.
		add_meta_box(
			'sesh_delivery_details',
			__( 'Delivery Details', 'speedy_econt_shipping' ),
			array( $this, 'render_delivery_details_meta_box' ),
			$post_type,
			'side',
			'high'
		);

		// Shipping label meta box (side, high priority).
		add_meta_box(
			'sesh_shipping_label',
			__( 'Shipping Label', 'speedy_econt_shipping' ),
			array( $this, 'render_label_meta_box' ),
			$post_type,
			'side',
			'high'
		);

		// Tracking information meta box (normal, default priority).
		$label = $this->label_manager->get_latest_label( $order->get_id() );
		if ( $label && ! empty( $label->tracking_number ) ) {
			add_meta_box(
				'sesh_tracking_info',
				__( 'Tracking Information', 'speedy_econt_shipping' ),
				array( $this, 'render_tracking_meta_box' ),
				$post_type,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Render delivery details meta box.
	 *
	 * Shows the carrier, city, and office information captured during checkout.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_Post|WC_Order $post_or_order Post or order object.
	 */
	public function render_delivery_details_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order
			? $post_or_order
			: wc_get_order( $post_or_order->ID );

		if ( ! $order ) {
			return;
		}

		// Get delivery details from order meta.
		$carrier          = $order->get_meta( '_sesh_carrier' );
		$delivery_type    = $order->get_meta( '_sesh_delivery_type' );
		$city_name        = $order->get_meta( '_sesh_city_name' );
		$city_id          = $order->get_meta( '_sesh_city_id' );
		$office_name      = $order->get_meta( '_sesh_office_name' );
		$office_id        = $order->get_meta( '_sesh_office_id' );
		$office_address   = $order->get_meta( '_sesh_office_address' );
		$calculated_price = $order->get_meta( '_sesh_calculated_shipping_price' );

		// Check if any delivery data exists.
		if ( empty( $carrier ) ) {
			?>
			<div class="sesh-delivery-details sesh-delivery-details--warning">
				<p class="sesh-warning-message">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'No shipping details captured. This order may have been placed before the checkout enhancement update, or via a method that bypassed the checkout form.', 'speedy_econt_shipping' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'You may need to manually configure the delivery details before generating a shipping label.', 'speedy_econt_shipping' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		// Get carrier label.
		$carrier_labels = array(
			'speedy'  => __( 'Speedy', 'speedy_econt_shipping' ),
			'econt'   => __( 'Econt', 'speedy_econt_shipping' ),
			'address' => __( 'Address Delivery', 'speedy_econt_shipping' ),
		);
		$carrier_label  = isset( $carrier_labels[ $carrier ] ) ? $carrier_labels[ $carrier ] : ucfirst( $carrier );

		// Get delivery type label.
		$delivery_type_label = 'office' === $delivery_type
			? __( 'Office Pickup', 'speedy_econt_shipping' )
			: __( 'Address Delivery', 'speedy_econt_shipping' );
		?>
		<div class="sesh-delivery-details">
			<div class="sesh-detail-row">
				<span class="sesh-detail-label"><?php esc_html_e( 'Carrier:', 'speedy_econt_shipping' ); ?></span>
				<span class="sesh-detail-value sesh-carrier-badge sesh-carrier-badge--<?php echo esc_attr( $carrier ); ?>">
					<?php echo esc_html( $carrier_label ); ?>
				</span>
			</div>

			<div class="sesh-detail-row">
				<span class="sesh-detail-label"><?php esc_html_e( 'Delivery Type:', 'speedy_econt_shipping' ); ?></span>
				<span class="sesh-detail-value"><?php echo esc_html( $delivery_type_label ); ?></span>
			</div>

			<?php if ( $city_name ) : ?>
			<div class="sesh-detail-row">
				<span class="sesh-detail-label"><?php esc_html_e( 'City:', 'speedy_econt_shipping' ); ?></span>
				<span class="sesh-detail-value">
					<?php echo esc_html( $city_name ); ?>
					<?php if ( $city_id ) : ?>
						<span class="sesh-detail-id">(ID: <?php echo esc_html( $city_id ); ?>)</span>
					<?php endif; ?>
				</span>
			</div>
			<?php endif; ?>

			<?php if ( 'office' === $delivery_type && $office_name ) : ?>
			<div class="sesh-detail-row">
				<span class="sesh-detail-label"><?php esc_html_e( 'Office:', 'speedy_econt_shipping' ); ?></span>
				<span class="sesh-detail-value">
					<?php echo esc_html( $office_name ); ?>
					<?php if ( $office_id ) : ?>
						<span class="sesh-detail-id">(ID: <?php echo esc_html( $office_id ); ?>)</span>
					<?php endif; ?>
				</span>
			</div>

			<?php if ( $office_address ) : ?>
			<div class="sesh-detail-row">
				<span class="sesh-detail-label"><?php esc_html_e( 'Office Address:', 'speedy_econt_shipping' ); ?></span>
				<span class="sesh-detail-value"><?php echo esc_html( $office_address ); ?></span>
			</div>
			<?php endif; ?>
			<?php endif; ?>

			<?php if ( $calculated_price ) : ?>
			<div class="sesh-detail-row sesh-detail-row--price">
				<span class="sesh-detail-label"><?php esc_html_e( 'Calculated Price:', 'speedy_econt_shipping' ); ?></span>
				<span class="sesh-detail-value"><?php echo wp_kses_post( wc_price( $calculated_price ) ); ?></span>
			</div>
			<?php endif; ?>

			<?php
			// Add "Generate Label" action button if no label exists yet.
			$label = $this->label_manager->get_latest_label( $order->get_id() );
			if ( ! $label || empty( $label->tracking_number ) ) :
				// Validate order for label generation.
				$generator  = new SESH_Label_Generator(
					$this->settings,
					SESH_Plugin::instance()->get_database(),
					SESH_Plugin::instance()->get_speedy_api(),
					SESH_Plugin::instance()->get_econt_api()
				);
				$validation = $generator->validate_order( $order );
				?>
				<div class="sesh-detail-row sesh-detail-row--actions">
					<button
						type="button"
						class="button button-primary button-small sesh-generate-label"
						data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
						<?php disabled( ! $validation->is_valid() ); ?>
						title="<?php echo esc_attr( $validation->is_valid() ? __( 'Generate shipping label', 'speedy_econt_shipping' ) : __( 'Fix validation errors first', 'speedy_econt_shipping' ) ); ?>"
					>
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Generate Label', 'speedy_econt_shipping' ); ?>
					</button>
				</div>
			<?php else : ?>
				<!-- Label exists - show tracking number -->
				<div class="sesh-detail-row sesh-detail-row--tracking">
					<span class="sesh-detail-label"><?php esc_html_e( 'Tracking:', 'speedy_econt_shipping' ); ?></span>
					<span class="sesh-detail-value">
						<strong><?php echo esc_html( $label->tracking_number ); ?></strong>
						<span class="sesh-label-status sesh-status-<?php echo esc_attr( $label->status ); ?>">
							<?php echo esc_html( ucfirst( $label->status ) ); ?>
						</span>
					</span>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render shipping label meta box.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post or order object.
	 */
	public function render_label_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order
			? $post_or_order
			: wc_get_order( $post_or_order->ID );

		if ( ! $order ) {
			return;
		}

		// Get label data.
		$label = $this->label_manager->get_latest_label( $order->get_id() );

		// Load template.
		include SESH_PLUGIN_PATH . 'templates/admin/label-meta-box.php';
	}

	/**
	 * Render tracking information meta box.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post or order object.
	 */
	public function render_tracking_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order
			? $post_or_order
			: wc_get_order( $post_or_order->ID );

		if ( ! $order ) {
			return;
		}

		// Get label and tracking data.
		$label         = $this->label_manager->get_latest_label( $order->get_id() );
		$tracking_info = null;

		if ( $label ) {
			// Try to get cached tracking info.
			$tracking_info = get_transient( 'sesh_tracking_' . $label->id );
		}

		// Load template.
		include SESH_PLUGIN_PATH . 'templates/admin/tracking-meta-box.php';
	}

	/**
	 * Enqueue scripts for order pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on order edit and list pages.
		$allowed_pages = array(
			'post.php',
			'edit.php',
			'woocommerce_page_wc-orders',
		);

		if ( ! in_array( $hook, $allowed_pages, true ) ) {
			return;
		}

		// Check if we're on an order page.
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders', 'edit-shop_order' ), true ) ) {
			return;
		}

		// Enqueue styles.
		wp_enqueue_style(
			'sesh-admin-orders',
			SESH_PLUGIN_URL . 'assets/css/sesh-admin-orders.css',
			array(),
			SESH_VERSION
		);

		// Enqueue script.
		wp_enqueue_script(
			'sesh-admin-orders',
			SESH_PLUGIN_URL . 'assets/js/sesh-admin-orders.js',
			array( 'jquery' ),
			SESH_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'sesh-admin-orders',
			'seshAdminOrders',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonces'   => array(
					'generate_label'  => wp_create_nonce( 'sesh_generate_label' ),
					'download_label'  => wp_create_nonce( 'sesh_download_label' ),
					'print_label'     => wp_create_nonce( 'sesh_print_label' ),
					'cancel_label'    => wp_create_nonce( 'sesh_cancel_label' ),
					'refresh_tracking' => wp_create_nonce( 'sesh_refresh_tracking' ),
					'bulk_print'      => wp_create_nonce( 'sesh_bulk_print_labels' ),
				),
				'i18n'     => array(
					'confirm_cancel'       => __( 'Are you sure you want to cancel this shipping label? This action cannot be undone.', 'speedy_econt_shipping' ),
					'copy_success'         => __( 'Tracking number copied to clipboard!', 'speedy_econt_shipping' ),
					'copy_error'           => __( 'Failed to copy tracking number.', 'speedy_econt_shipping' ),
					'generating_label'     => __( 'Generating label...', 'speedy_econt_shipping' ),
					'cancelling_label'     => __( 'Cancelling label...', 'speedy_econt_shipping' ),
					'refreshing_tracking'  => __( 'Refreshing tracking...', 'speedy_econt_shipping' ),
					'error_occurred'       => __( 'An error occurred. Please try again.', 'speedy_econt_shipping' ),
				),
			)
		);
	}

	/**
	 * Check if order uses SESH shipping method.
	 *
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	private function order_uses_sesh_shipping( $order ) {
		$shipping_methods = $order->get_shipping_methods();

		if ( empty( $shipping_methods ) ) {
			return false;
		}

		foreach ( $shipping_methods as $shipping_method ) {
			$method_id = $shipping_method->get_method_id();
			if ( in_array( $method_id, array( 'sesh_speedy', 'sesh_econt' ), true ) ) {
				return true;
			}
		}

		return false;
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
	 * Add bulk actions to orders list.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public function add_bulk_actions( $actions ) {
		$actions['sesh_generate_labels'] = __( 'Generate shipping labels (SESH)', 'speedy_econt_shipping' );
		return $actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action      Bulk action name.
	 * @param array  $post_ids    Selected post/order IDs.
	 * @return string Modified redirect URL.
	 */
	public function handle_bulk_actions( $redirect_to, $action, $post_ids ) {
		if ( 'sesh_generate_labels' !== $action ) {
			return $redirect_to;
		}

		if ( empty( $post_ids ) ) {
			return $redirect_to;
		}

		$generator = new SESH_Label_Generator(
			$this->settings,
			SESH_Plugin::instance()->get_database(),
			SESH_Plugin::instance()->get_speedy_api(),
			SESH_Plugin::instance()->get_econt_api()
		);

		$processed = 0;
		$errors    = 0;

		foreach ( $post_ids as $post_id ) {
			$order = wc_get_order( $post_id );

			if ( ! $order ) {
				$errors++;
				continue;
			}

			// Skip if not using SESH shipping.
			if ( ! $this->order_uses_sesh_shipping( $order ) ) {
				continue;
			}

			// Skip if label already exists.
			$existing_label = $this->label_manager->get_latest_label( $order->get_id() );
			if ( $existing_label && ! empty( $existing_label->tracking_number ) ) {
				continue;
			}

			// Generate label.
			$result = $generator->generate_label( $order->get_id() );

			if ( $result->is_success() ) {
				$processed++;
			} else {
				$errors++;
			}
		}

		// Add query args for admin notice.
		$redirect_to = add_query_arg(
			array(
				'sesh_bulk_generated' => $processed,
				'sesh_bulk_errors'    => $errors,
			),
			$redirect_to
		);

		return $redirect_to;
	}

	/**
	 * Display admin notice after bulk label generation.
	 */
	public function bulk_action_admin_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_REQUEST['sesh_bulk_generated'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$generated = isset( $_REQUEST['sesh_bulk_generated'] ) ? absint( $_REQUEST['sesh_bulk_generated'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$errors = isset( $_REQUEST['sesh_bulk_errors'] ) ? absint( $_REQUEST['sesh_bulk_errors'] ) : 0;

		if ( $generated > 0 ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of labels generated */
						_n(
							'%d shipping label generated successfully.',
							'%d shipping labels generated successfully.',
							$generated,
							'speedy_econt_shipping'
						),
						$generated
					)
				)
			);
		}

		if ( $errors > 0 ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of errors */
						_n(
							'%d order could not be processed. Check order details for validation errors.',
							'%d orders could not be processed. Check order details for validation errors.',
							$errors,
							'speedy_econt_shipping'
						),
						$errors
					)
				)
			);
		}
	}
}
