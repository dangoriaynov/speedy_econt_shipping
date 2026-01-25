/**
 * SESH City Persistence Module.
 *
 * Stores the selected city when switching between delivery methods
 * so users don't have to re-select the city each time.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.1.0
 */

/* global jQuery, sesh_checkout_params */

(function($, window, document) {
	'use strict';

	/**
	 * City Persistence handler.
	 */
	window.SESHCityPersistence = {
		/**
		 * Storage key for sessionStorage.
		 */
		storageKey: 'sesh_selected_city',

		/**
		 * Configuration from wp_localize_script.
		 */
		config: null,

		/**
		 * Initialize the city persistence module.
		 */
		init: function() {
			this.config = window.sesh_checkout_params || {};

			if (!this.isStorageAvailable()) {
				this.log('sessionStorage not available, city persistence disabled');
				return;
			}

			this.log('Initializing city persistence module');
			this.bindEvents();
			this.restoreCityOnLoad();
		},

		/**
		 * Check if sessionStorage is available.
		 *
		 * @return {boolean} True if available.
		 */
		isStorageAvailable: function() {
			try {
				var test = '__sesh_storage_test__';
				window.sessionStorage.setItem(test, test);
				window.sessionStorage.removeItem(test);
				return true;
			} catch (e) {
				return false;
			}
		},

		/**
		 * Bind event listeners.
		 */
		bindEvents: function() {
			var self = this;
			var selectors = this.config.selectors || {};

			// Save city when Speedy city is selected.
			$(document).on('change', selectors.speedy_city_sel, function() {
				self.saveCity('speedy', $(this));
			});

			// Save city when Econt city is selected.
			$(document).on('change', selectors.econt_city_sel, function() {
				self.saveCity('econt', $(this));
			});

			// Save city when billing city is changed (for address delivery).
			$(document).on('change blur', selectors.address_city_sel, function() {
				self.saveCity('address', $(this));
			});

			// Restore city when shipping method changes.
			$(document).on('change', 'input[name^="shipping_method"]', function() {
				// Small delay to allow fields to be shown/hidden.
				setTimeout(function() {
					self.restoreCity();
				}, 100);
			});

			// Restore city after WooCommerce updates checkout.
			$(document.body).on('updated_checkout', function() {
				setTimeout(function() {
					self.restoreCity();
				}, 100);
			});
		},

		/**
		 * Save selected city to sessionStorage.
		 *
		 * @param {string} carrier Carrier name (speedy, econt, address).
		 * @param {jQuery} $field  The city field jQuery element.
		 */
		saveCity: function(carrier, $field) {
			var cityData = {
				carrier: carrier,
				text: '',
				id: '',
				timestamp: Date.now()
			};

			// Get value based on field type.
			if ($field.is('select')) {
				var $selected = $field.find('option:selected');
				cityData.id = $field.val();
				cityData.text = $selected.text();
			} else {
				cityData.text = $field.val();
				cityData.id = $field.val();
			}

			// Only save if we have a value.
			if (cityData.text && cityData.text.trim() !== '') {
				try {
					window.sessionStorage.setItem(this.storageKey, JSON.stringify(cityData));
					this.log('Saved city: ' + JSON.stringify(cityData));
				} catch (e) {
					this.log('Failed to save city: ' + e.message);
				}
			}
		},

		/**
		 * Restore city on page load.
		 */
		restoreCityOnLoad: function() {
			// Wait for checkout to initialize.
			var self = this;
			setTimeout(function() {
				self.restoreCity();
			}, 500);
		},

		/**
		 * Restore city to visible carrier field.
		 */
		restoreCity: function() {
			var savedCity = this.getSavedCity();
			if (!savedCity) {
				return;
			}

			var selectors = this.config.selectors || {};

			// Find visible carrier fields and restore city.
			this.restoreToCityField(selectors.speedy_city_sel, savedCity);
			this.restoreToCityField(selectors.econt_city_sel, savedCity);
			this.restoreToCityField(selectors.address_city_sel, savedCity);
		},

		/**
		 * Restore city to a specific field if visible and empty.
		 *
		 * @param {string} selector Field selector.
		 * @param {Object} cityData Saved city data.
		 */
		restoreToCityField: function(selector, cityData) {
			var $field = $(selector);

			// Skip if field doesn't exist or isn't visible.
			if (!$field.length || !$field.is(':visible')) {
				return;
			}

			// Skip if field already has a value.
			var currentValue = $field.val();
			if (currentValue && currentValue.trim() !== '') {
				return;
			}

			// Restore city value.
			if ($field.is('select')) {
				this.restoreToSelect($field, cityData);
			} else {
				$field.val(cityData.text);
				this.log('Restored city to text input: ' + cityData.text);
			}
		},

		/**
		 * Restore city to a Select2/select field.
		 *
		 * @param {jQuery} $select  Select element.
		 * @param {Object} cityData Saved city data.
		 */
		restoreToSelect: function($select, cityData) {
			// Check if option already exists.
			var $existingOption = $select.find('option[value="' + cityData.id + '"]');

			if ($existingOption.length) {
				$select.val(cityData.id).trigger('change');
				this.log('Restored city from existing option: ' + cityData.text);
			} else if (cityData.id && cityData.text) {
				// Create new option for Select2.
				var newOption = new Option(cityData.text, cityData.id, true, true);
				$select.append(newOption).trigger('change');
				this.log('Restored city with new option: ' + cityData.text);
			}
		},

		/**
		 * Get saved city from sessionStorage.
		 *
		 * @return {Object|null} Saved city data or null.
		 */
		getSavedCity: function() {
			try {
				var data = window.sessionStorage.getItem(this.storageKey);
				if (data) {
					return JSON.parse(data);
				}
			} catch (e) {
				this.log('Failed to get saved city: ' + e.message);
			}
			return null;
		},

		/**
		 * Clear saved city from sessionStorage.
		 */
		clearSavedCity: function() {
			try {
				window.sessionStorage.removeItem(this.storageKey);
				this.log('Cleared saved city');
			} catch (e) {
				this.log('Failed to clear saved city: ' + e.message);
			}
		},

		/**
		 * Log debug message.
		 *
		 * @param {string} message Log message.
		 */
		log: function(message) {
			if (window.console && window.console.log) {
				console.log('[SESH City Persistence] ' + message);
			}
		}
	};

	// Initialize on DOM ready.
	$(document).ready(function() {
		SESHCityPersistence.init();
	});

})(jQuery, window, document);
