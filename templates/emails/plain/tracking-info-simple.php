<?php
/**
 * Email template: Simple Tracking Information (Plain Text)
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 *
 * Available variables:
 * @var WC_Order $order Order object.
 * @var object $label Label object from database.
 * @var array|null $tracking_info Not used in simple template.
 */

defined( 'ABSPATH' ) || exit;

// Get tracking URL.
$tracking_url = SESH_Tracking_URLs::get_tracking_url( $label->carrier, $label->tracking_number );
$carrier_name = SESH_Tracking_URLs::get_carrier_name( $label->carrier );

echo "\n========================================\n";
echo strtoupper( __( 'Shipment Information', 'speedy_econt_shipping' ) ) . "\n";
echo "========================================\n\n";

echo __( 'Carrier:', 'speedy_econt_shipping' ) . ' ' . $carrier_name . "\n";
echo __( 'Tracking Number:', 'speedy_econt_shipping' ) . ' ' . $label->tracking_number . "\n";

if ( $tracking_url ) {
	echo "\n" . __( 'Track your shipment:', 'speedy_econt_shipping' ) . "\n";
	echo $tracking_url . "\n";
}

echo "\n========================================\n\n";
