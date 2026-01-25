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
 */
class SESH_Admin {

	/**
	 * Settings instance.
	 *
	 * @var SESH_Settings
	 */
	private $settings;

	/**
	 * Admin orders instance.
	 *
	 * @var SESH_Admin_Orders
	 */
	private $admin_orders;

	/**
	 * Admin AJAX instance.
	 *
	 * @var SESH_Admin_AJAX
	 */
	private $admin_ajax;

	/**
	 * Constructor.
	 *
	 * @param SESH_Settings $settings Settings instance.
	 */
	public function __construct( SESH_Settings $settings ) {
		$this->settings = $settings;
		$this->init_hooks();
		$this->init_components();
	}

	/**
	 * Initialize admin components.
	 */
	private function init_components() {
		// Get label manager and generator.
		$label_manager   = $this->get_label_manager();
		$label_generator = $this->get_label_generator();

		// Initialize admin orders UI.
		$this->admin_orders = new SESH_Admin_Orders( $this->settings, $label_manager );

		// Initialize AJAX handlers.
		$this->admin_ajax = new SESH_Admin_AJAX( $this->settings, $label_manager, $label_generator );
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin notices.
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add order meta box.
		add_action( 'add_meta_boxes', array( $this, 'add_order_meta_boxes' ), 10, 2 );

		// Order actions.
		add_filter( 'woocommerce_order_actions', array( $this, 'add_order_actions' ) );
		add_action( 'woocommerce_order_action_sesh_generate_label', array( $this, 'process_generate_label_action' ) );

		// Bulk actions.
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_bulk_actions' ) );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk_generate_labels' ), 10, 3 );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_bulk_generate_labels' ), 10, 3 );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk_print_labels' ), 10, 3 );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_bulk_print_labels' ), 10, 3 );

		// Automatic label generation.
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_auto_generate_label' ), 10, 4 );

		// AJAX handlers for admin.
		add_action( 'wp_ajax_sesh_search_cities', array( $this, 'ajax_search_cities' ) );
		add_action( 'wp_ajax_sesh_download_label', array( $this, 'ajax_download_label' ) );
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
						esc_url( admin_url( 'admin.php?page=wc-settings&tab=sesh_shipping&section=speedy' ) )
					),
					'warning'
				);
			}
		}

		// Check if Econt credentials are missing when Econt is enabled.
		if ( $this->settings->is_econt_enabled() ) {
			$username = $this->settings->get_econt_username();
			if ( empty( $username ) ) {
				$this->show_notice(
					sprintf(
						/* translators: %s: settings page URL */
						__( 'Econt shipping is enabled but API credentials are missing. Please <a href="%s">configure your settings</a>.', 'speedy_econt_shipping' ),
						esc_url( admin_url( 'admin.php?page=wc-settings&tab=sesh_shipping&section=econt' ) )
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
		// Only load on WooCommerce settings or order pages.
		$allowed_pages = array(
			'woocommerce_page_wc-settings',
			'post.php',
			'edit.php',
			'woocommerce_page_wc-orders',
		);

		if ( ! in_array( $hook, $allowed_pages, true ) ) {
			return;
		}

		// Admin styles.
		wp_enqueue_style(
			'sesh-admin',
			SESH_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SESH_VERSION
		);

		// Admin settings script (only on settings page).
		if ( 'woocommerce_page_wc-settings' === $hook ) {
			wp_enqueue_script( 'jquery-ui-autocomplete' );

			wp_enqueue_script(
				'sesh-admin-settings',
				SESH_PLUGIN_URL . 'assets/js/sesh-admin-settings.js',
				array( 'jquery', 'jquery-ui-autocomplete' ),
				SESH_VERSION,
				true
			);

			wp_localize_script(
				'sesh-admin-settings',
				'seshAdminSettings',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'sesh_admin_settings' ),
					'i18n'     => array(
						'invalid_phone' => __( 'Invalid Bulgarian phone number format. Use: 0888123456 or +359888123456', 'speedy_econt_shipping' ),
						'city_required' => __( 'Please select a valid city from the list.', 'speedy_econt_shipping' ),
					),
				)
			);
		}
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

		// Display label information.
		$manager = $this->get_label_manager();
		$labels  = $manager->get_labels_for_order( $order->get_id() );

		if ( ! empty( $labels ) ) {
			echo '<hr>';
			echo '<h4>' . esc_html__( 'Shipping Labels', 'speedy_econt_shipping' ) . '</h4>';

			foreach ( $labels as $label ) {
				echo '<div class="sesh-label-info" style="margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-left: 3px solid #2271b1;">';
				echo '<p style="margin: 5px 0;"><strong>' . esc_html__( 'Carrier:', 'speedy_econt_shipping' ) . '</strong> ' . esc_html( ucfirst( $label->carrier ) ) . '</p>';
				echo '<p style="margin: 5px 0;"><strong>' . esc_html__( 'Tracking:', 'speedy_econt_shipping' ) . '</strong> ' . esc_html( $label->tracking_number ) . '</p>';
				echo '<p style="margin: 5px 0;"><strong>' . esc_html__( 'Status:', 'speedy_econt_shipping' ) . '</strong> ' . esc_html( ucfirst( $label->status ) ) . '</p>';
				echo '<p style="margin: 5px 0;"><strong>' . esc_html__( 'Created:', 'speedy_econt_shipping' ) . '</strong> ' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $label->created_at ) ) ) . '</p>';

				// Action buttons.
				echo '<p style="margin: 10px 0 5px 0;">';
				$download_url = wp_nonce_url(
					admin_url( 'admin-ajax.php?action=sesh_download_label&label_id=' . $label->id ),
					'sesh_download_label',
					'security'
				);
				echo '<a href="' . esc_url( $download_url ) . '" class="button button-small" target="_blank">' . esc_html__( 'Download PDF', 'speedy_econt_shipping' ) . '</a> ';

				if ( 'cancelled' !== $label->status ) {
					echo '<a href="#" class="button button-small sesh-cancel-label" data-label-id="' . esc_attr( $label->id ) . '">' . esc_html__( 'Cancel', 'speedy_econt_shipping' ) . '</a>';
				}
				echo '</p>';
				echo '</div>';
			}
		} else {
			echo '<hr>';
			echo '<p class="sesh-meta-box-info">' .
				esc_html__( 'No shipping label generated yet. Use "Generate shipping label" from Order Actions dropdown.', 'speedy_econt_shipping' ) .
				'</p>';
		}
	}

	/**
	 * AJAX handler for city search.
	 *
	 * Searches both Speedy and Econt city databases.
	 */
	public function ajax_search_cities() {
		check_ajax_referer( 'sesh_admin_settings', 'security' );

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		if ( empty( $search ) || strlen( $search ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'Search term too short.', 'speedy_econt_shipping' ) ) );
		}

		global $wpdb;

		$results = array();

		// Search Speedy sites.
		$speedy_table = $wpdb->prefix . 'speedy_sites';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $speedy_table ) ) === $speedy_table ) {
			$speedy_sites = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT name, region
					FROM {$speedy_table}
					WHERE name LIKE %s
					AND is_prod = 1
					ORDER BY name ASC
					LIMIT 10",
					'%' . $wpdb->esc_like( $search ) . '%'
				)
			);

			foreach ( $speedy_sites as $site ) {
				$results[] = array(
					'label'  => $site->name . ' (' . $site->region . ')',
					'value'  => $site->name,
					'region' => $site->region,
				);
			}
		}

		// Search Econt sites.
		$econt_table = $wpdb->prefix . 'econt_sites';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $econt_table ) ) === $econt_table ) {
			$econt_sites = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT name, region
					FROM {$econt_table}
					WHERE name LIKE %s
					AND is_prod = 1
					ORDER BY name ASC
					LIMIT 10",
					'%' . $wpdb->esc_like( $search ) . '%'
				)
			);

			foreach ( $econt_sites as $site ) {
				// Check if not already in results (avoid duplicates).
				$exists = false;
				foreach ( $results as $result ) {
					if ( $result['value'] === $site->name ) {
						$exists = true;
						break;
					}
				}

				if ( ! $exists ) {
					$results[] = array(
						'label'  => $site->name . ' (' . $site->region . ')',
						'value'  => $site->name,
						'region' => $site->region,
					);
				}
			}
		}

		// Sort results alphabetically.
		usort(
			$results,
			function( $a, $b ) {
				return strcmp( $a['value'], $b['value'] );
			}
		);

		// Limit total results.
		$results = array_slice( $results, 0, 20 );

		wp_send_json_success( $results );
	}

	/**
	 * Get settings instance.
	 *
	 * @return SESH_Settings
	 */
	public function get_settings() {
		return $this->settings;
	}

	// =========================================================================
	// Label Generation Methods
	// =========================================================================

	/**
	 * Add order actions dropdown.
	 *
	 * @param array $actions Existing actions.
	 * @return array
	 */
	public function add_order_actions( $actions ) {
		$actions['sesh_generate_label'] = __( 'Generate shipping label', 'speedy_econt_shipping' );
		return $actions;
	}

	/**
	 * Process generate label order action.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function process_generate_label_action( $order ) {
		$generator = $this->get_label_generator();
		$result    = $generator->generate_label( $order->get_id() );

		if ( $result->is_success() ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: tracking number */
					__( 'Shipping label generated successfully. Tracking: %s', 'speedy_econt_shipping' ),
					$result->get_tracking_number()
				)
			);
		} else {
			$order->add_order_note(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to generate shipping label: %s', 'speedy_econt_shipping' ),
					$result->get_error()
				)
			);
		}
	}

	/**
	 * Add bulk actions.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public function add_bulk_actions( $actions ) {
		$actions['sesh_generate_labels'] = __( 'Generate shipping labels', 'speedy_econt_shipping' );
		$actions['sesh_print_labels']    = __( 'Print shipping labels', 'speedy_econt_shipping' );
		return $actions;
	}

	/**
	 * Handle bulk label generation.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action      Action name.
	 * @param array  $post_ids    Order IDs.
	 * @return string
	 */
	public function handle_bulk_generate_labels( $redirect_to, $action, $post_ids ) {
		if ( 'sesh_generate_labels' !== $action ) {
			return $redirect_to;
		}

		$generator = $this->get_label_generator();
		$manager   = $this->get_label_manager();
		$results   = $manager->bulk_generate_labels( $post_ids, $generator );

		$success_count = 0;
		$error_count   = 0;

		foreach ( $results as $result ) {
			if ( $result->is_success() ) {
				$success_count++;
			} else {
				$error_count++;
			}
		}

		$redirect_to = add_query_arg(
			array(
				'sesh_labels_generated' => $success_count,
				'sesh_labels_failed'    => $error_count,
			),
			$redirect_to
		);

		return $redirect_to;
	}

	/**
	 * Handle bulk label printing.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action      Action name.
	 * @param array  $post_ids    Order IDs.
	 * @return string
	 */
	public function handle_bulk_print_labels( $redirect_to, $action, $post_ids ) {
		if ( 'sesh_print_labels' !== $action ) {
			return $redirect_to;
		}

		// Collect label IDs for orders that have labels.
		$manager   = $this->get_label_manager();
		$label_ids = array();

		foreach ( $post_ids as $order_id ) {
			$label = $manager->get_latest_label( $order_id );
			if ( $label ) {
				$label_ids[] = $label->id;
			}
		}

		if ( empty( $label_ids ) ) {
			$redirect_to = add_query_arg(
				array(
					'sesh_print_error' => 1,
				),
				$redirect_to
			);
			return $redirect_to;
		}

		// Redirect to print endpoint.
		$print_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'    => 'sesh_bulk_print_labels',
					'label_ids' => implode( ',', $label_ids ),
				),
				admin_url( 'admin-ajax.php' )
			),
			'sesh_bulk_print_labels',
			'security'
		);

		wp_safe_redirect( $print_url );
		exit;
	}

	/**
	 * Maybe auto-generate label on order status change.
	 *
	 * @param int      $order_id   Order ID.
	 * @param string   $old_status Old status.
	 * @param string   $new_status New status.
	 * @param WC_Order $order      Order object.
	 */
	public function maybe_auto_generate_label( $order_id, $old_status, $new_status, $order ) {
		// Check if auto-generation is enabled.
		$auto_generate = $this->settings->get( 'general', 'auto_generate_labels', false );
		if ( ! $auto_generate ) {
			return;
		}

		// Check if we should generate for this status.
		$target_status = $this->settings->get( 'general', 'auto_generate_status', 'processing' );
		if ( $new_status !== $target_status ) {
			return;
		}

		// Check if label already exists.
		$manager = $this->get_label_manager();
		if ( $manager->has_label( $order_id ) ) {
			return;
		}

		// Generate label.
		$generator = $this->get_label_generator();
		$result    = $generator->generate_label( $order_id );

		if ( ! $result->is_success() ) {
			// Add admin notice about failure.
			add_option( 'sesh_label_generation_error_' . $order_id, $result->get_error() );
		}
	}

	/**
	 * AJAX handler for label download.
	 */
	public function ajax_download_label() {
		check_ajax_referer( 'sesh_download_label', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'speedy_econt_shipping' ) ) );
		}

		$label_id = isset( $_POST['label_id'] ) ? absint( $_POST['label_id'] ) : 0;

		if ( ! $label_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid label ID.', 'speedy_econt_shipping' ) ) );
		}

		$manager = $this->get_label_manager();
		$manager->download_label( $label_id );
	}

	/**
	 * Get label generator instance.
	 *
	 * @return SESH_Label_Generator
	 */
	private function get_label_generator() {
		$plugin = SESH_Plugin::instance();
		return new SESH_Label_Generator(
			$this->settings,
			$plugin->get_database(),
			$plugin->get_speedy_api(),
			$plugin->get_econt_api()
		);
	}

	/**
	 * Get label manager instance.
	 *
	 * @return SESH_Label_Manager
	 */
	private function get_label_manager() {
		$plugin = SESH_Plugin::instance();
		return new SESH_Label_Manager(
			$plugin->get_database(),
			$plugin->get_speedy_api(),
			$plugin->get_econt_api()
		);
	}
}
