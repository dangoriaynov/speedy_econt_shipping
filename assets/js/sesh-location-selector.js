/**
 * SESH Location Selector Module - Handles city autocomplete and office selection.
 *
 * Manages city autocomplete → office selection for Speedy and Econt carriers.
 * Uses Select2 AJAX mode for lazy loading cities and offices.
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
		 * Cached offices per city (to avoid re-fetching).
		 */
		officeCache: {},

		/**
		 * Currently selected data per carrier.
		 */
		selectedData: {
			speedy: { cityId: null, cityName: null, officeId: null },
			econt: { cityId: null, cityName: null, officeId: null }
		},

		/**
		 * Debounce timer for price calculation.
		 */
		priceDebounceTimer: null,

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
			this.initCityAutocomplete();
			this.initOfficeSelect();
			this.bindEvents();
		},

		/**
		 * Initialize city autocomplete with Select2 AJAX mode.
		 */
		initCityAutocomplete: function() {
			var self = this;
			var carriers = ['speedy', 'econt'];

			carriers.forEach(function(carrier) {
				var $citySelect = $('#' + carrier + '_city');

				if ($citySelect.length === 0) {
					return;
				}

				// Add aria-label to the select for accessibility.
				$citySelect.attr('aria-label', self.config.i18n.select_city_label || 'Select city for ' + carrier + ' delivery');

				$citySelect.select2({
					placeholder: self.config.i18n.type_city_name || 'Type city name...',
					minimumInputLength: 2,
					allowClear: true,
					ajax: {
						url: self.config.ajax_url,
						type: 'POST',
						dataType: 'json',
						delay: 300, // 300ms debounce
						data: function(params) {
							return {
								action: 'sesh_search_cities_checkout',
								nonce: self.config.nonce,
								carrier: carrier,
								search: params.term
							};
						},
						processResults: function(response) {
							if (response.success && response.data.results) {
								return {
									results: response.data.results
								};
							}
							return { results: [] };
						},
						cache: true
					},
					language: {
						inputTooShort: function() {
							return self.config.i18n.min_chars || 'Type at least 2 characters';
						},
						searching: function() {
							return self.config.i18n.searching || 'Searching...';
						},
						noResults: function() {
							return self.config.i18n.no_results || 'No results found';
						},
						errorLoading: function() {
							return self.config.i18n.error_search || 'Unable to search. Please try again.';
						}
					}
				});

				// Add aria-label to Select2 search input after initialization.
				$citySelect.on('select2:open', function() {
					var $searchField = $('.select2-search__field');
					if ($searchField.length) {
						$searchField.attr('aria-label', self.config.i18n.type_city_name || 'Type city name');
					}
				});

				self.log('City autocomplete initialized for: ' + carrier);
			});
		},

		/**
		 * Initialize office select dropdowns.
		 */
		initOfficeSelect: function() {
			var self = this;
			var carriers = ['speedy', 'econt'];

			carriers.forEach(function(carrier) {
				var $officeSelect = $('#' + carrier + '_office');

				if ($officeSelect.length === 0) {
					return;
				}

				// Add aria-label to the select for accessibility.
				$officeSelect.attr('aria-label', self.config.i18n.select_office_label || 'Select office for ' + carrier + ' delivery');

				$officeSelect.select2({
					placeholder: self.config.i18n.select_office || 'Select office',
					allowClear: true
				});

				// Add aria-label to Select2 container after initialization.
				$officeSelect.on('select2:open', function() {
					var $searchField = $('.select2-search__field');
					if ($searchField.length) {
						$searchField.attr('aria-label', self.config.i18n.search_offices || 'Search offices');
					}
				});
			});
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			var self = this;

			// City selection change for Speedy.
			$(document).on('select2:select', '#speedy_city', function(e) {
				self.handleCitySelect('speedy', e.params.data);
			});

			$(document).on('select2:clear', '#speedy_city', function() {
				self.handleCityClear('speedy');
			});

			// City selection change for Econt.
			$(document).on('select2:select', '#econt_city', function(e) {
				self.handleCitySelect('econt', e.params.data);
			});

			$(document).on('select2:clear', '#econt_city', function() {
				self.handleCityClear('econt');
			});

			// Office selection change.
			$(document).on('change', '#speedy_office', function() {
				self.handleOfficeSelect('speedy', $(this).val());
			});

			$(document).on('change', '#econt_office', function() {
				self.handleOfficeSelect('econt', $(this).val());
			});

			this.log('Event handlers bound');
		},

		/**
		 * Handle city selection.
		 *
		 * @param {string} carrier Carrier name.
		 * @param {Object} data    Selected city data from Select2.
		 */
		handleCitySelect: function(carrier, data) {
			this.log('City selected: ' + carrier, data);

			// Store selected city data.
			this.selectedData[carrier].cityId = data.id;
			this.selectedData[carrier].cityName = data.name || data.text;

			// Update hidden field with city ID.
			$('#' + carrier + '_city_id').val(data.id);

			// Update the global hidden fields for form submission.
			$('#sesh_city_id').val(data.id);
			$('#sesh_city_name').val(data.name || data.text);

			// Also update carrier and delivery type.
			$('#sesh_carrier').val(carrier);
			$('#sesh_delivery_type').val('office'); // Default to office for Speedy/Econt.

			// Update billing city field for address delivery sync.
			if (data.name) {
				$('#billing_city').val(data.name);
			}

			// Load offices for this city.
			this.loadOfficesForCity(carrier, data.id);
		},

		/**
		 * Handle city clear.
		 *
		 * @param {string} carrier Carrier name.
		 */
		handleCityClear: function(carrier) {
			this.log('City cleared: ' + carrier);

			// Clear stored data.
			this.selectedData[carrier].cityId = null;
			this.selectedData[carrier].cityName = null;
			this.selectedData[carrier].officeId = null;

			// Clear hidden field.
			$('#' + carrier + '_city_id').val('');

			// Clear global hidden fields.
			$('#sesh_city_id').val('');
			$('#sesh_city_name').val('');
			$('#sesh_office_id').val('');
			$('#sesh_office_name').val('');
			$('#sesh_office_address').val('');
			$('#sesh_shipping_price').val('');

			// Hide and clear office field.
			$('#' + carrier + '_office_field').hide();
			$('#' + carrier + '_office').empty().trigger('change.select2');

			// Hide office preview and price display.
			$('#' + carrier + '_office_preview').hide();
			$('#' + carrier + '_price_display').hide();
		},

		/**
		 * Load offices for a city via AJAX.
		 *
		 * @param {string} carrier Carrier name.
		 * @param {number} cityId  City ID.
		 */
		loadOfficesForCity: function(carrier, cityId) {
			var self = this;
			var cacheKey = carrier + '_' + cityId;

			// Check cache first.
			if (this.officeCache[cacheKey]) {
				this.populateOffices(carrier, this.officeCache[cacheKey]);
				return;
			}

			// Show loading state.
			var $officeField = $('#' + carrier + '_office_field');
			var $officeLoading = $officeField.find('.sesh-office-loading');
			var $officeSelect = $('#' + carrier + '_office');

			$officeField.show();
			$officeLoading.show();
			// Add aria-live announcement for loading state.
			$officeLoading.attr('aria-live', 'polite').attr('role', 'status');
			$officeSelect.prop('disabled', true).attr('aria-busy', 'true');

			// Fetch offices via AJAX.
			$.ajax({
				url: this.config.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'sesh_get_offices_lazy',
					nonce: this.config.nonce,
					carrier: carrier,
					city_id: cityId
				},
				success: function(response) {
					$officeLoading.hide();
					$officeSelect.prop('disabled', false).attr('aria-busy', 'false');

					if (response.success && response.data.offices) {
						// Cache the results.
						self.officeCache[cacheKey] = response.data.offices;
						self.populateOffices(carrier, response.data.offices);

						// Focus the office select after loading.
						setTimeout(function() {
							$officeSelect.select2('open');
						}, 100);
					} else {
						self.showOfficeError(carrier, self.config.i18n.error_load_offices);
					}
				},
				error: function() {
					$officeLoading.hide();
					$officeSelect.prop('disabled', false).attr('aria-busy', 'false');
					self.showOfficeError(carrier, self.config.i18n.error_load_offices);
				}
			});
		},

		/**
		 * Populate office dropdown with data.
		 *
		 * @param {string} carrier Carrier name.
		 * @param {Array}  offices Office data array.
		 */
		populateOffices: function(carrier, offices) {
			var self = this;
			var $officeSelect = $('#' + carrier + '_office');

			// Clear and populate.
			$officeSelect.empty();
			$officeSelect.append($('<option>', {
				value: '',
				text: this.config.i18n.select_office || 'Select office'
			}));

			if (!offices || offices.length === 0) {
				this.log('No offices found for: ' + carrier);
				return;
			}

			// Sort offices alphabetically.
			offices.sort(function(a, b) {
				return a.name.localeCompare(b.name);
			});

			offices.forEach(function(office) {
				var displayText = self.formatOfficeText(carrier, office);
				$officeSelect.append($('<option>', {
					value: office.id,
					text: displayText,
					'data-office': JSON.stringify(office)
				}));
			});

			$officeSelect.trigger('change.select2');

			// Auto-select if only one office.
			if (offices.length === 1) {
				$officeSelect.val(offices[0].id).trigger('change');
			}

			this.log('Offices populated for: ' + carrier + ' (' + offices.length + ' offices)');
		},

		/**
		 * Format office text for display.
		 *
		 * @param {string} carrier Carrier name.
		 * @param {Object} office  Office data.
		 * @return {string} Formatted text.
		 */
		formatOfficeText: function(carrier, office) {
			var text = office.name;

			if (office.address) {
				text += ' (' + office.address + ')';
			}

			// Add office number prefix for Speedy.
			if (carrier === 'speedy') {
				text = '#' + office.id + ', ' + text;
			}

			return text;
		},

		/**
		 * Show error message for office loading.
		 *
		 * @param {string} carrier Carrier name.
		 * @param {string} message Error message.
		 */
		showOfficeError: function(carrier, message) {
			var self = this;
			var $officeField = $('#' + carrier + '_office_field');

			// Remove existing error.
			$officeField.find('.sesh-error').remove();

			// Add error with retry button.
			var $error = $('<div class="sesh-error">' +
				'<span class="sesh-error__icon"></span>' +
				'<span class="sesh-error__message">' + message + '</span>' +
				'<button type="button" class="sesh-error__retry">' + (this.config.i18n.retry || 'Retry') + '</button>' +
				'</div>');

			$error.find('.sesh-error__retry').on('click', function() {
				$error.remove();
				var cityId = self.selectedData[carrier].cityId;
				if (cityId) {
					// Clear cache and retry.
					delete self.officeCache[carrier + '_' + cityId];
					self.loadOfficesForCity(carrier, cityId);
				}
			});

			$officeField.append($error);
		},

		/**
		 * Handle office selection.
		 *
		 * @param {string} carrier  Carrier name.
		 * @param {string} officeId Selected office ID.
		 */
		handleOfficeSelect: function(carrier, officeId) {
			this.log('Office selected: ' + carrier + ' - ' + officeId);

			if (!officeId) {
				this.selectedData[carrier].officeId = null;
				$('#' + carrier + '_office_preview').hide();
				$('#' + carrier + '_price_display').hide();
				// Clear office hidden fields.
				$('#sesh_office_id').val('');
				$('#sesh_office_name').val('');
				$('#sesh_office_address').val('');
				return;
			}

			this.selectedData[carrier].officeId = officeId;

			// Get office data from option.
			var $selectedOption = $('#' + carrier + '_office option:selected');
			var officeData = $selectedOption.data('office');

			// Update hidden fields for form submission.
			if (officeData) {
				$('#sesh_office_id').val(officeId);
				$('#sesh_office_name').val(officeData.name || '');
				$('#sesh_office_address').val(officeData.address || '');
			}

			// Update address field with office info.
			this.updateAddressField(carrier, officeData);

			// Show office preview.
			if (officeData) {
				this.showOfficePreview(carrier, officeData);
			}

			// Calculate and display price.
			this.calculatePrice(carrier);
		},

		/**
		 * Update billing address field with office selection.
		 *
		 * @param {string} carrier    Carrier name.
		 * @param {Object} officeData Office data.
		 */
		updateAddressField: function(carrier, officeData) {
			if (!officeData) {
				return;
			}

			var deliveryOption = this.config.delivery_options[carrier];
			if (!deliveryOption) {
				return;
			}

			var addressValue = deliveryOption.label + ': ' + officeData.name;
			if (officeData.address) {
				addressValue += ', ' + officeData.address;
			}

			$('#billing_address_1').val(addressValue);
			this.log('Address field updated: ' + addressValue);
		},

		/**
		 * Show office preview card.
		 *
		 * @param {string} carrier    Carrier name.
		 * @param {Object} officeData Office data.
		 */
		showOfficePreview: function(carrier, officeData) {
			var $preview = $('#' + carrier + '_office_preview');

			var html = '<div class="sesh-office-card">';
			html += '<div class="sesh-office-card__name">' + this.escapeHtml(officeData.name) + '</div>';

			if (officeData.address) {
				html += '<div class="sesh-office-card__address">' + this.escapeHtml(officeData.address) + '</div>';
			}

			if (officeData.working_hours) {
				html += '<div class="sesh-office-card__hours"><strong>' + (this.config.i18n.working_hours || 'Hours') + ':</strong> ' + this.escapeHtml(officeData.working_hours) + '</div>';
			}

			if (officeData.phone) {
				html += '<div class="sesh-office-card__phone"><strong>' + (this.config.i18n.phone || 'Phone') + ':</strong> ' + this.escapeHtml(officeData.phone) + '</div>';
			}

			// Add map link if coordinates available.
			if (officeData.lat && officeData.lng) {
				var mapUrl = 'https://www.google.com/maps?q=' + officeData.lat + ',' + officeData.lng;
				html += '<a href="' + mapUrl + '" target="_blank" rel="noopener" class="sesh-office-card__map">' + (this.config.i18n.view_on_map || 'View on map') + '</a>';
			}

			html += '</div>';

			$preview.html(html).show();
		},

		/**
		 * Calculate and display shipping price.
		 *
		 * @param {string} carrier Carrier name.
		 */
		calculatePrice: function(carrier) {
			var self = this;
			var data = this.selectedData[carrier];

			if (!data.cityId) {
				return;
			}

			// Debounce price calculation.
			clearTimeout(this.priceDebounceTimer);
			this.priceDebounceTimer = setTimeout(function() {
				self.fetchPrice(carrier, data);
			}, 300);
		},

		/**
		 * Fetch shipping price from server.
		 *
		 * @param {string} carrier Carrier name.
		 * @param {Object} data    Selected location data.
		 */
		fetchPrice: function(carrier, data) {
			var self = this;
			var $priceDisplay = $('#' + carrier + '_price_display');

			// Show loading state.
			$priceDisplay.html(
				'<div class="sesh-price-loading">' +
				'<span class="sesh-spinner sesh-spinner--sm"></span> ' +
				(this.config.i18n.calculating_price || 'Calculating price...') +
				'</div>'
			).show();

			$.ajax({
				url: this.config.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'sesh_calculate_checkout_shipping',
					nonce: this.config.nonce,
					carrier: carrier,
					delivery_type: 'office',
					city_id: data.cityId,
					office_id: data.officeId || 0
				},
				success: function(response) {
					if (response.success) {
						self.displayPrice(carrier, response.data);
						// Notify checkout module of price update.
						$(document.body).trigger('sesh_price_calculated', [carrier, response.data]);
					} else {
						self.displayPriceError(carrier, response.data.message);
					}
				},
				error: function() {
					self.displayPriceError(carrier, self.config.i18n.error_calculate_price);
				}
			});
		},

		/**
		 * Display calculated price.
		 *
		 * @param {string} carrier   Carrier name.
		 * @param {Object} priceData Price data from server.
		 */
		displayPrice: function(carrier, priceData) {
			var $priceDisplay = $('#' + carrier + '_price_display');

			// Store calculated price in hidden field for form submission.
			if (priceData && priceData.price !== undefined) {
				$('#sesh_shipping_price').val(priceData.price);
			}

			var html = '<div class="sesh-price-result" aria-live="polite" role="status">';

			if (priceData.is_free) {
				html += '<span class="sesh-price-display sesh-price-display--free">';
				html += '<span class="sesh-price-display__amount">' + (this.config.i18n.free || 'FREE') + '</span>';
				html += '</span>';
			} else {
				html += '<span class="sesh-price-display">';
				html += '<span class="sesh-price-display__amount">' + priceData.formatted_price + '</span>';
				html += '</span>';

				if (priceData.is_fallback) {
					html += '<span class="sesh-price-estimated"> ' + (this.config.i18n.estimated || '(estimated)') + '</span>';
				}
			}

			if (priceData.delivery_time) {
				html += '<div class="sesh-delivery-time">' + priceData.delivery_time + '</div>';
			}

			html += '</div>';

			$priceDisplay.html(html).show();
		},

		/**
		 * Display price calculation error.
		 *
		 * @param {string} carrier Carrier name.
		 * @param {string} message Error message.
		 */
		displayPriceError: function(carrier, message) {
			var self = this;
			var $priceDisplay = $('#' + carrier + '_price_display');

			var html = '<div class="sesh-price-error">';
			html += '<span class="sesh-error__message">' + this.escapeHtml(message || this.config.i18n.error_calculate_price) + '</span>';
			html += '<button type="button" class="sesh-error__retry">' + (this.config.i18n.retry || 'Retry') + '</button>';
			html += '</div>';

			$priceDisplay.html(html).show();

			$priceDisplay.find('.sesh-error__retry').on('click', function() {
				self.calculatePrice(carrier);
			});
		},

		/**
		 * Escape HTML special characters.
		 *
		 * @param {string} text Text to escape.
		 * @return {string} Escaped text.
		 */
		escapeHtml: function(text) {
			if (!text) return '';
			var div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		},

		/**
		 * Get selected city ID for a carrier.
		 *
		 * @param {string} carrier Carrier name.
		 * @return {number|null} City ID or null.
		 */
		getSelectedCityId: function(carrier) {
			return this.selectedData[carrier] ? this.selectedData[carrier].cityId : null;
		},

		/**
		 * Get selected office ID for a carrier.
		 *
		 * @param {string} carrier Carrier name.
		 * @return {number|null} Office ID or null.
		 */
		getSelectedOfficeId: function(carrier) {
			return this.selectedData[carrier] ? this.selectedData[carrier].officeId : null;
		},

		/**
		 * Log debug message.
		 *
		 * @param {string} message Log message.
		 * @param {*}      data    Optional data.
		 */
		log: function(message, data) {
			if (window.console && window.console.log) {
				if (data !== undefined) {
					console.log('[SESH Location Selector] ' + message, data);
				} else {
					console.log('[SESH Location Selector] ' + message);
				}
			}
		}
	};

	// Initialize on DOM ready.
	$(document).ready(function() {
		SESHLocationSelector.init();
	});

})(jQuery, window, document);
