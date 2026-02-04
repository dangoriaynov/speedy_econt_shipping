/**
 * SESH Price Display Module - Handles price formatting and display.
 *
 * Manages consistent price formatting across the checkout page.
 * Provides utilities for currency display and price calculations.
 *
 * @package Speedy_Econt_Shipping
 * @since   3.0.0
 */

/* global jQuery, sesh_checkout_params */

(function($, window, document) {
	'use strict';

	/**
	 * SESH Price Display handler.
	 */
	window.SESHPriceDisplay = {
		/**
		 * Configuration from wp_localize_script.
		 */
		config: null,

		/**
		 * Initialize the price display module.
		 */
		init: function() {
			this.config = window.sesh_checkout_params || {};
			this.log('Initializing price display module');
		},

		/**
		 * Format price with currency symbol.
		 *
		 * @param {number} price Price value.
		 * @param {number} decimals Number of decimal places (default: 2).
		 * @return {string} Formatted price string.
		 */
		formatPrice: function(price, decimals) {
			decimals = decimals !== undefined ? decimals : 2;
			var formatted = parseFloat(price).toFixed(decimals);
			return formatted + ' ' + this.config.currency_symbol;
		},

		/**
		 * Format price as WooCommerce HTML.
		 *
		 * @param {number} price Price value.
		 * @param {number} decimals Number of decimal places (default: 2).
		 * @return {string} HTML string.
		 */
		formatPriceHTML: function(price, decimals) {
			decimals = decimals !== undefined ? decimals : 2;
			var formatted = parseFloat(price).toFixed(decimals);

			return '<span class="woocommerce-Price-amount amount">' +
				formatted +
				'&nbsp;<span class="woocommerce-Price-currencySymbol">' +
				this.config.currency_symbol +
				'</span></span>';
		},

		/**
		 * Parse price from text string.
		 *
		 * Extracts numeric value from formatted price string.
		 *
		 * @param {string} priceText Price text (e.g., "12,50 лв").
		 * @return {number} Parsed price value.
		 */
		parsePrice: function(priceText) {
			if (typeof priceText !== 'string') {
				return 0;
			}

			// Remove currency symbols and thousand separators.
			var cleaned = priceText.replace(',', '.').replace(/[^0-9.]/g, '');
			var parsed = parseFloat(cleaned);

			return isNaN(parsed) ? 0 : parsed;
		},

		/**
		 * Get price element from DOM.
		 *
		 * @param {string} selector jQuery selector.
		 * @return {jQuery} Price element.
		 */
		getPriceElement: function(selector) {
			var $element = $(selector).not('.secondary-currency .amount').first();
			return $element;
		},

		/**
		 * Extract price from DOM element.
		 *
		 * @param {string} selector jQuery selector.
		 * @return {number} Extracted price value.
		 */
		extractPrice: function(selector) {
			var $element = this.getPriceElement(selector);
			if ($element.length === 0) {
				return 0;
			}

			var priceText = $element.text();
			return this.parsePrice(priceText);
		},

		/**
		 * Update price in DOM element.
		 *
		 * @param {string} selector jQuery selector.
		 * @param {number} price Price value.
		 * @param {boolean} useHTML Use HTML formatting (default: true).
		 */
		updatePrice: function(selector, price, useHTML) {
			useHTML = useHTML !== undefined ? useHTML : true;

			var formatted = useHTML ? this.formatPriceHTML(price) : this.formatPrice(price);
			$(selector).html(formatted);

			this.log('Updated price: ' + selector + ' = ' + price);
		},

		/**
		 * Calculate percentage of price.
		 *
		 * @param {number} price Base price.
		 * @param {number} percentage Percentage value.
		 * @return {number} Calculated value.
		 */
		calculatePercentage: function(price, percentage) {
			return (price * percentage) / 100;
		},

		/**
		 * Add prices together.
		 *
		 * @param {...number} prices Price values to add.
		 * @return {number} Sum of prices.
		 */
		addPrices: function() {
			var sum = 0;
			for (var i = 0; i < arguments.length; i++) {
				sum += parseFloat(arguments[i]) || 0;
			}
			return sum;
		},

		/**
		 * Subtract prices.
		 *
		 * @param {number} price1 First price.
		 * @param {number} price2 Second price.
		 * @return {number} Difference.
		 */
		subtractPrices: function(price1, price2) {
			return parseFloat(price1) - parseFloat(price2);
		},

		/**
		 * Compare prices for equality.
		 *
		 * Handles floating point comparison with epsilon.
		 *
		 * @param {number} price1 First price.
		 * @param {number} price2 Second price.
		 * @param {number} epsilon Tolerance (default: 0.01).
		 * @return {boolean} True if prices are equal.
		 */
		comparePrices: function(price1, price2, epsilon) {
			epsilon = epsilon !== undefined ? epsilon : 0.01;
			return Math.abs(parseFloat(price1) - parseFloat(price2)) < epsilon;
		},

		/**
		 * Validate price value.
		 *
		 * @param {*} value Value to validate.
		 * @return {boolean} True if valid price.
		 */
		isValidPrice: function(value) {
			var price = parseFloat(value);
			return !isNaN(price) && price >= 0;
		},

		/**
		 * Get currency symbol.
		 *
		 * @return {string} Currency symbol.
		 */
		getCurrencySymbol: function() {
			return this.config.currency_symbol || '';
		},

		/**
		 * Format price range.
		 *
		 * @param {number} minPrice Minimum price.
		 * @param {number} maxPrice Maximum price.
		 * @return {string} Formatted range (e.g., "10.00 - 20.00 лв").
		 */
		formatPriceRange: function(minPrice, maxPrice) {
			var min = parseFloat(minPrice).toFixed(2);
			var max = parseFloat(maxPrice).toFixed(2);
			return min + ' - ' + max + ' ' + this.config.currency_symbol;
		},

		/**
		 * Format discount.
		 *
		 * @param {number} originalPrice Original price.
		 * @param {number} discountedPrice Discounted price.
		 * @return {Object} Formatted discount object.
		 */
		formatDiscount: function(originalPrice, discountedPrice) {
			var amount = this.subtractPrices(originalPrice, discountedPrice);
			var percentage = (amount / originalPrice) * 100;

			return {
				amount: amount,
				percentage: percentage.toFixed(0),
				formatted: this.formatPrice(amount)
			};
		},

		/**
		 * Create a loading spinner element.
		 *
		 * @param {string} size Spinner size (sm, md, lg).
		 * @return {string} HTML string for spinner.
		 */
		createSpinner: function(size) {
			size = size || 'md';
			var sizeClass = 'sesh-spinner';
			if (size === 'sm') {
				sizeClass += ' sesh-spinner--sm';
			} else if (size === 'lg') {
				sizeClass += ' sesh-spinner--lg';
			}
			return '<span class="' + sizeClass + '"></span>';
		},

		/**
		 * Create a loading indicator with text.
		 *
		 * @param {string} text Loading text.
		 * @param {string} size Spinner size.
		 * @return {string} HTML string.
		 */
		createLoadingIndicator: function(text, size) {
			return '<div class="sesh-price-loading">' +
				this.createSpinner(size) + ' ' +
				'<span class="sesh-loading-text">' + (text || 'Loading...') + '</span>' +
				'</div>';
		},

		/**
		 * Create price breakdown display.
		 *
		 * @param {Object} breakdown Breakdown data { base_price, discount, final_price }.
		 * @return {string} HTML string.
		 */
		createPriceBreakdown: function(breakdown) {
			if (!breakdown) {
				return '';
			}

			var html = '<div class="sesh-price-breakdown-details">';

			if (breakdown.base_price > 0) {
				html += '<div class="sesh-breakdown-row">';
				html += '<span class="sesh-breakdown-label">Base price:</span>';
				html += '<span class="sesh-breakdown-value">' + this.formatPrice(breakdown.base_price) + '</span>';
				html += '</div>';
			}

			if (breakdown.discount > 0) {
				html += '<div class="sesh-breakdown-row sesh-breakdown-row--discount">';
				html += '<span class="sesh-breakdown-label">Discount:</span>';
				html += '<span class="sesh-breakdown-value">-' + this.formatPrice(breakdown.discount) + '</span>';
				html += '</div>';
			}

			html += '<div class="sesh-breakdown-row sesh-breakdown-row--total">';
			html += '<span class="sesh-breakdown-label">Total:</span>';
			html += '<span class="sesh-breakdown-value">' + this.formatPrice(breakdown.final_price) + '</span>';
			html += '</div>';

			html += '</div>';

			return html;
		},

		/**
		 * Create a "price may vary" indicator.
		 *
		 * @param {string} text Optional custom text.
		 * @return {string} HTML string.
		 */
		createEstimatedIndicator: function(text) {
			text = text || this.config.i18n.estimated || '(estimated)';
			return '<span class="sesh-price-estimated">' + text + '</span>';
		},

		/**
		 * Create a free shipping badge.
		 *
		 * @param {string} text Optional custom text.
		 * @return {string} HTML string.
		 */
		createFreeBadge: function(text) {
			text = text || this.config.i18n.free || 'FREE';
			return '<span class="sesh-free-badge">' + text + '</span>';
		},

		/**
		 * Log debug message.
		 *
		 * @param {string} message Log message.
		 */
		log: function(message) {
			if (window.console && window.console.log) {
				console.log('[SESH Price Display] ' + message);
			}
		}
	};

	// Initialize on DOM ready.
	$(document).ready(function() {
		SESHPriceDisplay.init();
	});

})(jQuery, window, document);
