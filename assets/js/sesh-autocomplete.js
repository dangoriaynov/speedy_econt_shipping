/**
 * SESH Autocomplete Module - Handles autocomplete for city, office, and address fields.
 *
 * Provides autocomplete functionality for:
 * - City selection (DB lookup)
 * - Office selection (DB lookup)
 * - Street/address selection (Speedy API)
 *
 * @package Speedy_Econt_Shipping
 * @since   2.1.0
 */

/* global jQuery, sesh_checkout_params */

(function($, window, document) {
	'use strict';

	/**
	 * SESH Autocomplete handler.
	 */
	window.SESHAutocomplete = {
		/**
		 * Configuration from wp_localize_script.
		 */
		config: null,

		/**
		 * Debounce timers.
		 */
		debounceTimers: {},

		/**
		 * Initialize the autocomplete module.
		 */
		init: function() {
			this.config = window.sesh_checkout_params || {};

			if (!this.config.ajax_url) {
				this.log('AJAX URL not configured');
				return;
			}

			this.log('Initializing autocomplete module');
			this.initCityAutocomplete();
			this.initOfficeAutocomplete();
			this.initStreetAutocomplete();
		},

		/**
		 * Initialize city autocomplete for Speedy and Econt.
		 */
		initCityAutocomplete: function() {
			var self = this;
			var selectors = this.config.selectors;

			// Speedy city autocomplete.
			if ($(selectors.speedy_city_sel).length > 0) {
				this.setupSelect2Autocomplete(
					selectors.speedy_city_sel,
					'speedy',
					'cities',
					{
						placeholder: this.config.i18n.select_city || 'Select city',
						minimumInputLength: 2
					}
				);
			}

			// Econt city autocomplete.
			if ($(selectors.econt_city_sel).length > 0) {
				this.setupSelect2Autocomplete(
					selectors.econt_city_sel,
					'econt',
					'cities',
					{
						placeholder: this.config.i18n.select_city || 'Select city',
						minimumInputLength: 2
					}
				);
			}

			// Address city autocomplete (standard WooCommerce field).
			if ($(selectors.address_city_sel).length > 0 && this.config.enable_address_autocomplete) {
				this.setupSelect2Autocomplete(
					selectors.address_city_sel,
					'speedy', // Use Speedy API for address autocomplete
					'cities',
					{
						placeholder: this.config.i18n.select_city || 'Select city',
						minimumInputLength: 2
					}
				);
			}
		},

		/**
		 * Initialize office autocomplete for Speedy and Econt.
		 */
		initOfficeAutocomplete: function() {
			var self = this;
			var selectors = this.config.selectors;

			// Speedy office autocomplete.
			if ($(selectors.speedy_office_sel).length > 0) {
				this.setupSelect2Autocomplete(
					selectors.speedy_office_sel,
					'speedy',
					'offices',
					{
						placeholder: this.config.i18n.select_office || 'Select office',
						minimumInputLength: 2
					}
				);
			}

			// Econt office autocomplete.
			if ($(selectors.econt_office_sel).length > 0) {
				this.setupSelect2Autocomplete(
					selectors.econt_office_sel,
					'econt',
					'offices',
					{
						placeholder: this.config.i18n.select_office || 'Select office',
						minimumInputLength: 2
					}
				);
			}
		},

		/**
		 * Initialize street autocomplete (for address delivery).
		 */
		initStreetAutocomplete: function() {
			var self = this;
			var selectors = this.config.selectors;

			// Only initialize if address delivery is enabled.
			if (!this.config.enable_address_autocomplete) {
				return;
			}

			// Street/address field autocomplete.
			if ($(selectors.address_office_sel).length > 0) {
				this.setupSelect2Autocomplete(
					selectors.address_office_sel,
					'speedy',
					'streets',
					{
						placeholder: this.config.i18n.select_office || 'Enter address',
						minimumInputLength: 3
					}
				);
			}
		},

		/**
		 * Setup Select2 autocomplete for a field.
		 *
		 * @param {string} selector jQuery selector.
		 * @param {string} carrier  Carrier (speedy or econt).
		 * @param {string} type     Type (cities, offices, streets).
		 * @param {Object} options  Select2 options.
		 */
		setupSelect2Autocomplete: function(selector, carrier, type, options) {
			var self = this;
			var $field = $(selector);

			if ($field.length === 0) {
				return;
			}

			// Destroy existing Select2 instance if present.
			if ($field.data('select2')) {
				$field.select2('destroy');
			}

			var select2Options = $.extend({
				ajax: {
					url: this.config.ajax_url,
					dataType: 'json',
					delay: 300, // Debounce delay
					data: function(params) {
						return self.buildAjaxParams(carrier, type, params, selector);
					},
					processResults: function(data) {
						return self.processAjaxResults(data, type);
					},
					cache: true
				},
				language: {
					inputTooShort: function(args) {
						var remainingChars = args.minimum - args.input.length;
						return 'Please enter ' + remainingChars + ' or more characters';
					},
					noResults: function() {
						return 'No results found';
					},
					searching: function() {
						return self.config.i18n.loading || 'Loading...';
					}
				},
				allowClear: true
			}, options);

			$field.select2(select2Options);

			this.log('Autocomplete initialized for: ' + selector);
		},

		/**
		 * Build AJAX parameters for autocomplete request.
		 *
		 * @param {string} carrier  Carrier name.
		 * @param {string} type     Type (cities, offices, streets).
		 * @param {Object} params   Select2 params.
		 * @param {string} selector Field selector.
		 * @return {Object} AJAX parameters.
		 */
		buildAjaxParams: function(carrier, type, params, selector) {
			var ajaxParams = {
				action: 'sesh_autocomplete_' + type,
				nonce: this.config.nonce,
				carrier: carrier,
				term: params.term || ''
			};

			// Add city parameter for office search.
			if (type === 'offices') {
				var citySelector = carrier === 'speedy'
					? this.config.selectors.speedy_city_sel
					: this.config.selectors.econt_city_sel;
				var selectedCity = $(citySelector).find('option:selected').text();
				if (selectedCity) {
					ajaxParams.city = selectedCity;
				}
			}

			// Add site_id parameter for street search.
			if (type === 'streets') {
				var cityIdField = $(this.config.selectors.address_city_sel).find('option:selected').data('city-id');
				if (cityIdField) {
					ajaxParams.site_id = cityIdField;
				}
			}

			return ajaxParams;
		},

		/**
		 * Process AJAX results from autocomplete request.
		 *
		 * @param {Object} data AJAX response data.
		 * @param {string} type Type (cities, offices, streets).
		 * @return {Object} Processed results for Select2.
		 */
		processAjaxResults: function(data, type) {
			if (!data.success || !data.data || !data.data.results) {
				return { results: [] };
			}

			var results = data.data.results;

			// Format results for Select2.
			var formattedResults = results.map(function(item) {
				return {
					id: item.id,
					text: item.text,
					data: item // Store original data
				};
			});

			return { results: formattedResults };
		},

		/**
		 * Debounce function execution.
		 *
		 * @param {Function} func    Function to debounce.
		 * @param {number}   wait    Wait time in milliseconds.
		 * @param {string}   timerId Timer identifier.
		 * @return {Function} Debounced function.
		 */
		debounce: function(func, wait, timerId) {
			var self = this;
			return function() {
				var context = this;
				var args = arguments;

				clearTimeout(self.debounceTimers[timerId]);
				self.debounceTimers[timerId] = setTimeout(function() {
					func.apply(context, args);
				}, wait);
			};
		},

		/**
		 * Search cities (fallback for non-Select2 implementation).
		 *
		 * @param {string} carrier Carrier name.
		 * @param {string} term    Search term.
		 * @param {Function} callback Callback function.
		 */
		searchCities: function(carrier, term, callback) {
			var self = this;

			if (term.length < 2) {
				callback([]);
				return;
			}

			$.ajax({
				url: this.config.ajax_url,
				type: 'GET',
				dataType: 'json',
				data: {
					action: 'sesh_autocomplete_cities',
					nonce: this.config.nonce,
					carrier: carrier,
					term: term
				},
				success: function(response) {
					if (response.success && response.data && response.data.results) {
						callback(response.data.results);
					} else {
						callback([]);
					}
				},
				error: function(xhr, status, error) {
					self.log('City search error: ' + error);
					callback([]);
				}
			});
		},

		/**
		 * Search offices (fallback for non-Select2 implementation).
		 *
		 * @param {string} carrier Carrier name.
		 * @param {string} term    Search term.
		 * @param {string} city    Optional city filter.
		 * @param {Function} callback Callback function.
		 */
		searchOffices: function(carrier, term, city, callback) {
			var self = this;

			if (term.length < 2) {
				callback([]);
				return;
			}

			$.ajax({
				url: this.config.ajax_url,
				type: 'GET',
				dataType: 'json',
				data: {
					action: 'sesh_autocomplete_offices',
					nonce: this.config.nonce,
					carrier: carrier,
					term: term,
					city: city || ''
				},
				success: function(response) {
					if (response.success && response.data && response.data.results) {
						callback(response.data.results);
					} else {
						callback([]);
					}
				},
				error: function(xhr, status, error) {
					self.log('Office search error: ' + error);
					callback([]);
				}
			});
		},

		/**
		 * Search streets (fallback for non-Select2 implementation).
		 *
		 * @param {number} siteId  Site/city ID.
		 * @param {string} term    Search term.
		 * @param {Function} callback Callback function.
		 */
		searchStreets: function(siteId, term, callback) {
			var self = this;

			if (term.length < 3 || !siteId) {
				callback([]);
				return;
			}

			$.ajax({
				url: this.config.ajax_url,
				type: 'GET',
				dataType: 'json',
				data: {
					action: 'sesh_autocomplete_streets',
					nonce: this.config.nonce,
					site_id: siteId,
					term: term
				},
				success: function(response) {
					if (response.success && response.data && response.data.results) {
						callback(response.data.results);
					} else {
						callback([]);
					}
				},
				error: function(xhr, status, error) {
					// Fail silently as per requirements.
					self.log('Street search error (silent): ' + error);
					callback([]);
				}
			});
		},

		/**
		 * Log debug message.
		 *
		 * @param {string} message Log message.
		 */
		log: function(message) {
			if (window.console && window.console.log) {
				console.log('[SESH Autocomplete] ' + message);
			}
		}
	};

	// Initialize on DOM ready.
	$(document).ready(function() {
		SESHAutocomplete.init();
	});

})(jQuery, window, document);
