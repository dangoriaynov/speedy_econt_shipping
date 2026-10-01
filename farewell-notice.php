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
const SESH_SUCCESSOR_SLUG = 'bg-couriers';
const SESH_SUCCESSOR_FILE = 'bg-couriers/bg-couriers.php';
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

/**
 * Is the notice due on this request? Asked twice: once to print it, once to decide whether the
 * install dialog's assets are worth loading.
 */
function sesh_handover_due(): bool {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'activate_plugins' ) ) {
		return false;
	}
	if ( get_user_meta( get_current_user_id(), SESH_DISMISS_META, true ) ) {
		return false;
	}
	// A shop already running the successor has migrated and does not need telling.
	if ( function_exists( 'is_plugin_active' ) && is_plugin_active( SESH_SUCCESSOR_FILE ) ) {
		return false;
	}
	return sesh_handover_screen();
}

/**
 * The successor's name, as a link.
 *
 * To someone who may install plugins this is WordPress's own plugin dialog, opened in place: the
 * description, the screenshots and an Install button, without leaving the screen or being told to go
 * and search for a name. To everyone else it is the page on WordPress.org, which is all their account
 * could do with anyway.
 */
function sesh_handover_link(): string {
	$label = esc_html( SESH_SUCCESSOR_NAME );
	if ( current_user_can( 'install_plugins' ) ) {
		$url = self_admin_url(
			'plugin-install.php?tab=plugin-information&plugin=' . SESH_SUCCESSOR_SLUG
			. '&TB_iframe=true&width=772&height=550'
		);
		return sprintf( '<a href="%s" class="thickbox open-plugin-details-modal">%s</a>', esc_url( $url ), $label );
	}
	return sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( SESH_SUCCESSOR_URL ), $label );
}

/** The dialog's own script and styles, on the screens that will show the link. */
function sesh_handover_assets(): void {
	if ( sesh_handover_due() ) {
		add_thickbox();
	}
}
add_action( 'admin_enqueue_scripts', 'sesh_handover_assets' );

/** The notice itself. */
function sesh_handover_notice(): void {
	if ( ! sesh_handover_due() ) {
		return;
	}

	echo '<div class="notice notice-info is-dismissible" id="sesh-handover-notice" style="border-left-color:#2271b1">';
	printf(
		'<p><strong>%s</strong></p>',
		esc_html__( 'Speedy and Econt Shipping is no longer being developed', 'speedy_econt_shipping' )
	);
	printf(
		'<p>%s</p>',
		sprintf(
			/* translators: %s: the successor plugin's name, as a link. */
			esc_html__( 'It keeps working and your settings are untouched. Its successor, %s, is free as well and does considerably more: it supports Speedy, Econt, BOX NOW, Sameday, Pigeon Express, Express One and Evropat, the block checkout, waybills and labels in one click, and parcel tracking for your customers.', 'speedy_econt_shipping' ),
			// The link is assembled from esc_url() and esc_html() in sesh_handover_link(); escaping it
			// again here would print the anchor as text.
			sesh_handover_link() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
