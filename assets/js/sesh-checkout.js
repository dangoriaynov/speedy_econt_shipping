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

			// Listen for real-time price calculation from location selector.
			$(document.body).on('sesh_price_calculated', function(e, carrier, priceData) {
				self.handleRealTimePriceUpdate(carrier, priceData);
			});

			// Validate before checkout submission.
			$(document.body).on('checkout_place_order', function() {
				return self.validateBeforeSubmit();
			});

			// Initial setup on document ready.
			$(document).ready(function() {
				self.log('Document ready - initializing');
				self.handleCheckoutUpdate();
			});
		},

		/**
		 * Validate required fields before allowing checkout submission.
		 *
		 * @return {boolean} True if valid, false to prevent submission.
		 */
		validateBeforeSubmit: function() {
			var carrier = $('#sesh_carrier').val();
			var cityId = $('#sesh_city_id').val();
			var officeId = $('#sesh_office_id').val();
			var deliveryType = $('#sesh_delivery_type').val();

			// Remove any previous validation errors.
			$('.sesh-validation-error-notice').remove();

			// If no carrier, let WooCommerce handle it.
			if (!carrier) {
				return true;
			}

			var errors = [];

			// For Speedy/Econt, city is required.
			if ((carrier === 'speedy' || carrier === 'econt') && !cityId) {
				errors.push(this.config.i18n.select_city || 'Please select a city');
				this.highlightField('#' + carrier + '_city');
			}

			// For office delivery, office is required.
			if (deliveryType === 'office' && !officeId) {
				errors.push(this.config.i18n.select_office || 'Please select an office');
				this.highlightField('#' + carrier + '_office');
			}

			// If errors, show them and prevent submission.
			if (errors.length > 0) {
				var errorHtml = '<div class="woocommerce-error sesh-validation-error-notice" role="alert" aria-live="assertive" tabindex="-1"><ul>';
				errors.forEach(function(error) {
					errorHtml += '<li>' + error + '</li>';
				});
				errorHtml += '</ul></div>';

				// Insert error at top of checkout form.
				$('.woocommerce-checkout').prepend(errorHtml);

				// Focus the error div for screen readers.
				$('.sesh-validation-error-notice').focus();

				// Scroll to error.
				$('html, body').animate({
					scrollTop: $('.sesh-validation-error-notice').offset().top - 100
				}, 500);

				return false; // Prevent form submission.
			}

			return true; // Allow submission.
		},

		/**
		 * Highlight a field with an error state.
		 *
		 * @param {string} selector Field selector.
		 */
		highlightField: function(selector) {
			$(selector).addClass('sesh-field-error');

			// Also highlight Select2 container if present.
			$(selector).next('.select2-container').find('.select2-selection').addClass('sesh-field-error');

			// Remove highlight after 3 seconds or when field changes.
			setTimeout(function() {
				$(selector).removeClass('sesh-field-error');
				$(selector).next('.select2-container').find('.select2-selection').removeClass('sesh-field-error');
			}, 3000);

			$(selector).one('change', function() {
				$(this).removeClass('sesh-field-error');
				$(this).next('.select2-container').find('.select2-selection').removeClass('sesh-field-error');
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
			this.updateHiddenCarrierFields();
			this.focusNextField();
		},

		/**
		 * Focus the next relevant field after carrier selection.
		 */
		focusNextField: function() {
			if (!this.selectedDeliveryOption) {
				return;
			}

			var carrier = this.selectedDeliveryOption.name;
			var $nextField = null;

			// Focus city field for Speedy/Econt.
			if (carrier === 'speedy') {
				$nextField = $('#speedy_city');
			} else if (carrier === 'econt') {
				$nextField = $('#econt_city');
			}

			// Focus the field after a short delay to ensure it's visible.
			if ($nextField && $nextField.length) {
				setTimeout(function() {
					$nextField.select2('open');
				}, 300);
			}
		},

		/**
		 * Update hidden form fields with current carrier selection.
		 */
		updateHiddenCarrierFields: function() {
			if (!this.selectedDeliveryOption) {
				return;
			}

			var carrier = this.selectedDeliveryOption.name;
			$('#sesh_carrier').val(carrier);

			// Set delivery type based on carrier.
			if (carrier === 'address') {
				$('#sesh_delivery_type').val('address');
				// Clear office fields for address delivery.
				$('#sesh_office_id').val('');
				$('#sesh_office_name').val('');
				$('#sesh_office_address').val('');
			} else {
				$('#sesh_delivery_type').val('office');
			}

			this.log('Hidden carrier fields updated: ' + carrier);
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
				$(selectors.speedy_region_field).show('slow').attr('aria-hidden', 'false');
				$(selectors.speedy_city_field).show('slow').attr('aria-hidden', 'false');
				// Set tabindex on select elements.
				$(selectors.speedy_region_field + ' select, ' + selectors.speedy_city_field + ' select').attr('tabindex', '0');
			} else if (selectedCarrier === 'econt') {
				$(selectors.econt_region_field).show('slow').attr('aria-hidden', 'false');
				$(selectors.econt_city_field).show('slow').attr('aria-hidden', 'false');
				// Set tabindex on select elements.
				$(selectors.econt_region_field + ' select, ' + selectors.econt_city_field + ' select').attr('tabindex', '0');
			} else if (selectedCarrier === 'address') {
				$(selectors.address_region_field).show('slow').attr('aria-hidden', 'false');
				$(selectors.address_city_field).show('slow').attr('aria-hidden', 'false');
				$(selectors.address_office_field).show('slow').attr('aria-hidden', 'false');
				// Set tabindex on select elements.
				$(selectors.address_region_field + ' select, ' + selectors.address_city_field + ' select, ' + selectors.address_office_field + ' select').attr('tabindex', '0');
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

			// Hide fields and set aria-hidden + tabindex for accessibility.
			$(fieldsToHide.join(',')).hide().attr('aria-hidden', 'true');
			$(fieldsToHide.join(',') + ' select').attr('tabindex', '-1');
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
		 * Handle real-time price update from location selector.
		 *
		 * @param {string} carrier   Carrier name.
		 * @param {Object} priceData Price data from AJAX response.
		 */
		handleRealTimePriceUpdate: function(carrier, priceData) {
			this.log('Real-time price update received', { carrier: carrier, priceData: priceData });

			// Update cached delivery price for this carrier.
			var option = this.config.delivery_options[carrier];
			if (!option) {
				return;
			}

			// Update the cached price.
			this.deliveryPrices[option.id] = priceData.is_free ? 0 : priceData.price;

			// If this is the currently selected carrier, update the display.
			if (this.selectedDeliveryOption && this.selectedDeliveryOption.name === carrier) {
				this.updateFinalPriceWithRealTime(priceData);
			}
		},

		/**
		 * Update final price display with real-time API price.
		 *
		 * @param {Object} priceData Price data from AJAX response.
		 */
		updateFinalPriceWithRealTime: function(priceData) {
			var deliveryPrice = priceData.is_free ? 0 : priceData.price;

			// Update delivery label.
			$('.cart-subtotal th').last().text(this.config.i18n.delivery);

			// Update delivery price display.
			var delivPriceFormatted;
			if (priceData.is_free) {
				delivPriceFormatted = '<span class="sesh-free-badge">' + this.config.i18n.free + '</span>';
			} else {
				delivPriceFormatted = priceData.formatted_price;
				if (priceData.is_fallback) {
					delivPriceFormatted += ' <span class="sesh-price-estimated">' + (this.config.i18n.estimated || '(estimated)') + '</span>';
				}
			}
			$(this.config.delivery_price_selector).last().html(delivPriceFormatted);

			// Update total price.
			var totalPrice = this.currentOrderPrice + deliveryPrice;
			var totalFormatted = totalPrice.toFixed(2) + ' ' + this.config.currency_symbol;
			this.setCustomShippingPrice(totalFormatted);

			this.log('Final price updated with real-time data', { delivery: deliveryPrice, total: totalPrice });
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
