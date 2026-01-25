<?php
/**
 * Admin template: Shipping Label Meta Box
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 *
 * Available variables:
 * @var WC_Order $order Order object.
 * @var object|null $label Label object from database.
 */

defined( 'ABSPATH' ) || exit;

// Validate order for label generation.
$generator  = new SESH_Label_Generator(
	$this->settings,
	SESH_Plugin::instance()->get_database(),
	SESH_Plugin::instance()->get_speedy_api(),
	SESH_Plugin::instance()->get_econt_api()
);
$validation = $generator->validate_order( $order );
?>

<div class="sesh-label-meta-box">
	<?php if ( $label && ! empty( $label->tracking_number ) ) : ?>
		<!-- Label exists - Show label info -->
		<div class="sesh-label-info">
			<!-- Carrier Logo/Name -->
			<div class="sesh-label-carrier">
				<span class="sesh-carrier-logo sesh-carrier-<?php echo esc_attr( $label->carrier ); ?>">
					<?php echo esc_html( ucfirst( $label->carrier ) ); ?>
				</span>
			</div>

			<!-- Tracking Number -->
			<div class="sesh-label-field">
				<label><?php esc_html_e( 'Tracking Number:', 'speedy_econt_shipping' ); ?></label>
				<div class="sesh-tracking-display">
					<strong class="sesh-tracking-number"><?php echo esc_html( $label->tracking_number ); ?></strong>
					<button type="button" class="button button-small sesh-copy-tracking" data-tracking="<?php echo esc_attr( $label->tracking_number ); ?>">
						<span class="dashicons dashicons-admin-page"></span>
						<?php esc_html_e( 'Copy', 'speedy_econt_shipping' ); ?>
					</button>
				</div>
			</div>

			<!-- Status -->
			<div class="sesh-label-field">
				<label><?php esc_html_e( 'Status:', 'speedy_econt_shipping' ); ?></label>
				<span class="sesh-label-status sesh-status-<?php echo esc_attr( $label->status ); ?>">
					<?php echo esc_html( ucfirst( $label->status ) ); ?>
				</span>
			</div>

			<!-- Created Date -->
			<div class="sesh-label-field">
				<label><?php esc_html_e( 'Created:', 'speedy_econt_shipping' ); ?></label>
				<span class="sesh-label-date">
					<?php
					echo esc_html(
						date_i18n(
							get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
							strtotime( $label->created_at )
						)
					);
					?>
				</span>
			</div>

			<!-- Action Buttons -->
			<div class="sesh-label-actions">
				<?php
				$download_url = wp_nonce_url(
					admin_url( 'admin-ajax.php?action=sesh_download_label&label_id=' . $label->id ),
					'sesh_download_label',
					'security'
				);

				$print_url = wp_nonce_url(
					admin_url( 'admin-ajax.php?action=sesh_print_label&label_id=' . $label->id ),
					'sesh_print_label',
					'security'
				);
				?>
				<a href="<?php echo esc_url( $download_url ); ?>" class="button button-primary" target="_blank">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Download PDF', 'speedy_econt_shipping' ); ?>
				</a>

				<a href="<?php echo esc_url( $print_url ); ?>" class="button" target="_blank">
					<span class="dashicons dashicons-printer"></span>
					<?php esc_html_e( 'Print', 'speedy_econt_shipping' ); ?>
				</a>

				<?php if ( 'cancelled' !== $label->status ) : ?>
					<button type="button" class="button sesh-regenerate-label" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Regenerate', 'speedy_econt_shipping' ); ?>
					</button>

					<button type="button" class="button sesh-cancel-label" data-label-id="<?php echo esc_attr( $label->id ); ?>">
						<span class="dashicons dashicons-no"></span>
						<?php esc_html_e( 'Cancel', 'speedy_econt_shipping' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>

	<?php else : ?>
		<!-- No label - Show generate option -->
		<div class="sesh-no-label">
			<?php if ( ! $validation->is_valid() ) : ?>
				<!-- Validation errors -->
				<div class="sesh-validation-errors">
					<p class="sesh-error-intro">
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'Cannot generate label. Please fix the following issues:', 'speedy_econt_shipping' ); ?>
					</p>
					<ul class="sesh-error-list">
						<?php foreach ( $validation->get_errors() as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php else : ?>
				<!-- Generate button -->
				<p class="sesh-no-label-message">
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'No shipping label generated yet.', 'speedy_econt_shipping' ); ?>
				</p>
			<?php endif; ?>

			<div class="sesh-label-actions">
				<button
					type="button"
					class="button button-primary button-large sesh-generate-label"
					data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
					<?php disabled( ! $validation->is_valid() ); ?>
				>
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Generate Label', 'speedy_econt_shipping' ); ?>
				</button>
			</div>
		</div>
	<?php endif; ?>

	<!-- Loading spinner (hidden by default) -->
	<div class="sesh-loading" style="display: none;">
		<span class="spinner is-active"></span>
		<span class="sesh-loading-text"></span>
	</div>
</div>
