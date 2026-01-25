<?php
/**
 * Email template: Simple Tracking Information (HTML)
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
?>

<div style="margin-bottom: 40px;">
	<h2 style="color: #333; font-size: 18px; font-weight: bold; margin: 0 0 10px;">
		<?php esc_html_e( 'Shipment Information', 'speedy_econt_shipping' ); ?>
	</h2>

	<table cellspacing="0" cellpadding="0" style="width: 100%; border: 1px solid #e5e5e5; border-radius: 4px;" border="0">
		<tbody>
			<tr>
				<td style="padding: 12px; background-color: #f7f7f7; border-bottom: 1px solid #e5e5e5;">
					<strong><?php esc_html_e( 'Carrier:', 'speedy_econt_shipping' ); ?></strong>
				</td>
				<td style="padding: 12px; background-color: #fff; border-bottom: 1px solid #e5e5e5;">
					<?php echo esc_html( $carrier_name ); ?>
				</td>
			</tr>
			<tr>
				<td style="padding: 12px; background-color: #f7f7f7;">
					<strong><?php esc_html_e( 'Tracking Number:', 'speedy_econt_shipping' ); ?></strong>
				</td>
				<td style="padding: 12px; background-color: #fff;">
					<strong style="font-size: 16px;"><?php echo esc_html( $label->tracking_number ); ?></strong>
				</td>
			</tr>
		</tbody>
	</table>

	<?php if ( $tracking_url ) : ?>
		<p style="margin-top: 15px;">
			<?php esc_html_e( 'You can track your shipment using the link below:', 'speedy_econt_shipping' ); ?>
		</p>
		<div style="margin-top: 15px; text-align: center;">
			<a href="<?php echo esc_url( $tracking_url ); ?>"
			   style="background-color: #0073aa; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;"
			   target="_blank"
			   rel="noopener noreferrer">
				<?php esc_html_e( 'Track Your Shipment', 'speedy_econt_shipping' ); ?>
			</a>
		</div>
	<?php endif; ?>
</div>
