/**
 * SESH Location Selector Module - Handles cascading location dropdowns.
 *
 * Manages region → city → office selection for Speedy and Econt carriers.
 * Uses AJAX to dynamically load cities and offices based on parent selection.
 *
 * @package Speedy_Econt_Shipping
 * @since   3.0.0
 */

/* global jQuery, sesh_checkout_params */

(function($, window, document) {
	'use strict';

	/**
	 * SESH Location Selector handler.
	 */
	window.SESHLocationSelector = {
		/**
		 * Configuration from wp_localize_script.
		 */
		config: null,

		/**
		 * Office data cache.
		 */
		officeData: {},

		/**
		 * Initialize the location selector module.
		 */
		init: function() {
			this.config = window.sesh_checkout_params || {};

			if (!this.config.delivery_options) {
				this.log('No delivery options configured');
				return;
			}

			this.log('Initializing location selector module');
			this.cacheOfficeData();
			this.bindEvents();
			this.initializeSelect2();
		},

		/**
		 * Cache office data from inline variables.
		 */
		cacheOfficeData: function() {
			// Data is passed via inline script tags (speedyData, econtData).
			// This is maintained for backward compatibility with the legacy system.
			if (window.speedyData) {
				this.officeData.speedy = window.speedyData;
				this.log('Cached Speedy office data');
			}

			if (window.econtData) {
				this.officeData.econt = window.econtData;
				this.log('Cached Econt office data');
			}
		},

		/**
		 * Initialize Select2 dropdowns.
		 */
		initializeSelect2: function() {
			var selectors = this.config.selectors;

			var select2Fields = [
				selectors.speedy_region_sel,
				selectors.speedy_city_sel,
				selectors.speedy_office_sel,
				selectors.econt_region_sel,
				selectors.econt_city_sel,
				selectors.econt_office_sel,
				selectors.address_region_sel,
				selectors.address_city_sel,
				selectors.address_office_sel
			];

			$(select2Fields.join(',')).select2();
			this.log('Select2 initialized');
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			var self = this;
			var selectors = this.config.selectors;

			// Speedy region change.
			$(document).on('change', selectors.speedy_region_sel, function() {
				self.handleRegionChange('speedy');
			});

			// Speedy city change.
			$(document).on('change', selectors.speedy_city_sel, function() {
				self.handleCityChange('speedy');
			});

			// Speedy office change.
			$(document).on('change', selectors.speedy_office_sel, function() {
				self.handleOfficeChange('speedy');
			});

			// Econt region change.
			$(document).on('change', selectors.econt_region_sel, function() {
				self.handleRegionChange('econt');
			});

			// Econt city change.
			$(document).on('change', selectors.econt_city_sel, function() {
				self.handleCityChange('econt');
			});

			// Econt office change.
			$(document).on('change', selectors.econt_office_sel, function() {
				self.handleOfficeChange('econt');
			});

			this.log('Event handlers bound');
		},

		/**
		 * Handle region selection change.
		 *
		 * @param {string} carrier Carrier name (speedy or econt).
		 */
		handleRegionChange: function(carrier) {
			var selectors = this.config.selectors;
			var regionSelector = carrier === 'speedy' ? selectors.speedy_region_sel : selectors.econt_region_sel;
			var citySelector = carrier === 'speedy' ? selectors.speedy_city_sel : selectors.econt_city_sel;
			var officeSelector = carrier === 'speedy' ? selectors.speedy_office_sel : selectors.econt_office_sel;
			var cityField = carrier === 'speedy' ? selectors.speedy_city_field : selectors.econt_city_field;
			var officeField = carrier === 'speedy' ? selectors.speedy_office_field : selectors.econt_office_field;

			var selectedRegion = $(regionSelector).find('option:selected').text();
			this.log('Region changed: ' + carrier + ' - ' + selectedRegion);

			// Update address region dropdown.
			$(selectors.address_region_sel + ' option').filter(function() {
				return $(this).text() === selectedRegion;
			}).prop('selected', true).trigger('change.select2');

			// Clear address city.
			$(selectors.address_city_sel).val('');

			// Populate cities for selected region.
			this.populateCities(carrier, selectedRegion);

			// Reset city and office selections.
			$(citySelector).val('').trigger('change.select2');
			$(officeSelector).empty().trigger('change.select2');

			// Show city field, hide office field.
			$(cityField).show();
			$(officeField).hide();
		},

		/**
		 * Handle city selection change.
		 *
		 * @param {string} carrier Carrier name (speedy or econt).
		 */
		handleCityChange: function(carrier) {
			var selectors = this.config.selectors;
			var regionSelector = carrier === 'speedy' ? selectors.speedy_region_sel : selectors.econt_region_sel;
			var citySelector = carrier === 'speedy' ? selectors.speedy_city_sel : selectors.econt_city_sel;
			var officeSelector = carrier === 'speedy' ? selectors.speedy_office_sel : selectors.econt_office_sel;
			var officeField = carrier === 'speedy' ? selectors.speedy_office_field : selectors.econt_office_field;

			var selectedRegion = $(regionSelector).find('option:selected').text();
			var selectedCity = $(citySelector).find('option:selected').text();
			this.log('City changed: ' + carrier + ' - ' + selectedCity);

			// Update address city.
			$(selectors.address_city_sel).val(selectedCity);

			// Populate offices for selected city.
			this.populateOffices(carrier, selectedRegion, selectedCity);

			// Auto-select if only one office.
			var $officeOptions = $(officeSelector).find('option');
			if ($officeOptions.length === 1) {
				var officeValue = $officeOptions.eq(0).val();
				$(officeSelector).val(officeValue).trigger('change.select2');
				this.updateAddressField(carrier, officeValue);
			}

			// Show office field.
			$(officeField).show();
		},

		/**
		 * Handle office selection change.
		 *
		 * @param {string} carrier Carrier name (speedy or econt).
		 */
		handleOfficeChange: function(carrier) {
			var selectors = this.config.selectors;
			var officeSelector = carrier === 'speedy' ? selectors.speedy_office_sel : selectors.econt_office_sel;
			var selectedOffice = $(officeSelector).find('option:selected').text();

			this.log('Office changed: ' + carrier + ' - ' + selectedOffice);
			this.updateAddressField(carrier, selectedOffice);
		},

		/**
		 * Populate cities dropdown.
		 *
		 * @param {string} carrier Carrier name.
		 * @param {string} region Selected region.
		 */
		populateCities: function(carrier, region) {
			var selectors = this.config.selectors;
			var citySelector = carrier === 'speedy' ? selectors.speedy_city_sel : selectors.econt_city_sel;
			var data = this.officeData[carrier];

			if (!data || !data[region]) {
				this.log('No cities found for region: ' + region);
				return;
			}

			var cities = [];
			$.each(data[region], function(index, cityData) {
				cities.push({
					id: cityData.id,
					text: cityData.name
				});
			});

			// Sort cities alphabetically.
			cities.sort(function(a, b) {
				return a.text.localeCompare(b.text);
			});

			// Move matching city to top (region name = city name).
			var matchingIndex = cities.findIndex(function(city) {
				return city.text === region;
			});
			if (matchingIndex !== -1) {
				var matchingCity = cities.splice(matchingIndex, 1)[0];
				cities.unshift(matchingCity);
			}

			// Populate dropdown.
			var $citySelect = $(citySelector);
			$citySelect.empty();
			$.each(cities, function(index, city) {
				$citySelect.append($('<option>', {
					id: city.id,
					text: city.text
				}));
			});

			$citySelect.trigger('change.select2');
			this.log('Cities populated for: ' + region);
		},

		/**
		 * Populate offices dropdown.
		 *
		 * @param {string} carrier Carrier name.
		 * @param {string} region Selected region.
		 * @param {string} city Selected city.
		 */
		populateOffices: function(carrier, region, city) {
			var selectors = this.config.selectors;
			var officeSelector = carrier === 'speedy' ? selectors.speedy_office_sel : selectors.econt_office_sel;
			var data = this.officeData[carrier];

			if (!data || !data[region]) {
				this.log('No offices found for region: ' + region);
				return;
			}

			var offices = [];
			$.each(data[region], function(index, cityData) {
				if (cityData.name !== city) {
					return;
				}

				$.each(cityData.offices, function(index, officeData) {
					var officeName = officeData.name + ' (' + officeData.address + ')';

					// Add office number for Speedy.
					if (carrier === 'speedy') {
						officeName = '№' + officeData.id + ', ' + officeName;
					}

					offices.push({
						id: officeData.id,
						text: officeName
					});
				});
			});

			// Sort offices alphabetically.
			offices.sort(function(a, b) {
				return a.text.localeCompare(b.text);
			});

			// Populate dropdown.
			var $officeSelect = $(officeSelector);
			$officeSelect.empty();
			$.each(offices, function(index, office) {
				$officeSelect.append($('<option>', {
					id: office.id,
					text: office.text
				}));
			});

			$officeSelect.trigger('change.select2');
			this.log('Offices populated for: ' + city);
		},

		/**
		 * Update address field with selected office.
		 *
		 * @param {string} carrier Carrier name.
		 * @param {string} officeText Office text.
		 */
		updateAddressField: function(carrier, officeText) {
			if (!officeText) {
				return;
			}

			var deliveryOption = this.config.delivery_options[carrier];
			if (!deliveryOption) {
				return;
			}

			var addressValue = deliveryOption.label + ': ' + officeText;
			$(this.config.selectors.address_office_sel).val(addressValue);

			this.log('Address field updated: ' + addressValue);
		},

		/**
		 * Log debug message.
		 *
		 * @param {string} message Log message.
		 */
		log: function(message) {
			if (window.console && window.console.log) {
				console.log('[SESH Location Selector] ' + message);
			}
		}
	};

	// Initialize on DOM ready.
	$(document).ready(function() {
		SESHLocationSelector.init();
	});

})(jQuery, window, document);
