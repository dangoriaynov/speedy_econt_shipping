<?php
/**
 * Autoloader for plugin classes.
 *
 * @package SpeedyEcontShipping
 * @since   2.0.0
 */

namespace SpeedyEcontShipping;

defined( 'ABSPATH' ) || exit;

/**
 * Autoloader class.
 *
 * Handles autoloading of plugin classes following WordPress naming conventions
 * with PSR-4 namespace support.
 */
class Autoloader {

	/**
	 * Path to the includes directory.
	 *
	 * @var string
	 */
	private $include_path;

	/**
	 * Namespace prefix.
	 *
	 * @var string
	 */
	private $namespace_prefix = 'SpeedyEcontShipping\\';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->include_path = defined( 'SPEEDY_ECONT_PLUGIN_DIR' )
			? SPEEDY_ECONT_PLUGIN_DIR . 'includes/'
			: plugin_dir_path( dirname( __FILE__ ) );

		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class_name Fully qualified class name to load.
	 */
	public function autoload( $class_name ) {
		// Only handle our namespaced classes.
		if ( 0 !== strpos( $class_name, $this->namespace_prefix ) ) {
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
	 * @param string $class_name Fully qualified class name.
	 * @return string|false File path or false if not found.
	 */
	private function get_file_path( $class_name ) {
		// Remove namespace prefix.
		$class_name = str_replace( $this->namespace_prefix, '', $class_name );

		// Handle nested namespaces (e.g., Database\Database, API\SpeedyAPI).
		$parts = explode( '\\', $class_name );

		if ( count( $parts ) > 1 ) {
			// Last part is the class name, everything else is sub-namespace.
			$class_name = array_pop( $parts );
			$sub_namespace = implode( '/', array_map( 'strtolower', $parts ) );
		} else {
			$sub_namespace = '';
		}

		// Convert class name to file name following WordPress conventions.
		// Plugin_Manager -> class-plugin-manager.php
		$file_name = $this->convert_class_to_filename( $class_name );

		// Build possible paths.
		$paths = $this->get_possible_paths( $sub_namespace );

		// Search for the file.
		foreach ( $paths as $path ) {
			$file = $path . $file_name;
			if ( file_exists( $file ) ) {
				return $file;
			}
		}

		return false;
	}

	/**
	 * Convert class name to file name.
	 *
	 * @param string $class_name Class name.
	 * @return string File name.
	 */
	private function convert_class_to_filename( $class_name ) {
		// Check for interface.
		if ( false !== strpos( $class_name, 'Interface' ) ) {
			$name = str_replace( 'Interface', '', $class_name );
			return 'interface-' . $this->format_filename( $name ) . '.php';
		}

		// Check if it's an abstract class (by naming convention).
		if ( 0 === strpos( $class_name, 'Abstract' ) || 0 === strpos( $class_name, 'Shipping_Method' ) ) {
			return 'abstract-' . $this->format_filename( $class_name ) . '.php';
		}

		// Regular class.
		return 'class-' . $this->format_filename( $class_name ) . '.php';
	}

	/**
	 * Format class name to filename.
	 *
	 * @param string $name Class name.
	 * @return string Formatted filename (without .php).
	 */
	private function format_filename( $name ) {
		// Convert CamelCase to kebab-case.
		// APIClient -> api-client
		// SpeedyAPI -> speedy-api
		$name = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $name );
		$name = preg_replace( '/([A-Z])([A-Z][a-z])/', '$1-$2', $name );
		return strtolower( $name );
	}

	/**
	 * Get possible directory paths for class files.
	 *
	 * @param string $sub_namespace Sub-namespace path.
	 * @return array List of possible paths.
	 */
	private function get_possible_paths( $sub_namespace ) {
		$paths = array( $this->include_path );

		if ( ! empty( $sub_namespace ) ) {
			$paths[] = $this->include_path . $sub_namespace . '/';
		}

		// Common subdirectories.
		$subdirs = array( 'api', 'admin', 'database', 'shipping-methods', 'frontend' );

		foreach ( $subdirs as $subdir ) {
			$paths[] = $this->include_path . $subdir . '/';
		}

		return $paths;
	}
}
