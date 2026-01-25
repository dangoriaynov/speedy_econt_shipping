<?php
/**
 * Email template: Tracking Information (HTML)
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
?>

<div style="margin-bottom: 40px;">
	<h2 style="color: #333; font-size: 18px; font-weight: bold; margin: 0 0 10px;">
		<?php esc_html_e( 'Shipment Tracking', 'speedy_econt_shipping' ); ?>
	</h2>

	<table cellspacing="0" cellpadding="0" style="width: 100%; border: 1px solid #e5e5e5; border-radius: 4px;" border="0">
		<tbody>
			<!-- Carrier -->
			<tr>
				<td style="padding: 12px; background-color: #f7f7f7; border-bottom: 1px solid #e5e5e5;">
					<strong><?php esc_html_e( 'Carrier:', 'speedy_econt_shipping' ); ?></strong>
				</td>
				<td style="padding: 12px; background-color: #fff; border-bottom: 1px solid #e5e5e5;">
					<?php echo esc_html( $carrier_name ); ?>
				</td>
			</tr>

			<!-- Tracking Number -->
			<tr>
				<td style="padding: 12px; background-color: #f7f7f7; border-bottom: 1px solid #e5e5e5;">
					<strong><?php esc_html_e( 'Tracking Number:', 'speedy_econt_shipping' ); ?></strong>
				</td>
				<td style="padding: 12px; background-color: #fff; border-bottom: 1px solid #e5e5e5;">
					<strong style="font-size: 16px;"><?php echo esc_html( $label->tracking_number ); ?></strong>
				</td>
			</tr>

			<!-- Current Status -->
			<?php if ( $current_status ) : ?>
				<tr>
					<td style="padding: 12px; background-color: #f7f7f7;">
						<strong><?php esc_html_e( 'Current Status:', 'speedy_econt_shipping' ); ?></strong>
					</td>
					<td style="padding: 12px; background-color: #fff;">
						<?php echo esc_html( $current_status ); ?>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Track Shipment Button -->
	<?php if ( $tracking_url ) : ?>
		<div style="margin-top: 20px; text-align: center;">
			<a href="<?php echo esc_url( $tracking_url ); ?>"
			   style="background-color: #0073aa; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;"
			   target="_blank"
			   rel="noopener noreferrer">
				<?php esc_html_e( 'Track Your Shipment', 'speedy_econt_shipping' ); ?>
			</a>
		</div>
	<?php endif; ?>

	<!-- Tracking Timeline -->
	<?php if ( $tracking_info && ! empty( $tracking_info['events'] ) ) : ?>
		<div style="margin-top: 30px;">
			<h3 style="color: #333; font-size: 16px; font-weight: bold; margin: 0 0 15px;">
				<?php esc_html_e( 'Tracking History', 'speedy_econt_shipping' ); ?>
			</h3>
			<table cellspacing="0" cellpadding="0" style="width: 100%; border: 1px solid #e5e5e5;" border="0">
				<tbody>
					<?php
					$event_count = count( $tracking_info['events'] );
					$index       = 0;
					foreach ( $tracking_info['events'] as $event ) :
						$index++;
						$border_style = ( $index < $event_count ) ? 'border-bottom: 1px solid #e5e5e5;' : '';
						?>
						<tr>
							<td style="padding: 12px; <?php echo esc_attr( $border_style ); ?>">
								<div style="color: #666; font-size: 13px; margin-bottom: 4px;">
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
								<div style="font-weight: bold; margin-bottom: 4px;">
									<?php echo esc_html( isset( $event['status'] ) ? $event['status'] : '' ); ?>
								</div>
								<?php if ( ! empty( $event['description'] ) ) : ?>
									<div style="color: #666; font-size: 13px;">
										<?php echo esc_html( $event['description'] ); ?>
									</div>
								<?php endif; ?>
								<?php if ( ! empty( $event['location'] ) ) : ?>
									<div style="color: #666; font-size: 13px;">
										<?php echo esc_html( $event['location'] ); ?>
									</div>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
