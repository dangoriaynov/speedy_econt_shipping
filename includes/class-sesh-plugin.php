<?php
/**
 * Main plugin class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class - singleton pattern.
 *
 * Responsible for initializing the plugin, loading dependencies,
 * and orchestrating all plugin functionality.
 */
final class SESH_Plugin {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '2.0.0';

	/**
	 * Database version.
	 *
	 * @var string
	 */
	const DB_VERSION = '2.0.0';

	/**
	 * Minimum WooCommerce version required.
	 *
	 * @var string
	 */
	const MIN_WC_VERSION = '7.0.0';

	/**
	 * Minimum PHP version required.
	 *
	 * @var string
	 */
	const MIN_PHP_VERSION = '7.4';

	/**
	 * Singleton instance.
	 *
	 * @var SESH_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Speedy API client instance.
	 *
	 * @var SESH_Speedy_API|null
	 */
	private $speedy_api = null;

	/**
	 * Econt API client instance.
	 *
	 * @var SESH_Econt_API|null
	 */
	private $econt_api = null;

	/**
	 * Settings instance.
	 *
	 * @var SESH_Settings|null
	 */
	private $settings = null;

	/**
	 * Database handler instance.
	 *
	 * @var SESH_Database|null
	 */
	private $database = null;

	/**
	 * Plugin file path.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Get singleton instance.
	 *
	 * @param string $plugin_file Main plugin file path.
	 * @return SESH_Plugin
	 */
	public static function instance( $plugin_file = '' ) {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self( $plugin_file );
		}
		return self::$instance;
	}

	/**
	 * Private constructor - use instance() method.
	 *
	 * @param string $plugin_file Main plugin file path.
	 */
	private function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 *
	 * @throws Exception Always throws exception.
	 */
	public function __wakeup() {
		throw new Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Define plugin constants.
	 */
	private function define_constants() {
		$this->define( 'SESH_VERSION', self::VERSION );
		$this->define( 'SESH_DB_VERSION', self::DB_VERSION );
		$this->define( 'SESH_PLUGIN_FILE', $this->plugin_file );
		$this->define( 'SESH_PLUGIN_DIR', plugin_dir_path( $this->plugin_file ) );
		$this->define( 'SESH_PLUGIN_URL', plugin_dir_url( $this->plugin_file ) );
		$this->define( 'SESH_PLUGIN_BASENAME', plugin_basename( $this->plugin_file ) );
	}

	/**
	 * Define constant if not already defined.
	 *
	 * @param string $name  Constant name.
	 * @param mixed  $value Constant value.
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		// Core classes.
		require_once SESH_PLUGIN_DIR . 'includes/class-sesh-autoloader.php';
		require_once SESH_PLUGIN_DIR . 'includes/class-sesh-encryption.php';
		require_once SESH_PLUGIN_DIR . 'includes/class-sesh-settings-migrator.php';
		require_once SESH_PLUGIN_DIR . 'includes/class-sesh-settings.php';
		require_once SESH_PLUGIN_DIR . 'includes/database/class-sesh-database.php';
		require_once SESH_PLUGIN_DIR . 'includes/database/class-sesh-db-migrator.php';

		// API classes.
		require_once SESH_PLUGIN_DIR . 'includes/api/interface-sesh-api-client.php';
		require_once SESH_PLUGIN_DIR . 'includes/api/class-sesh-speedy-api.php';
		require_once SESH_PLUGIN_DIR . 'includes/api/class-sesh-econt-api.php';

		// Note: Shipping method classes are loaded in load_shipping_method_classes()
		// on 'woocommerce_shipping_init' hook to ensure WC_Shipping_Method is available.

		// Admin classes.
		if ( is_admin() ) {
			require_once SESH_PLUGIN_DIR . 'includes/admin/class-sesh-admin.php';
			require_once SESH_PLUGIN_DIR . 'includes/admin/class-sesh-wc-settings.php';
		}

		// Frontend classes.
		if ( ! is_admin() || defined( 'DOING_AJAX' ) ) {
			require_once SESH_PLUGIN_DIR . 'includes/class-sesh-frontend.php';
		}
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Plugin activation/deactivation.
		register_activation_hook( $this->plugin_file, array( $this, 'activate' ) );
		register_deactivation_hook( $this->plugin_file, array( $this, 'deactivate' ) );

		// Initialize plugin after plugins are loaded.
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );

		// WooCommerce compatibility declarations.
		add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );

		// Load shipping method classes when WooCommerce is ready.
		add_action( 'woocommerce_shipping_init', array( $this, 'load_shipping_method_classes' ) );

		// Register shipping methods with WooCommerce.
		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_methods' ) );

		// Add settings link on plugins page.
		add_filter( 'plugin_action_links_' . SESH_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		// Check requirements.
		if ( ! $this->check_requirements() ) {
			return;
		}

		// Load text domain.
		$this->load_textdomain();

		// Run settings migration if needed.
		SESH_Settings_Migrator::maybe_migrate();

		// Migrate to WooCommerce settings format if needed.
		SESH_Settings_Migrator::migrate_to_wc_settings();

		// Initialize components.
		$this->settings = new SESH_Settings();
		$this->database = new SESH_Database();

		// Run database migration if needed.
		$db_migrator = new SESH_DB_Migrator( $this->database );
		$db_migrator->maybe_migrate();

		// Initialize API clients if credentials are available.
		if ( $this->settings->is_speedy_enabled() && $this->settings->get_speedy_username() ) {
			$this->speedy_api = new SESH_Speedy_API(
				$this->settings->get_speedy_username(),
				$this->settings->get_speedy_password()
			);
			$this->speedy_api->set_database( $this->database );
		}

		// Initialize Econt API (credentials optional for nomenclatures, required for shipments).
		if ( $this->settings->is_econt_enabled() ) {
			$this->econt_api = new SESH_Econt_API(
				$this->settings->get_econt_username(),
				$this->settings->get_econt_password(),
				$this->database
			);
		}

		// Initialize admin.
		if ( is_admin() ) {
			new SESH_Admin( $this->settings );
			$this->init_wc_settings();
		}

		// Initialize frontend.
		if ( ! is_admin() || defined( 'DOING_AJAX' ) ) {
			new SESH_Frontend( $this->settings, $this->database );
		}

		/**
		 * Fires when the plugin is fully initialized.
		 *
		 * @since 2.0.0
		 * @param SESH_Plugin $plugin Plugin instance.
		 */
		do_action( 'sesh_init', $this );
	}

	/**
	 * Check plugin requirements.
	 *
	 * @return bool
	 */
	private function check_requirements() {
		// Check PHP version.
		if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
			return false;
		}

		// Check if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return false;
		}

		// Check WooCommerce version.
		if ( version_compare( WC_VERSION, self::MIN_WC_VERSION, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'wc_version_notice' ) );
			return false;
		}

		return true;
	}

	/**
	 * Load plugin textdomain.
	 */
	private function load_textdomain() {
		load_plugin_textdomain(
			'speedy_econt_shipping',
			false,
			dirname( SESH_PLUGIN_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Declare WooCommerce compatibility.
	 */
	public function declare_wc_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			// HPOS compatibility.
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				$this->plugin_file,
				true
			);

			// Cart and Checkout blocks compatibility (will be implemented later).
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				$this->plugin_file,
				false
			);
		}
	}

	/**
	 * Load shipping method classes.
	 *
	 * Called on 'woocommerce_shipping_init' to ensure WC_Shipping_Method is available.
	 */
	public function load_shipping_method_classes() {
		require_once SESH_PLUGIN_DIR . 'includes/shipping-methods/abstract-sesh-shipping-method.php';
		require_once SESH_PLUGIN_DIR . 'includes/shipping-methods/class-sesh-shipping-speedy.php';
		require_once SESH_PLUGIN_DIR . 'includes/shipping-methods/class-sesh-shipping-econt.php';
		require_once SESH_PLUGIN_DIR . 'includes/shipping-methods/class-sesh-shipping-address.php';
	}

	/**
	 * Register shipping methods with WooCommerce.
	 *
	 * @param array $methods Existing shipping methods.
	 * @return array
	 */
	public function register_shipping_methods( $methods ) {
		$methods['sesh_speedy']  = 'SESH_Shipping_Speedy';
		$methods['sesh_econt']   = 'SESH_Shipping_Econt';
		$methods['sesh_address'] = 'SESH_Shipping_Address';
		return $methods;
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		// Ensure required classes are loaded.
		require_once SESH_PLUGIN_DIR . 'includes/database/class-sesh-database.php';
		require_once SESH_PLUGIN_DIR . 'includes/database/class-sesh-db-migrator.php';

		// Create database tables.
		$database = new SESH_Database();
		$database->create_tables();

		// Run migrations for existing installations.
		$migrator = new SESH_DB_Migrator( $database );
		$migrator->maybe_migrate();

		// Schedule data refresh.
		if ( ! wp_next_scheduled( 'sesh_daily_data_refresh' ) ) {
			wp_schedule_event( strtotime( '03:05:00' ), 'daily', 'sesh_daily_data_refresh' );
		}

		// Schedule immediate data fetch.
		wp_schedule_single_event( time() + 60, 'sesh_initial_data_fetch' );

		// Set plugin version.
		update_option( 'sesh_version', self::VERSION );

		// Clear any cached data.
		wp_cache_flush();

		/**
		 * Fires on plugin activation.
		 *
		 * @since 2.0.0
		 */
		do_action( 'sesh_activated' );
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		// Clear scheduled hooks.
		wp_clear_scheduled_hook( 'sesh_daily_data_refresh' );
		wp_clear_scheduled_hook( 'sesh_initial_data_fetch' );
		wp_clear_scheduled_hook( 'sesh_speedy_data_refresh' );
		wp_clear_scheduled_hook( 'sesh_econt_data_refresh' );

		// Legacy hooks.
		wp_clear_scheduled_hook( 'seshDailyDbHook' );
		wp_clear_scheduled_hook( 'seshEcontUpdateDbHook' );
		wp_clear_scheduled_hook( 'seshSpeedyEcontUpdateDbHook' );

		/**
		 * Fires on plugin deactivation.
		 *
		 * @since 2.0.0
		 */
		do_action( 'sesh_deactivated' );
	}

	/**
	 * Initialize WooCommerce settings integration.
	 */
	private function init_wc_settings() {
		// Add our settings tab to WooCommerce settings.
		add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_wc_settings_page' ) );
	}

	/**
	 * Add settings page to WooCommerce.
	 *
	 * @param array $settings Settings pages.
	 * @return array
	 */
	public function add_wc_settings_page( $settings ) {
		$settings[] = new SESH_WC_Settings( $this->settings );
		return $settings;
	}

	/**
	 * Add plugin action links.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=sesh_shipping' ) ) . '">' .
			esc_html__( 'Settings', 'speedy_econt_shipping' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}

	/**
	 * PHP version notice.
	 */
	public function php_version_notice() {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				/* translators: %1$s: Required PHP version, %2$s: Current PHP version */
				esc_html__( 'Speedy & Econt Shipping requires PHP %1$s or higher. You are running PHP %2$s.', 'speedy_econt_shipping' ),
				self::MIN_PHP_VERSION,
				PHP_VERSION
			)
		);
	}

	/**
	 * WooCommerce missing notice.
	 */
	public function woocommerce_missing_notice() {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Speedy & Econt Shipping requires WooCommerce to be installed and active.', 'speedy_econt_shipping' )
		);
	}

	/**
	 * WooCommerce version notice.
	 */
	public function wc_version_notice() {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				/* translators: %1$s: Required WC version, %2$s: Current WC version */
				esc_html__( 'Speedy & Econt Shipping requires WooCommerce %1$s or higher. You are running WooCommerce %2$s.', 'speedy_econt_shipping' ),
				self::MIN_WC_VERSION,
				WC_VERSION
			)
		);
	}

	/**
	 * Get Speedy API client.
	 *
	 * @return SESH_Speedy_API|null
	 */
	public function get_speedy_api() {
		return $this->speedy_api;
	}

	/**
	 * Get Econt API client.
	 *
	 * @return SESH_Econt_API|null
	 */
	public function get_econt_api() {
		return $this->econt_api;
	}

	/**
	 * Get settings instance.
	 *
	 * @return SESH_Settings|null
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Get database handler.
	 *
	 * @return SESH_Database|null
	 */
	public function get_database() {
		return $this->database;
	}

	/**
	 * Get plugin file path.
	 *
	 * @return string
	 */
	public function get_plugin_file() {
		return $this->plugin_file;
	}
}
