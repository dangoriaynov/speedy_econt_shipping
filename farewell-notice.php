<?php
/**
 * The quiet handover notice.
 *
 * This plugin still works and is not going anywhere, but it is not being developed any more: the block
 * checkout it cannot draw its fields in, the couriers it does not speak to, and the labels it cannot
 * print all live in its successor. A shop that never opens wp.org has no way of knowing that, so it is
 * said here - once, dismissible, and only to someone who could act on it.
 *
 * Deliberately restrained: one blue notice on the screens where shipping is actually configured, a line
 * under the plugin on the Plugins screen, and nothing anywhere else. No pop-ups, no red, no countdown.
 *
 * Closing it is the cross WordPress draws on every dismissible notice, in the place every admin already
 * looks for it - not a second button competing with the only one worth clicking.
 *
 * @package Speedy_Econt_Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SESH_SUCCESSOR_URL  = 'https://wordpress.org/plugins/bg-couriers/';
const SESH_SUCCESSOR_NAME = 'BG Couriers for WooCommerce';
const SESH_DISMISS_META   = 'sesh_handover_dismissed';

/**
 * Screens where a shipping notice belongs: the dashboard, the plugin list, this plugin's own page and
 * WooCommerce's own screens. Not on posts, media or anyone else's settings.
 */
function sesh_handover_screen(): bool {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return false;
	}
	$screen = get_current_screen();
	if ( ! $screen ) {
		return false;
	}
	if ( in_array( $screen->id, array( 'dashboard', 'plugins', 'plugins-network' ), true ) ) {
		return true;
	}
	// WooCommerce's own screens and this plugin's settings page.
	return ( false !== strpos( $screen->id, 'woocommerce' ) )
		|| ( false !== strpos( $screen->id, 'speedy_econt' ) )
		|| ( false !== strpos( (string) $screen->post_type, 'shop_order' ) );
}

/** The notice itself. */
function sesh_handover_notice(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	if ( get_user_meta( get_current_user_id(), SESH_DISMISS_META, true ) ) {
		return;
	}
	if ( ! sesh_handover_screen() ) {
		return;
	}

	echo '<div class="notice notice-info is-dismissible" id="sesh-handover-notice" style="border-left-color:#2271b1">';
	printf(
		'<p><strong>%s</strong></p>',
		esc_html__( 'Speedy and Econt Shipping is no longer being developed', 'speedy_econt_shipping' )
	);
	printf(
		'<p>%s</p>',
		esc_html(
			sprintf(
				/* translators: %s: the successor plugin's name. */
				__( 'It keeps working and your settings are untouched. Its successor, %s, is free as well and carries on where this one stops: Speedy, Econt, BOX NOW, Sameday, Pigeon Express, Express One and Evropat, the block checkout, waybills and labels in one click, and parcel tracking for your customers.', 'speedy_econt_shipping' ),
				SESH_SUCCESSOR_NAME
			)
		)
	);
	printf(
		'<p><a class="button button-primary" href="%s" target="_blank" rel="noopener">%s</a></p>',
		esc_url( SESH_SUCCESSOR_URL ),
		esc_html__( 'See the successor', 'speedy_econt_shipping' )
	);
	echo '</div>';

	// WordPress draws the cross and hides the notice; only remembering it is ours to do.
	printf(
		'<script>jQuery(function($){$("#sesh-handover-notice").on("click",".notice-dismiss",function(){'
		. '$.post(ajaxurl,{action:"sesh_handover_dismiss",_ajax_nonce:"%s"});});});</script>',
		esc_js( wp_create_nonce( 'sesh_handover_dismiss' ) )
	);
}
add_action( 'admin_notices', 'sesh_handover_notice' );

/** Dismissed for this user, for good - nothing here is worth asking twice. */
function sesh_handover_dismiss(): void {
	check_ajax_referer( 'sesh_handover_dismiss' );
	update_user_meta( get_current_user_id(), SESH_DISMISS_META, 1 );
	wp_send_json_success();
}
add_action( 'wp_ajax_sesh_handover_dismiss', 'sesh_handover_dismiss' );

/**
 * One line under the plugin on the Plugins screen - for the shop that dismissed the notice months ago
 * and now wonders why nothing changes here any more.
 */
function sesh_handover_row_meta( array $links, string $file ): array {
	if ( plugin_basename( SESH_PLUGIN_FILE ) !== $file ) {
		return $links;
	}
	$links[] = sprintf(
		'<a href="%s" target="_blank" rel="noopener">%s</a>',
		esc_url( SESH_SUCCESSOR_URL ),
		esc_html__( 'Successor: BG Couriers', 'speedy_econt_shipping' )
	);
	return $links;
}
add_filter( 'plugin_row_meta', 'sesh_handover_row_meta', 10, 2 );
