/**
 * SESH Checkout Module - Main checkout coordination.
 *
 * Coordinates the checkout experience including:
 * - Carrier selection (Speedy/Econt/Address)
 * - Dynamic price updates
 * - Free shipping notifications
 * - WooCommerce checkout integration
 *
 * @package Speedy_Econt_Shipping
 * @since   3.0.0
 */

/* global jQuery, sesh_checkout_params, SESHLocationSelector, SESHPriceDisplay */

(function($, window, document) {
	'use strict';

	/**
	 * SESH Checkout handler.
	 */
	window.SESHCheckout = {
		/**
		 * Configuration from wp_localize_script.
		 */
		config: null,

		/**
		 * Current order price (excluding shipping).
		 */
		currentOrderPrice: 0,

		/**
		 * Currently selected delivery option.
		 */
		selectedDeliveryOption: null,

		/**
		 * Cached delivery prices.
		 */
		deliveryPrices: {},

		/**
		 * Initialize the checkout module.
		 */
		init: function() {
			this.config = window.sesh_checkout_params || {};

			if (!this.config.delivery_options || Object.keys(this.config.delivery_options).length === 0) {
				this.log('No delivery options configured');
				return;
			}

			this.log('Initializing checkout module');
			this.cacheDeliveryPrices();
			this.bindEvents();
			this.setDefaultDeliveryOption();
		},

		/**
		 * Cache delivery prices for quick access.
		 */
		cacheDeliveryPrices: function() {
			var self = this;
			$.each(this.config.delivery_options, function(key, option) {
				self.deliveryPrices[option.id] = parseFloat(option.shipping);
			});
			this.log('Cached delivery prices', this.deliveryPrices);
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			var self = this;

			// WooCommerce checkout update event (replaces polling).
			$(document.body).on('updated_checkout', function() {
				self.log('Checkout updated');
				self.handleCheckoutUpdate();
			});

			// Carrier selection change.
			$(document).on('change', this.getShippingSelector(), function() {
				self.log('Carrier selection changed');
				self.handleCarrierChange();
			});

			// Phone number change (show shipping options).
			$('#billing_phone').on('change blur', function() {
				self.handlePhoneChange();
			});

			// Initial setup on document ready.
			$(document).ready(function() {
				self.log('Document ready - initializing');
				self.handleCheckoutUpdate();
			});
		},

		/**
		 * Set default delivery option on page load.
		 */
		setDefaultDeliveryOption: function() {
			var checkedOption = $(this.getShippingSelector() + ':checked');

			if (checkedOption.length === 0) {
				// No option selected, set default.
				var defaultOption = this.config.default_shipping_method;
				if (defaultOption && this.config.delivery_options[defaultOption]) {
					this.selectedDeliveryOption = this.config.delivery_options[defaultOption];
					$('#' + this.selectedDeliveryOption.id).prop('checked', true);
				} else {
					// Use first available option.
					var firstKey = Object.keys(this.config.delivery_options)[0];
					this.selectedDeliveryOption = this.config.delivery_options[firstKey];
					$('#' + this.selectedDeliveryOption.id).prop('checked', true);
				}
			} else {
				this.updateSelectedDeliveryOption();
			}

			this.log('Default delivery option set', this.selectedDeliveryOption);
		},

		/**
		 * Handle WooCommerce checkout update event.
		 */
		handleCheckoutUpdate: function() {
			this.updateCurrentPrice();
			this.populateDeliveryOptions();
			this.updateSelectedDeliveryOption();
			this.updateFinalPrice();
			this.showFreeDeliveryMessage();
		},

		/**
		 * Handle carrier selection change.
		 */
		handleCarrierChange: function() {
			this.updateSelectedDeliveryOption();
			this.updateFinalPrice();
			this.showFreeDeliveryMessage();
			this.toggleCarrierFields();
		},

		/**
		 * Handle phone number field change.
		 */
		handlePhoneChange: function() {
			var phoneValue = $('#billing_phone').val();
			if (phoneValue && phoneValue.length > 0) {
				$(this.config.selectors.shipping_to_field).show('slow');
			}
		},

		/**
		 * Update the currently selected delivery option.
		 */
		updateSelectedDeliveryOption: function() {
			var checkedInput = $(this.getShippingSelector() + ':checked');
			var selectedValue = checkedInput.val();

			if (!selectedValue) {
				this.log('No delivery option selected');
				return;
			}

			// Find matching delivery option.
			var found = null;
			$.each(this.config.delivery_options, function(key, option) {
				if (option.name === selectedValue || option.id === selectedValue || option.label === selectedValue) {
					found = option;
					return false; // break loop
				}
			});

			if (found) {
				this.selectedDeliveryOption = found;
				this.log('Selected delivery option updated', found);
			} else {
				this.log('Could not find matching delivery option for: ' + selectedValue);
			}
		},

		/**
		 * Update current order price from DOM.
		 */
		updateCurrentPrice: function() {
			var $priceElement = $('.order-total .amount').not('.secondary-currency .amount').first();

			if ($priceElement.length === 0) {
				$priceElement = $('.cart-total .amount').not('.secondary-currency .amount').first();
			}

			if ($priceElement.length > 0) {
				var priceText = $priceElement.text();
				priceText = priceText.replace(',', '.').replace(/[^0-9.]/g, '');
				var price = parseFloat(priceText);

				if (!isNaN(price) && price > 0) {
					this.currentOrderPrice = price;
					this.log('Current price updated', price);
				}
			}
		},

		/**
		 * Populate delivery option labels with prices.
		 */
		populateDeliveryOptions: function() {
			var self = this;

			$.each(this.config.delivery_options, function(key, option) {
				var deliveryPrice = self.calculateDeliveryPrice(option);
				self.deliveryPrices[option.id] = deliveryPrice;

				var suffix = self.getDeliveryPriceSuffix(option, deliveryPrice);
				var labelText = option.label + (suffix ? ' (' + suffix + ')' : '');

				$('.woocommerce-input-wrapper > label[for="' + option.id + '"]').text(labelText);
			});

			this.log('Delivery options populated');
		},

		/**
		 * Calculate delivery price for an option.
		 *
		 * @param {Object} option Delivery option.
		 * @return {number} Calculated price.
		 */
		calculateDeliveryPrice: function(option) {
			if (this.isFreeDelivery(option)) {
				return 0;
			}
			return parseFloat(option.shipping);
		},

		/**
		 * Check if delivery is free for an option.
		 *
		 * @param {Object} option Delivery option.
		 * @return {boolean} True if free delivery applies.
		 */
		isFreeDelivery: function(option) {
			var freeFrom = parseFloat(option.free_from);
			return freeFrom !== -1 && this.currentOrderPrice >= freeFrom;
		},

		/**
		 * Get delivery price suffix for label.
		 *
		 * @param {Object} option Delivery option.
		 * @param {number} price Calculated price.
		 * @return {string} Suffix text.
		 */
		getDeliveryPriceSuffix: function(option, price) {
			if (price === 0) {
				var freeSuffix = this.config.free_shipping_suffix || '';
				return freeSuffix ? freeSuffix : this.config.i18n.free;
			}
			return '+' + price.toFixed(2) + ' ' + this.config.currency_symbol;
		},

		/**
		 * Update final price display.
		 */
		updateFinalPrice: function() {
			if (!this.selectedDeliveryOption) {
				return;
			}

			var deliveryPrice = this.deliveryPrices[this.selectedDeliveryOption.id];

			if (this.config.calculate_final_price) {
				this.updateFinalPriceWithShipping(deliveryPrice);
			} else {
				this.updateFinalPriceWithoutShipping();
			}
		},

		/**
		 * Update final price including shipping.
		 *
		 * @param {number} deliveryPrice Delivery price.
		 */
		updateFinalPriceWithShipping: function(deliveryPrice) {
			// Update delivery label.
			$('.cart-subtotal th').last().text(this.config.i18n.delivery);

			// Update delivery price.
			var delivPriceFormatted = deliveryPrice.toFixed(2) + ' ' + this.config.currency_symbol;
			$(this.config.delivery_price_selector).last().text(delivPriceFormatted);

			// Update total price.
			var totalPrice = (this.currentOrderPrice + deliveryPrice).toFixed(2) + ' ' + this.config.currency_symbol;
			this.setCustomShippingPrice(totalPrice);
		},

		/**
		 * Update final price without shipping calculation.
		 */
		updateFinalPriceWithoutShipping: function() {
			var priceFormatted = this.currentOrderPrice.toFixed(2) + ' ' + this.config.currency_symbol;
			this.setCustomShippingPrice(priceFormatted);
		},

		/**
		 * Set custom shipping price in DOM.
		 *
		 * @param {string} customPrice Formatted price string.
		 */
		setCustomShippingPrice: function(customPrice) {
			var $origPriceElem = $('.order-total .woocommerce-Price-amount.amount').not('.secondary-currency *').last();
			$origPriceElem.hide();

			var $customPriceElem = $('#sesh_custom_price');
			if ($customPriceElem.length === 0) {
				$origPriceElem.before('<span id="sesh_custom_price" class="woocommerce-Price-amount amount">' + customPrice + '</span>');
			} else {
				$customPriceElem.text(customPrice);
			}
		},

		/**
		 * Show free delivery message.
		 */
		showFreeDeliveryMessage: function() {
			if (!this.selectedDeliveryOption) {
				return;
			}

			// Check if messages are enabled for this carrier.
			var showMessages = this.config.show_store_messages || [];
			if (showMessages.indexOf(this.selectedDeliveryOption.name) === -1) {
				this.removeFreeDeliveryMessage();
				return;
			}

			if (this.currentOrderPrice === 0) {
				this.removeFreeDeliveryMessage();
				return;
			}

			var $msgContainer = $('#sesh_deliv_msg');
			if ($msgContainer.length === 0) {
				var $wrapper = $('.woocommerce-notices-wrapper').first();
				$wrapper.append('<div role="alert"><div id="sesh_deliv_msg" class="message-inner" style="display: block;"></div></div>');
				$msgContainer = $('#sesh_deliv_msg');
			}

			var labelBold = '<b style="font-weight:900;">' + this.selectedDeliveryOption.label + '</b>';
			var msg = '';

			$msgContainer.removeClass().hide().css('background-color', '');

			if (this.isFreeDelivery(this.selectedDeliveryOption)) {
				$msgContainer.addClass('woocommerce-message');
				msg = this.config.i18n.congrats_free_delivery.replace('%s', labelBold);
			} else if (parseFloat(this.selectedDeliveryOption.free_from) !== -1) {
				$msgContainer.addClass('woocommerce-message');
				$msgContainer.css('background-color', '#e2401c');

				var leftTillFree = parseFloat(this.selectedDeliveryOption.free_from) - this.currentOrderPrice;
				var leftFormatted = '<span class="woocommerce-Price-amount amount">' + leftTillFree.toFixed(2) + '&nbsp;<span class="woocommerce-Price-currencySymbol">' + this.config.currency_symbol + '</span></span>';

				msg = this.config.i18n.left_till_free.replace('%s', leftFormatted) + ' ' + labelBold + '! ';
				msg += '<a class="button" href="' + this.config.shop_url + '">' + this.config.i18n.to_shop + '</a>';
			} else {
				$msgContainer.addClass('woocommerce-message');
				$msgContainer.css('background-color', 'darkgray');
				msg = this.config.i18n.no_free_shipping.replace('%s', labelBold);
			}

			$msgContainer.html(msg).show();
		},

		/**
		 * Remove free delivery message.
		 */
		removeFreeDeliveryMessage: function() {
			$('#sesh_deliv_msg').parent().remove();
		},

		/**
		 * Toggle carrier-specific fields visibility.
		 */
		toggleCarrierFields: function() {
			if (!this.selectedDeliveryOption) {
				return;
			}

			var selectedCarrier = this.selectedDeliveryOption.name;
			var selectors = this.config.selectors;

			// Hide all carrier fields.
			this.hideAllCarrierFields();

			// Clear address field.
			$(selectors.address_office_sel).val('');

			// Show fields for selected carrier.
			if (selectedCarrier === 'speedy') {
				$(selectors.speedy_region_field).show('slow');
				$(selectors.speedy_city_field).show('slow');
			} else if (selectedCarrier === 'econt') {
				$(selectors.econt_region_field).show('slow');
				$(selectors.econt_city_field).show('slow');
			} else if (selectedCarrier === 'address') {
				$(selectors.address_region_field).show('slow');
				$(selectors.address_city_field).show('slow');
				$(selectors.address_office_field).show('slow');
			}

			this.log('Toggled carrier fields for: ' + selectedCarrier);
		},

		/**
		 * Hide all carrier-specific fields.
		 */
		hideAllCarrierFields: function() {
			var selectors = this.config.selectors;
			var fieldsToHide = [
				selectors.speedy_region_field,
				selectors.speedy_city_field,
				selectors.speedy_office_field,
				selectors.econt_region_field,
				selectors.econt_city_field,
				selectors.econt_office_field
			];

			$(fieldsToHide.join(',')).hide();
		},

		/**
		 * Get shipping selector string.
		 *
		 * @return {string} jQuery selector.
		 */
		getShippingSelector: function() {
			return 'input[name="' + this.config.shipping_to_id + '"]';
		},

		/**
		 * Log debug message.
		 *
		 * @param {string} message Log message.
		 * @param {*} data Optional data to log.
		 */
		log: function(message, data) {
			if (window.console && window.console.log) {
				var logMsg = '[SESH Checkout] ' + message;
				if (data !== undefined) {
					console.log(logMsg, data);
				} else {
					console.log(logMsg);
				}
			}
		}
	};

	// Initialize on DOM ready.
	$(document).ready(function() {
		SESHCheckout.init();
	});

})(jQuery, window, document);
