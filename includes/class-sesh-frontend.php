<?php
/**
 * Frontend class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frontend class.
 *
 * Handles all frontend functionality including checkout field customization,
 * office selection, and shipping calculations display.
 *
 * Note: The legacy frontend functions are still active for backward compatibility.
 * This class will gradually take over frontend functionality in future updates.
 */
class SESH_Frontend {

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
	 * Cart calculator instance.
	 *
	 * @var SESH_Cart_Calculator
	 */
	private $cart_calculator;

	/**
	 * Customer tracking instance.
	 *
	 * @var SESH_Customer_Tracking
	 */
	private $customer_tracking;

	/**
	 * Constructor.
	 *
	 * @param SESH_Settings $settings Settings instance.
	 * @param SESH_Database $database Database instance.
	 */
	public function __construct( SESH_Settings $settings, SESH_Database $database ) {
		$this->settings = $settings;
		$this->database = $database;
		$this->init_hooks();
		$this->init_cart_calculator();
		$this->init_customer_tracking();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Enqueue frontend scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add checkout fields for carrier selection.
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_checkout_fields' ) );

		// Output office data for JavaScript.
		add_action( 'wp_footer', array( $this, 'output_office_data' ) );

		// AJAX handlers for office selection (will be expanded in Phase 3).
		add_action( 'wp_ajax_sesh_get_offices', array( $this, 'ajax_get_offices' ) );
		add_action( 'wp_ajax_nopriv_sesh_get_offices', array( $this, 'ajax_get_offices' ) );

		add_action( 'wp_ajax_sesh_get_cities', array( $this, 'ajax_get_cities' ) );
		add_action( 'wp_ajax_nopriv_sesh_get_cities', array( $this, 'ajax_get_cities' ) );
	}

	/**
	 * Initialize cart calculator.
	 */
	private function init_cart_calculator() {
		require_once SESH_PLUGIN_DIR . 'includes/class-sesh-cart-calculator.php';
		$this->cart_calculator = new SESH_Cart_Calculator( $this->settings, $this->database );
	}

	/**
	 * Initialize customer tracking.
	 */
	private function init_customer_tracking() {
		// Get plugin instance to access API clients.
		$plugin = SESH_Plugin::instance();

		// Initialize label manager.
		$label_manager = new SESH_Label_Manager(
			$this->database,
			$plugin->get_speedy_api(),
			$plugin->get_econt_api()
		);

		// Initialize customer tracking.
		$this->customer_tracking = new SESH_Customer_Tracking( $this->database, $label_manager );
	}

	/**
	 * Enqueue frontend scripts and styles.
	 */
	public function enqueue_scripts() {
		// Only load on checkout and cart pages.
		if ( ! ( is_checkout() || is_cart() ) ) {
			return;
		}

		// Enqueue Select2 for dropdowns.
		wp_enqueue_style( 'select2' );
		wp_enqueue_script( 'select2' );

		// Base frontend styles.
		wp_enqueue_style(
			'sesh-frontend',
			SESH_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			SESH_VERSION
		);

		// Modern checkout UI styles.
		wp_enqueue_style(
			'sesh-checkout-css',
			SESH_PLUGIN_URL . 'assets/css/sesh-checkout.css',
			array( 'sesh-frontend', 'select2' ),
			SESH_VERSION
		);

		// Only load checkout scripts on checkout page.
		if ( ! is_checkout() ) {
			return;
		}

		// Enqueue modern JavaScript modules.
		// 1. Price Display (no dependencies).
		wp_enqueue_script(
			'sesh-price-display',
			SESH_PLUGIN_URL . 'assets/js/sesh-price-display.js',
			array( 'jquery' ),
			SESH_VERSION,
			true
		);

		// 2. Location Selector (depends on price display).
		wp_enqueue_script(
			'sesh-location-selector',
			SESH_PLUGIN_URL . 'assets/js/sesh-location-selector.js',
			array( 'jquery', 'select2', 'sesh-price-display' ),
			SESH_VERSION,
			true
		);

		// 3. Checkout main module (depends on both above).
		wp_enqueue_script(
			'sesh-checkout',
			SESH_PLUGIN_URL . 'assets/js/sesh-checkout.js',
			array( 'jquery', 'wc-checkout', 'sesh-price-display', 'sesh-location-selector' ),
			SESH_VERSION,
			true
		);

		// Localize script with checkout data.
		wp_localize_script(
			'sesh-checkout',
			'sesh_checkout_params',
			$this->get_checkout_params()
		);
	}

