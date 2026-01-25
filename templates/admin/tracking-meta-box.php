<?php
/**
 * Admin template: Tracking Information Meta Box
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

// Get carrier tracking URL.
$tracking_url = '';
if ( 'speedy' === $label->carrier ) {
	$tracking_url = 'https://www.speedy.bg/bg/track-shipment?shipmentId=' . urlencode( $label->tracking_number );
} elseif ( 'econt' === $label->carrier ) {
	$tracking_url = 'https://econt.com/services/shipment-tracking/' . urlencode( $label->tracking_number );
}

// Get current status.
$current_status = '';
$status_icon    = 'dashicons-marker';

if ( $tracking_info && ! empty( $tracking_info['events'] ) ) {
	$latest_event   = reset( $tracking_info['events'] );
	$current_status = isset( $latest_event['status'] ) ? $latest_event['status'] : __( 'Unknown', 'speedy_econt_shipping' );

	// Map status to icon.
	$status_map = array(
		'delivered'   => 'dashicons-yes-alt',
		'in_transit'  => 'dashicons-update',
		'out_for_delivery' => 'dashicons-car',
		'picked_up'   => 'dashicons-randomize',
		'pending'     => 'dashicons-clock',
		'cancelled'   => 'dashicons-no',
	);

	$status_key  = strtolower( str_replace( ' ', '_', $current_status ) );
	$status_icon = isset( $status_map[ $status_key ] ) ? $status_map[ $status_key ] : 'dashicons-marker';
}

// Get last updated time.
$last_updated = get_transient( 'sesh_tracking_updated_' . $label->id );
if ( ! $last_updated ) {
	$last_updated = gmdate( 'Y-m-d H:i:s' );
	set_transient( 'sesh_tracking_updated_' . $label->id, $last_updated, 30 * MINUTE_IN_SECONDS );
}
?>

<div class="sesh-tracking-meta-box" data-label-id="<?php echo esc_attr( $label->id ); ?>">
	<!-- Current Status -->
	<div class="sesh-tracking-status">
		<div class="sesh-status-icon">
			<span class="dashicons <?php echo esc_attr( $status_icon ); ?>"></span>
		</div>
		<div class="sesh-status-info">
			<h4><?php esc_html_e( 'Current Status', 'speedy_econt_shipping' ); ?></h4>
			<p class="sesh-status-text">
				<?php
				if ( $current_status ) {
					echo esc_html( $current_status );
				} else {
					esc_html_e( 'Tracking information not available yet.', 'speedy_econt_shipping' );
				}
				?>
			</p>
		</div>
	</div>

	<!-- Tracking Timeline -->
	<?php if ( $tracking_info && ! empty( $tracking_info['events'] ) ) : ?>
		<div class="sesh-tracking-timeline">
			<h4><?php esc_html_e( 'Tracking Timeline', 'speedy_econt_shipping' ); ?></h4>
			<ul class="sesh-timeline-events">
				<?php foreach ( $tracking_info['events'] as $event ) : ?>
					<li class="sesh-timeline-event">
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
						<div class="sesh-event-details">
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

	<!-- Actions -->
	<div class="sesh-tracking-actions">
		<button type="button" class="button sesh-refresh-tracking" data-label-id="<?php echo esc_attr( $label->id ); ?>">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( 'Refresh Tracking', 'speedy_econt_shipping' ); ?>
		</button>

		<?php if ( $tracking_url ) : ?>
			<a href="<?php echo esc_url( $tracking_url ); ?>" class="button" target="_blank" rel="noopener noreferrer">
				<span class="dashicons dashicons-external"></span>
				<?php esc_html_e( 'Track on Carrier Site', 'speedy_econt_shipping' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<!-- Last Updated -->
	<div class="sesh-tracking-updated">
		<small>
			<?php
			printf(
				/* translators: %s: last updated timestamp */
				esc_html__( 'Last updated: %s', 'speedy_econt_shipping' ),
				'<span class="sesh-updated-time">' . esc_html(
					date_i18n(
						get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
						strtotime( $last_updated )
					)
				) . '</span>'
			);
			?>
		</small>
	</div>

	<!-- Loading spinner (hidden by default) -->
	<div class="sesh-loading" style="display: none;">
		<span class="spinner is-active"></span>
		<span class="sesh-loading-text"><?php esc_html_e( 'Refreshing tracking...', 'speedy_econt_shipping' ); ?></span>
	</div>
</div>
