/**
 * Cart Shipping Calculator Module.
 *
 * Provides shipping cost estimation on the cart page.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

(function ($) {
	'use strict';

	/**
	 * Cart Calculator object.
	 */
	const SESH_CartCalculator = {
		/**
		 * Container element.
		 */
		$container: null,

		/**
		 * Selected carrier.
		 */
		selectedCarrier: 'speedy',

		/**
		 * Initialize the calculator.
		 */
		init: function () {
			this.$container = $('.sesh-cart-shipping-calculator');

			if (!this.$container.length) {
				return;
			}

			// Check if config is available.
			if (typeof sesh_cart_config === 'undefined') {
				console.error('SESH Cart Calculator: Configuration not loaded');
				return;
			}

			this.bindEvents();
			this.initCitySearch();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function () {
			// Carrier selection.
			this.$container.on('click', '.sesh-carrier-pills button', this.onCarrierSelect.bind(this));

			// Calculate button.
			this.$container.on('click', '.sesh-calculate-btn', this.onCalculate.bind(this));

			// Recalculate when cart updates.
			$(document.body).on('updated_cart_totals', this.onCartUpdated.bind(this));

			// Update delivery type visibility on init.
			this.updateDeliveryTypeVisibility();
		},

		/**
		 * Initialize city search with Select2.
		 */
		initCitySearch: function () {
			const self = this;

			$('#sesh_calc_city').select2({
				ajax: {
					url: sesh_cart_config.ajax_url,
					type: 'POST',
					dataType: 'json',
					delay: 250,
					data: function (params) {
						return {
							action: 'sesh_search_cities',
							search: params.term,
							carrier: self.selectedCarrier,
							nonce: sesh_cart_config.nonce
						};
					},
					processResults: function (response) {
						if (response.success && response.data && response.data.results) {
							return { results: response.data.results };
						}
						return { results: [] };
					},
					cache: true
				},
				minimumInputLength: 2,
				placeholder: sesh_cart_config.i18n.select_city,
				allowClear: true
			});
		},

		/**
		 * Update delivery type visibility based on selected carrier.
		 *
		 * Econt only supports office delivery, so hide "To Address" option.
		 */
		updateDeliveryTypeVisibility: function () {
			const carrier = this.selectedCarrier;
			const $addressOption = this.$container.find('input[name="calc_delivery_type"][value="address"]').closest('.sesh-radio-label');
			const $addressRadio = this.$container.find('input[name="calc_delivery_type"][value="address"]');

			if (carrier === 'econt') {
				// Hide address option for Econt.
				$addressOption.hide();

				// If address was selected, switch to office.
				if ($addressRadio.is(':checked')) {
					this.$container.find('input[name="calc_delivery_type"][value="office"]').prop('checked', true);
				}
			} else {
				// Show address option for Speedy.
				$addressOption.show();
			}
		},

		/**
		 * Handle carrier selection.
		 *
		 * @param {Event} e Click event.
		 */
		onCarrierSelect: function (e) {
			e.preventDefault();

			const $btn = $(e.currentTarget);
			const carrier = $btn.data('carrier');

			if (carrier === this.selectedCarrier) {
				return;
			}

			// Update selected carrier.
			this.selectedCarrier = carrier;

			// Update button states.
			this.$container.find('.sesh-carrier-pills button')
				.removeClass('sesh-pill-active');
			$btn.addClass('sesh-pill-active');

			// Update delivery type visibility (hide address for Econt).
			this.updateDeliveryTypeVisibility();

			// Clear city selection.
			$('#sesh_calc_city').val(null).trigger('change');

			// Hide previous results.
			this.hideResults();
		},

		/**
		 * Handle calculate button click.
		 *
		 * @param {Event} e Click event.
		 */
		onCalculate: function (e) {
			e.preventDefault();

			const carrier = this.getSelectedCarrier();
			const deliveryType = this.getSelectedDeliveryType();
			const cityId = $('#sesh_calc_city').val();

			// Validate city selection.
			if (!cityId) {
				this.showError(sesh_cart_config.i18n.select_city_first);
				return;
			}

			// Show loading state.
			this.showLoading();

			// Make AJAX request.
			$.ajax({
				url: sesh_cart_config.ajax_url,
				method: 'POST',
				data: {
					action: 'sesh_estimate_shipping',
					carrier: carrier,
					delivery_type: deliveryType,
					city_id: cityId,
					nonce: sesh_cart_config.nonce
				},
				success: this.onCalculateSuccess.bind(this),
				error: this.onCalculateError.bind(this)
			});
		},

		/**
		 * Handle successful calculation.
		 *
		 * @param {Object} response AJAX response.
		 */
		onCalculateSuccess: function (response) {
			this.hideLoading();

			if (!response.success) {
				this.showError(response.data && response.data.message
					? response.data.message
					: sesh_cart_config.i18n.calculation_failed);
				return;
			}

			this.showResults(response.data);
		},

		/**
		 * Handle calculation error.
		 *
		 * @param {Object} xhr XHR object.
		 */
		onCalculateError: function (xhr) {
			this.hideLoading();
			this.showError(sesh_cart_config.i18n.error);
		},

		/**
		 * Show calculation results.
		 *
		 * @param {Object} data Result data.
		 */
		showResults: function (data) {
			const $results = this.$container.find('.sesh-calculator-results');
			const carrier = this.getSelectedCarrier();
			const deliveryType = this.getSelectedDeliveryType();

			// Update carrier label.
			const carrierLabel = carrier.charAt(0).toUpperCase() + carrier.slice(1);
			const deliveryLabel = deliveryType === 'office' ? 'To Office' : 'To Address';
			$results.find('.sesh-result-carrier').text(carrierLabel + ' - ' + deliveryLabel);

			// Update price.
			$results.find('.sesh-result-price').text(data.formatted_price);

			// Update delivery time.
			$results.find('.sesh-result-time').text(data.delivery_time);

			// Update free shipping hint.
			const $hint = $results.find('.sesh-free-shipping-hint');
			if (data.is_free || data.free_shipping_remaining === 0) {
				$hint.text(sesh_cart_config.i18n.free_shipping)
					.addClass('sesh-eligible')
					.show();
			} else if (data.free_shipping_remaining > 0) {
				const message = sesh_cart_config.i18n.add_for_free.replace(
					'%s',
					data.formatted_remaining
				);
				$hint.text(message)
					.removeClass('sesh-eligible')
					.show();
			} else {
				$hint.hide();
			}

			// Hide error if visible.
			this.$container.find('.sesh-calculator-error').hide();

			// Show results.
			$results.slideDown();
		},

		/**
		 * Show error message.
		 *
		 * @param {string} message Error message.
		 */
		showError: function (message) {
			const $error = this.$container.find('.sesh-calculator-error');
			$error.text(message).slideDown();

			// Hide results.
			this.$container.find('.sesh-calculator-results').hide();
		},

		/**
		 * Show loading state.
		 */
		showLoading: function () {
			this.$container.find('.sesh-calculator-loading').show();
			this.$container.find('.sesh-calculator-results').hide();
			this.$container.find('.sesh-calculator-error').hide();
			this.$container.find('.sesh-calculate-btn').prop('disabled', true);
		},

		/**
		 * Hide loading state.
		 */
		hideLoading: function () {
			this.$container.find('.sesh-calculator-loading').hide();
			this.$container.find('.sesh-calculate-btn').prop('disabled', false);
		},

		/**
		 * Hide results.
		 */
		hideResults: function () {
			this.$container.find('.sesh-calculator-results').hide();
			this.$container.find('.sesh-calculator-error').hide();
		},

		/**
		 * Get selected carrier.
		 *
		 * @return {string} Carrier ID.
		 */
		getSelectedCarrier: function () {
			return this.selectedCarrier;
		},

		/**
		 * Get selected delivery type.
		 *
		 * @return {string} Delivery type (office or address).
		 */
		getSelectedDeliveryType: function () {
			return this.$container.find('input[name="calc_delivery_type"]:checked').val();
		},

		/**
		 * Handle cart update.
		 */
		onCartUpdated: function () {
			// Optionally recalculate if city is selected.
			const cityId = $('#sesh_calc_city').val();
			if (cityId && this.$container.find('.sesh-calculator-results').is(':visible')) {
				// Auto-recalculate.
				this.onCalculate({ preventDefault: function () {} });
			}
		}
	};

	/**
	 * Initialize on document ready.
	 */
	$(document).ready(function () {
		SESH_CartCalculator.init();
	});

})(jQuery);