	/**
	 * Get checkout parameters for JavaScript.
	 *
	 * @return array
	 */
	private function get_checkout_params() {
		// Build delivery options from settings.
		$delivery_options        = $this->build_delivery_options();
		$default_shipping_method = $this->get_default_shipping_method();

		// Build field selectors.
		$selectors = $this->get_field_selectors();

		// Get show store messages from settings.
		$show_store_messages = array_filter(
			array_map( 'trim', explode( ',', $this->settings->get_show_store_messages() ) )
		);

		return array(
			'ajax_url'                => admin_url( 'admin-ajax.php' ),
			'nonce'                   => wp_create_nonce( 'sesh_frontend_nonce' ),
			'delivery_options'        => $delivery_options,
			'default_shipping_method' => $default_shipping_method,
			'currency_symbol'         => html_entity_decode( get_woocommerce_currency_symbol() ),
			'shop_url'                => get_permalink( wc_get_page_id( 'shop' ) ),
			'calculate_final_price'   => $this->settings->is_calculate_final_price(),
			'delivery_price_selector' => $this->settings->get_delivery_price_selector(),
			'free_shipping_suffix'    => $this->settings->get_free_shipping_label_suffix(),
			'show_store_messages'     => $show_store_messages,
			'shipping_to_id'          => 'shipping-to-row',
			'selectors'               => $selectors,
			'i18n'                    => array(
				'select_region'          => __( 'Select region', 'speedy_econt_shipping' ),
				'select_city'            => __( 'Select city', 'speedy_econt_shipping' ),
				'select_office'          => __( 'Select office', 'speedy_econt_shipping' ),
				'loading'                => __( 'Loading...', 'speedy_econt_shipping' ),
				'error'                  => __( 'Error loading data', 'speedy_econt_shipping' ),
				'delivery'               => __( 'delivery', 'speedy_econt_shipping' ),
				'free'                   => __( 'for free', 'speedy_econt_shipping' ),
				'congrats_free_delivery' => __( 'Congrats, you won free delivery using %s!', 'speedy_econt_shipping' ),
				'left_till_free'         => __( 'Still left %s', 'speedy_econt_shipping' ),
				'to_shop'                => __( 'To shop', 'speedy_econt_shipping' ),
				'no_free_shipping'       => __( 'Sorry, there is no free shipping available for the option chosen: %s', 'speedy_econt_shipping' ),
			),
		);
	}

	/**
	 * Build delivery options array for JavaScript.
	 *
	 * @return array
	 */
	private function build_delivery_options() {
		$options = array();
		$order   = $this->settings->get_shipping_options_order();

		foreach ( $order as $carrier ) {
			switch ( $carrier ) {
				case 'speedy':
					if ( $this->settings->is_speedy_enabled() ) {
						$options['speedy'] = array(
							'id'        => 'shipping_method_0_sesh_speedy',
							'name'      => 'speedy',
							'label'     => __( 'Speedy Office', 'speedy_econt_shipping' ),
							'shipping'  => $this->settings->get_speedy_shipping(),
							'free_from' => $this->settings->get_speedy_free_from(),
						);
					}
					break;

				case 'econt':
					if ( $this->settings->is_econt_enabled() ) {
						$options['econt'] = array(
							'id'        => 'shipping_method_0_sesh_econt',
							'name'      => 'econt',
							'label'     => __( 'Econt Office', 'speedy_econt_shipping' ),
							'shipping'  => $this->settings->get_econt_shipping(),
							'free_from' => $this->settings->get_econt_free_from(),
						);
					}
					break;

				case 'address':
					if ( $this->settings->is_address_enabled() ) {
						$options['address'] = array(
							'id'        => 'shipping_method_0_sesh_address',
							'name'      => 'address',
							'label'     => $this->settings->get_address_label(),
							'shipping'  => $this->settings->get_address_shipping(),
							'free_from' => $this->settings->get_address_free_from(),
						);
					}
					break;
			}
		}

		return $options;
	}

	/**
	 * Get default shipping method from settings.
	 *
	 * @return string
	 */
	private function get_default_shipping_method() {
		$order = $this->settings->get_shipping_options_order();
		return ! empty( $order ) ? $order[0] : '';
	}

	/**
	 * Get field selectors for JavaScript.
	 *
	 * @return array
	 */
	private function get_field_selectors() {
		return array(
			// Speedy selectors.
			'speedy_region_sel'    => '#speedy_region',
			'speedy_city_sel'      => '#speedy_city',
			'speedy_office_sel'    => '#speedy_office',
			'speedy_region_field'  => '#speedy_region_field',
			'speedy_city_field'    => '#speedy_city_field',
			'speedy_office_field'  => '#speedy_office_field',

			// Econt selectors.
			'econt_region_sel'     => '#econt_region',
			'econt_city_sel'       => '#econt_city',
			'econt_office_sel'     => '#econt_office',
			'econt_region_field'   => '#econt_region_field',
			'econt_city_field'     => '#econt_city_field',
			'econt_office_field'   => '#econt_office_field',

			// Address selectors.
			'address_region_sel'   => '#billing_state',
			'address_city_sel'     => '#billing_city',
			'address_office_sel'   => '#billing_address_1',
			'address_region_field' => '#billing_state_field',
			'address_city_field'   => '#billing_city_field',
			'address_office_field' => '#billing_address_1_field',

			// Shipping to field.
			'shipping_to_field'    => '#shipping-to-row',
		);
	}

