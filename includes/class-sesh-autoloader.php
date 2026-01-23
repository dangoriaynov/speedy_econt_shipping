<?php
/**
 * Autoloader for plugin classes.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Autoloader class.
 *
 * Handles autoloading of plugin classes following WordPress naming conventions.
 */
class SESH_Autoloader {

	/**
	 * Path to the includes directory.
	 *
	 * @var string
	 */
	private $include_path;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->include_path = SESH_PLUGIN_DIR . 'includes/';
		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class_name Class name to load.
	 */
	public function autoload( $class_name ) {
		// Only handle our classes.
		if ( 0 !== strpos( $class_name, 'SESH_' ) ) {
			return;
		}

		$file = $this->get_file_path( $class_name );

		if ( $file && is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Get file path for a class.
	 *
	 * @param string $class_name Class name.
	 * @return string|false File path or false if not found.
	 */
	private function get_file_path( $class_name ) {
		// Convert class name to file name.
		$file_name = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

		// Define possible paths.
		$paths = array(
			$this->include_path,
			$this->include_path . 'api/',
			$this->include_path . 'admin/',
			$this->include_path . 'database/',
			$this->include_path . 'shipping-methods/',
		);

		// Check for interface.
		if ( 0 === strpos( $class_name, 'SESH_' ) && false !== strpos( strtolower( $class_name ), 'interface' ) ) {
			$file_name = 'interface-' . str_replace( array( '_', 'interface-' ), array( '-', '' ), strtolower( $class_name ) ) . '.php';
		}

		// Check for abstract class.
		if ( 0 === strpos( $class_name, 'SESH_' ) && 0 === strpos( $class_name, 'SESH_Shipping_Method' ) ) {
			$file_name = 'abstract-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
		}

		// Search for the file.
		foreach ( $paths as $path ) {
			$file = $path . $file_name;
			if ( file_exists( $file ) ) {
				return $file;
			}
		}

		return false;
	}
}

// Initialize autoloader.
new SESH_Autoloader();
