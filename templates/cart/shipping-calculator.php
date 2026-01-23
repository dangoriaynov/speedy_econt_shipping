<?php
/**
 * Cart shipping calculator template.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="sesh-cart-shipping-calculator">
	<h3 class="sesh-calculator-title"><?php esc_html_e( 'Estimate Shipping', 'speedy_econt_shipping' ); ?></h3>

	<div class="sesh-calculator-form">
		<!-- Carrier selection pills -->
		<div class="sesh-carrier-pills">
			<button type="button" class="sesh-pill sesh-pill-active" data-carrier="speedy">
				<?php esc_html_e( 'Speedy', 'speedy_econt_shipping' ); ?>
			</button>
			<button type="button" class="sesh-pill" data-carrier="econt">
				<?php esc_html_e( 'Econt', 'speedy_econt_shipping' ); ?>
			</button>
		</div>

		<!-- Delivery type selection -->
		<div class="sesh-delivery-type">
			<label class="sesh-radio-label">
				<input type="radio" name="calc_delivery_type" value="office" checked>
				<span><?php esc_html_e( 'To Office', 'speedy_econt_shipping' ); ?></span>
			</label>
			<label class="sesh-radio-label">
				<input type="radio" name="calc_delivery_type" value="address">
				<span><?php esc_html_e( 'To Address', 'speedy_econt_shipping' ); ?></span>
			</label>
		</div>

		<!-- City search field -->
		<div class="sesh-city-field">
			<label for="sesh_calc_city">
				<?php esc_html_e( 'Delivery City', 'speedy_econt_shipping' ); ?>
			</label>
			<select id="sesh_calc_city" class="sesh-city-select" style="width: 100%;">
				<option value=""><?php esc_html_e( 'Type to search...', 'speedy_econt_shipping' ); ?></option>
			</select>
		</div>

		<!-- Calculate button -->
		<button type="button" class="sesh-calculate-btn button">
			<?php esc_html_e( 'Calculate Shipping', 'speedy_econt_shipping' ); ?>
		</button>
	</div>

	<!-- Results container -->
	<div class="sesh-calculator-results" style="display:none;">
		<div class="sesh-result-card">
			<div class="sesh-result-header">
				<span class="sesh-result-carrier"></span>
				<span class="sesh-result-price"></span>
			</div>
			<span class="sesh-result-time"></span>
		</div>

		<div class="sesh-free-shipping-hint" style="display:none;"></div>
	</div>

	<!-- Loading state -->
	<div class="sesh-calculator-loading" style="display:none;">
		<span class="spinner is-active"></span>
		<span><?php esc_html_e( 'Calculating...', 'speedy_econt_shipping' ); ?></span>
	</div>

	<!-- Error message -->
	<div class="sesh-calculator-error" style="display:none;"></div>
</div>