	/**
	 * AJAX handler for getting offices.
	 */
	public function ajax_get_offices() {
		check_ajax_referer( 'sesh_frontend_nonce', 'nonce' );

		$carrier   = isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : '';
		$city_name = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';

		if ( empty( $carrier ) || empty( $city_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'speedy_econt_shipping' ) ) );
		}

		if ( ! in_array( $carrier, array( 'speedy', 'econt' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid carrier', 'speedy_econt_shipping' ) ) );
		}

		$offices = $this->database->get_offices_by_city( $carrier, $city_name );

		if ( empty( $offices ) ) {
			wp_send_json_success( array( 'offices' => array() ) );
		}

		$formatted_offices = array();
		foreach ( $offices as $office ) {
			$formatted_offices[] = array(
				'id'      => $office->id,
				'name'    => $office->name,
				'address' => $office->address,
			);
		}

		wp_send_json_success( array( 'offices' => $formatted_offices ) );
	}

	/**
	 * AJAX handler for getting cities by region.
	 */
	public function ajax_get_cities() {
		check_ajax_referer( 'sesh_frontend_nonce', 'nonce' );

		$carrier = isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : '';
		$region  = isset( $_POST['region'] ) ? sanitize_text_field( wp_unslash( $_POST['region'] ) ) : '';

		if ( empty( $carrier ) || empty( $region ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'speedy_econt_shipping' ) ) );
		}

		if ( ! in_array( $carrier, array( 'speedy', 'econt' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid carrier', 'speedy_econt_shipping' ) ) );
		}

		$cities = $this->database->get_cities_by_region( $carrier, $region );

		if ( empty( $cities ) ) {
			wp_send_json_success( array( 'cities' => array() ) );
		}

		$formatted_cities = array();
		foreach ( $cities as $city ) {
			$formatted_cities[] = array(
				'id'           => $city->id,
				'name'         => $city->name,
				'municipality' => isset( $city->municipality ) ? $city->municipality : '',
			);
		}

		wp_send_json_success( array( 'cities' => $formatted_cities ) );
	}

	/**
	 * Get regions for a carrier.
	 *
	 * @param string $carrier Carrier (speedy or econt).
	 * @return array
	 */
	public function get_regions( $carrier ) {
		return $this->database->get_regions( $carrier );
	}

	/**
	 * Render checkout fields for carrier selection.
	 *
	 * @param WC_Checkout $checkout WooCommerce checkout object.
	 */
	public function render_checkout_fields( $checkout ) {
		if ( ! is_checkout() ) {
			return;
		}

		echo '<div id="shipping-to-row" class="sesh-shipping-fields" style="display:none;">';

		// Render Speedy fields.
		if ( $this->settings->is_speedy_enabled() ) {
			$this->render_carrier_fields( 'speedy', __( 'Speedy Delivery', 'speedy_econt_shipping' ) );
		}

		// Render Econt fields.
		if ( $this->settings->is_econt_enabled() ) {
			$this->render_carrier_fields( 'econt', __( 'Econt Delivery', 'speedy_econt_shipping' ) );
		}

		// Address fields (use standard WooCommerce billing fields).
		if ( $this->settings->is_address_enabled() ) {
			$this->render_address_fields();
		}

		echo '</div>';
	}

	/**
	 * Render carrier-specific fields (region, city, office).
	 *
	 * @param string $carrier Carrier slug (speedy or econt).
	 * @param string $label   Carrier display label.
	 */
	private function render_carrier_fields( $carrier, $label ) {
		$carrier_slug = sanitize_key( $carrier );
		?>
		<div id="<?php echo esc_attr( $carrier_slug ); ?>_fields" class="sesh-carrier-fields" style="display:none;">
			<h3><?php echo esc_html( $label ); ?></h3>

			<p class="form-row form-row-wide" id="<?php echo esc_attr( $carrier_slug ); ?>_region_field">
				<label for="<?php echo esc_attr( $carrier_slug ); ?>_region">
					<?php esc_html_e( 'Region', 'speedy_econt_shipping' ); ?>
					<abbr class="required" title="required">*</abbr>
				</label>
				<select name="<?php echo esc_attr( $carrier_slug ); ?>_region" id="<?php echo esc_attr( $carrier_slug ); ?>_region" class="sesh-region-select" data-carrier="<?php echo esc_attr( $carrier_slug ); ?>">
					<option value=""><?php esc_html_e( 'Select region', 'speedy_econt_shipping' ); ?></option>
				</select>
			</p>

			<p class="form-row form-row-wide" id="<?php echo esc_attr( $carrier_slug ); ?>_city_field">
				<label for="<?php echo esc_attr( $carrier_slug ); ?>_city">
					<?php esc_html_e( 'City', 'speedy_econt_shipping' ); ?>
					<abbr class="required" title="required">*</abbr>
				</label>
				<select name="<?php echo esc_attr( $carrier_slug ); ?>_city" id="<?php echo esc_attr( $carrier_slug ); ?>_city" class="sesh-city-select" data-carrier="<?php echo esc_attr( $carrier_slug ); ?>">
					<option value=""><?php esc_html_e( 'Select city', 'speedy_econt_shipping' ); ?></option>
				</select>
			</p>

			<p class="form-row form-row-wide" id="<?php echo esc_attr( $carrier_slug ); ?>_office_field">
				<label for="<?php echo esc_attr( $carrier_slug ); ?>_office">
					<?php esc_html_e( 'Office', 'speedy_econt_shipping' ); ?>
					<abbr class="required" title="required">*</abbr>
				</label>
				<select name="<?php echo esc_attr( $carrier_slug ); ?>_office" id="<?php echo esc_attr( $carrier_slug ); ?>_office" class="sesh-office-select" data-carrier="<?php echo esc_attr( $carrier_slug ); ?>">
					<option value=""><?php esc_html_e( 'Select office', 'speedy_econt_shipping' ); ?></option>
				</select>
			</p>
		</div>
		<?php
	}

	/**
	 * Render address delivery fields placeholder.
	 *
	 * Address delivery uses standard WooCommerce billing fields.
	 */
	private function render_address_fields() {
		?>
		<div id="address_fields" class="sesh-carrier-fields" style="display:none;">
			<h3><?php echo esc_html( $this->settings->get_address_label() ); ?></h3>
			<p class="sesh-address-notice">
				<?php esc_html_e( 'Please fill in your delivery address in the billing fields above.', 'speedy_econt_shipping' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Output office data as JSON for JavaScript.
	 */
	public function output_office_data() {
		if ( ! is_checkout() ) {
			return;
		}

		// Get office data for enabled carriers.
		$speedy_data = array();
		$econt_data  = array();

		if ( $this->settings->is_speedy_enabled() ) {
			$speedy_data = $this->get_carrier_office_data( 'speedy' );
		}

		if ( $this->settings->is_econt_enabled() ) {
			$econt_data = $this->get_carrier_office_data( 'econt' );
		}

		?>
		<script type="text/javascript">
			window.speedyData = <?php echo wp_json_encode( $speedy_data ); ?>;
			window.econtData = <?php echo wp_json_encode( $econt_data ); ?>;
		</script>
		<?php
	}

	/**
	 * Get office data for a carrier.
	 *
	 * @param string $carrier Carrier slug (speedy or econt).
	 * @return array Structured office data.
	 */
	private function get_carrier_office_data( $carrier ) {
		$regions = $this->database->get_regions( $carrier );
		$sites   = $this->database->get_sites( $carrier );
		$offices = $this->database->get_offices( $carrier );

		$data = array(
			'regions' => array(),
			'cities'  => array(),
			'offices' => array(),
		);

		// Build regions array.
		foreach ( $regions as $region_name => $region_label ) {
			$data['regions'][] = array(
				'name'  => $region_name,
				'label' => $region_label,
			);
		}

		// Build cities array grouped by region.
		foreach ( $sites as $site ) {
			$region_key = $site->region;
			if ( ! isset( $data['cities'][ $region_key ] ) ) {
				$data['cities'][ $region_key ] = array();
			}

			$data['cities'][ $region_key ][] = array(
				'id'           => $site->id,
				'name'         => $site->name,
				'municipality' => isset( $site->municipality ) ? $site->municipality : '',
			);
		}

		// Build offices array grouped by city.
		foreach ( $offices as $office ) {
			$city_name = $office->city;
			if ( ! isset( $data['offices'][ $city_name ] ) ) {
				$data['offices'][ $city_name ] = array();
			}

			$data['offices'][ $city_name ][] = array(
				'id'      => $office->id,
				'name'    => $office->name,
				'address' => $office->address,
			);
		}

		return $data;
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
