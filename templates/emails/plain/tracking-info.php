<?php
/**
 * Email template: Tracking Information (Plain Text)
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 *
 * Available variables:
 * @var WC_Order $order Order object.
 * @var object $label Label object from database.
 * @var array|null $tracking_info Cached tracking information.
 */

defined( 'ABSPATH' ) || exit;

// Get tracking URL.
$tracking_url = SESH_Tracking_URLs::get_tracking_url( $label->carrier, $label->tracking_number );
$carrier_name = SESH_Tracking_URLs::get_carrier_name( $label->carrier );

// Get current status.
$current_status = '';
if ( $tracking_info && ! empty( $tracking_info['events'] ) ) {
	$latest_event   = reset( $tracking_info['events'] );
	$current_status = isset( $latest_event['status'] ) ? $latest_event['status'] : '';
}

echo "\n========================================\n";
echo strtoupper( __( 'Shipment Tracking', 'speedy_econt_shipping' ) ) . "\n";
echo "========================================\n\n";

echo __( 'Carrier:', 'speedy_econt_shipping' ) . ' ' . $carrier_name . "\n";
echo __( 'Tracking Number:', 'speedy_econt_shipping' ) . ' ' . $label->tracking_number . "\n";

if ( $current_status ) {
	echo __( 'Current Status:', 'speedy_econt_shipping' ) . ' ' . $current_status . "\n";
}

if ( $tracking_url ) {
	echo "\n" . __( 'Track your shipment:', 'speedy_econt_shipping' ) . "\n";
	echo $tracking_url . "\n";
}

// Tracking timeline.
if ( $tracking_info && ! empty( $tracking_info['events'] ) ) {
	echo "\n" . __( 'Tracking History:', 'speedy_econt_shipping' ) . "\n";
	echo "----------------------------------------\n";

	foreach ( $tracking_info['events'] as $event ) {
		if ( ! empty( $event['date'] ) ) {
			echo date_i18n(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				is_numeric( $event['date'] ) ? $event['date'] : strtotime( $event['date'] )
			);
			echo ' - ';
		}

		if ( ! empty( $event['status'] ) ) {
			echo $event['status'];
		}

		if ( ! empty( $event['description'] ) ) {
			echo "\n   " . $event['description'];
		}

		if ( ! empty( $event['location'] ) ) {
			echo "\n   " . $event['location'];
		}

		echo "\n\n";
	}
}

echo "========================================\n\n";
