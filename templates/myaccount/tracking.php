<?php
/**
 * My Account template: Tracking Information
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
$carrier_logo = SESH_Tracking_URLs::get_carrier_logo_url( $label->carrier );

// Get current status.
$current_status = '';
if ( $tracking_info && ! empty( $tracking_info['events'] ) ) {
	$latest_event   = reset( $tracking_info['events'] );
	$current_status = isset( $latest_event['status'] ) ? $latest_event['status'] : '';
}
?>

<section class="sesh-tracking-section woocommerce-order-tracking">
	<h2 class="woocommerce-order-tracking__title">
		<?php esc_html_e( 'Shipment Tracking', 'speedy_econt_shipping' ); ?>
	</h2>

	<div class="sesh-tracking-container" data-label-id="<?php echo esc_attr( $label->id ); ?>">

		<!-- Carrier Header -->
		<div class="sesh-tracking-header">
			<?php if ( $carrier_logo && file_exists( str_replace( SESH_PLUGIN_URL, SESH_PLUGIN_DIR, $carrier_logo ) ) ) : ?>
				<div class="sesh-carrier-logo">
					<img src="<?php echo esc_url( $carrier_logo ); ?>" alt="<?php echo esc_attr( $carrier_name ); ?>" />
				</div>
			<?php else : ?>
				<div class="sesh-carrier-name">
					<strong><?php echo esc_html( $carrier_name ); ?></strong>
				</div>
			<?php endif; ?>
		</div>

		<!-- Tracking Number -->
		<div class="sesh-tracking-number">
			<label><?php esc_html_e( 'Tracking Number:', 'speedy_econt_shipping' ); ?></label>
			<div class="sesh-tracking-number-value">
				<strong><?php echo esc_html( $label->tracking_number ); ?></strong>
				<button type="button" class="sesh-copy-tracking" data-tracking="<?php echo esc_attr( $label->tracking_number ); ?>" title="<?php esc_attr_e( 'Copy tracking number', 'speedy_econt_shipping' ); ?>">
					<span class="dashicons dashicons-admin-page"></span>
				</button>
			</div>
		</div>

		<!-- Current Status -->
		<?php if ( $current_status ) : ?>
			<div class="sesh-tracking-status">
				<label><?php esc_html_e( 'Current Status:', 'speedy_econt_shipping' ); ?></label>
				<div class="sesh-status-badge">
					<?php echo esc_html( $current_status ); ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- Track Shipment Button -->
		<?php if ( $tracking_url ) : ?>
			<div class="sesh-tracking-actions">
				<a href="<?php echo esc_url( $tracking_url ); ?>" class="button sesh-track-button" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Track Your Shipment', 'speedy_econt_shipping' ); ?>
					<span class="dashicons dashicons-external"></span>
				</a>
			</div>
		<?php endif; ?>

		<!-- Tracking Timeline -->
		<?php if ( $tracking_info && ! empty( $tracking_info['events'] ) ) : ?>
			<div class="sesh-tracking-timeline">
				<h3><?php esc_html_e( 'Tracking History', 'speedy_econt_shipping' ); ?></h3>
				<ul class="sesh-timeline-events">
					<?php foreach ( $tracking_info['events'] as $event ) : ?>
						<li class="sesh-timeline-event">
							<div class="sesh-event-marker"></div>
							<div class="sesh-event-content">
								<div class="sesh-event-date">
									<?php
									if ( ! empty( $event['date'] ) ) {
										echo esc_html(
											date_i18n(
												get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
												is_numeric( $event['date'] ) ? $event['date'] : strtotime( $event['date'] )
											)
										);
									}
									?>
								</div>
								<div class="sesh-event-status">
									<?php echo esc_html( isset( $event['status'] ) ? $event['status'] : '' ); ?>
								</div>
								<?php if ( ! empty( $event['description'] ) ) : ?>
									<div class="sesh-event-description">
										<?php echo esc_html( $event['description'] ); ?>
									</div>
								<?php endif; ?>
								<?php if ( ! empty( $event['location'] ) ) : ?>
									<div class="sesh-event-location">
										<span class="dashicons dashicons-location"></span>
										<?php echo esc_html( $event['location'] ); ?>
									</div>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<!-- Refresh Button -->
		<div class="sesh-tracking-footer">
			<button type="button" class="button sesh-refresh-tracking" data-label-id="<?php echo esc_attr( $label->id ); ?>">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Refresh Tracking', 'speedy_econt_shipping' ); ?>
			</button>
			<span class="sesh-tracking-updated">
				<?php
				printf(
					/* translators: %d: cache duration in minutes */
					esc_html__( 'Updates every %d minutes', 'speedy_econt_shipping' ),
					absint( SESH_Customer_Tracking::CACHE_DURATION / 60 )
				);
				?>
			</span>
		</div>

		<!-- Loading spinner -->
		<div class="sesh-tracking-loading" style="display: none;">
			<span class="spinner is-active"></span>
			<span><?php esc_html_e( 'Refreshing tracking...', 'speedy_econt_shipping' ); ?></span>
		</div>

	</div>
</section>
