<?php
/**
 * Plugin Name:       Speedy and Econt Shipping
 * Description:       Adds Speedy and Econt shipping methods along with their delivery options.
 * Author:            Dan Goriaynov
 * Author URI:        https://github.com/dangoriaynov
 * Plugin URI:        https://github.com/dangoriaynov/speedy_econt_shipping
 * Version:           2.0.0
 * WC tested up to:   9.0
 * WC requires at least: 7.0
 * Requires PHP:      7.4
 * License:           GNU General Public License, version 2
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.en.html
 * Domain Path:       /languages/
 * Text Domain:       speedy_econt_shipping
 *
 * @package Speedy_Econt_Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Load and initialize the plugin.
require_once __DIR__ . '/includes/class-sesh-plugin.php';
SESH_Plugin::instance( __FILE__ );
