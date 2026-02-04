/**
 * Admin Settings JavaScript
 *
 * Handles cascading dropdowns for sender address configuration.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

(function($) {
	'use strict';

	/**
	 * SESH Admin Settings handler.
	 */
	const SESHAdminSettings = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.cacheDom();
			this.bindEvents();
			this.initCityAutocomplete();
			this.bindTestConnectionButtons();
			this.initConditionalFields();
		},

		/**
		 * Cache DOM elements.
		 */
		cacheDom: function() {
			this.$regionField = $('#sesh_sender_region');
			this.$cityField = $('#sesh_sender_city');
			this.$form = $('form');
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			// Validate phone on blur.
			$('#sesh_sender_phone').on('blur', this.validatePhone.bind(this));

			// Validate form before submit.
			this.$form.on('submit', this.validateForm.bind(this));
		},

		/**
		 * Initialize city autocomplete.
		 */
		initCityAutocomplete: function() {
			if (!this.$cityField.length || typeof $.fn.autocomplete === 'undefined') {
				return;
			}

			const self = this;

			this.$cityField.autocomplete({
				minLength: 2,
				delay: 300,
				source: function(request, response) {
					self.searchCities(request.term, response);
				},
				select: function(event, ui) {
					self.$cityField.val(ui.item.value);
					self.$regionField.val(ui.item.region || '');
					return false;
				}
			});
		},

		/**
		 * Search cities via AJAX.
		 *
		 * @param {string} term Search term.
		 * @param {Function} callback Autocomplete callback.
		 */
		searchCities: function(term, callback) {
			if (!window.seshAdminSettings || !window.seshAdminSettings.ajax_url) {
				callback([]);
				return;
			}

			$.ajax({
				url: window.seshAdminSettings.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'sesh_search_cities',
					security: window.seshAdminSettings.nonce,
					search: term
				},
				success: function(data) {
					if (data.success && data.data) {
						callback(data.data);
					} else {
						callback([]);
					}
				},
				error: function() {
					callback([]);
				}
			});
		},

		/**
		 * Validate Bulgarian phone number.
		 *
		 * @param {Event} event Blur event.
		 */
		validatePhone: function(event) {
			const $field = $(event.currentTarget);
			const phone = $field.val().trim();

			if (!phone) {
				this.clearFieldError($field);
				return;
			}

			// Remove spaces and dashes for validation.
			const cleaned = phone.replace(/[\s\-]/g, '');

			// Valid formats: 0888123456, +359888123456, 00359888123456.
			const isValid = /^0[0-9]{9}$/.test(cleaned) ||
				/^\+3590?[0-9]{9}$/.test(cleaned) ||
				/^003590?[0-9]{9}$/.test(cleaned);

			if (!isValid) {
				this.showFieldError(
					$field,
					window.seshAdminSettings.i18n.invalid_phone || 'Invalid Bulgarian phone number format.'
				);
			} else {
				this.clearFieldError($field);
			}
		},

		/**
		 * Validate form before submission.
		 *
		 * @param {Event} event Submit event.
		 * @return {boolean} Whether form is valid.
		 */
		validateForm: function(event) {
			// Only validate on sender settings section.
			const urlParams = new URLSearchParams(window.location.search);
			if (urlParams.get('section') !== 'sender') {
				return true;
			}

			let isValid = true;
			const errors = [];

			// Validate city exists if provided.
			const city = this.$cityField.val().trim();
			if (city) {
				// City validation will be handled server-side via AJAX.
				// For now, just ensure it's not empty.
			}

			// Validate phone if provided.
			const phone = $('#sesh_sender_phone').val().trim();
			if (phone) {
				const cleaned = phone.replace(/[\s\-]/g, '');
				const phoneValid = /^0[0-9]{9}$/.test(cleaned) ||
					/^\+3590?[0-9]{9}$/.test(cleaned) ||
					/^003590?[0-9]{9}$/.test(cleaned);

				if (!phoneValid) {
					isValid = false;
					errors.push(window.seshAdminSettings.i18n.invalid_phone || 'Invalid phone number format.');
				}
			}

			if (!isValid) {
				event.preventDefault();
				this.showNotice(errors.join('<br>'), 'error');
			}

			return isValid;
		},

		/**
		 * Show field error.
		 *
		 * @param {jQuery} $field Field element.
		 * @param {string} message Error message.
		 */
		showFieldError: function($field, message) {
			this.clearFieldError($field);

			$field.addClass('sesh-field-error');

			const $error = $('<span class="sesh-field-error-message"></span>')
				.text(message)
				.css({
					color: '#dc3232',
					fontSize: '12px',
					display: 'block',
					marginTop: '5px'
				});

			$field.after($error);
		},

		/**
		 * Clear field error.
		 *
		 * @param {jQuery} $field Field element.
		 */
		clearFieldError: function($field) {
			$field.removeClass('sesh-field-error');
			$field.siblings('.sesh-field-error-message').remove();
		},

		/**
		 * Show admin notice.
		 *
		 * @param {string} message Notice message.
		 * @param {string} type Notice type (error, warning, success, info).
		 */
		showNotice: function(message, type) {
			type = type || 'info';

			const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

			$('.wrap h1').first().after($notice);

			// Auto-dismiss after 5 seconds.
			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		},

		/**
		 * Initialize conditional field visibility.
		 */
		initConditionalFields: function() {
			const $autoGenerateToggle = $('#sesh_auto_generate_labels');
			const $autoGenerateStatusRow = $('#sesh_auto_generate_status').closest('tr');

			if (!$autoGenerateToggle.length || !$autoGenerateStatusRow.length) {
				return;
			}

			// Set initial state.
			this.toggleAutoGenerateStatus($autoGenerateToggle.is(':checked'), $autoGenerateStatusRow);

			// Bind change event.
			$autoGenerateToggle.on('change', function() {
				SESHAdminSettings.toggleAutoGenerateStatus($(this).is(':checked'), $autoGenerateStatusRow);
			});
		},

		/**
		 * Toggle auto-generate status field visibility.
		 *
		 * @param {boolean} show Whether to show the field.
		 * @param {jQuery} $row The table row element.
		 */
		toggleAutoGenerateStatus: function(show, $row) {
			if (show) {
				$row.slideDown(200);
			} else {
				$row.slideUp(200);
			}
		},

		/**
		 * Bind test connection buttons.
		 */
		bindTestConnectionButtons: function() {
			const self = this;

			$(document).on('click', '.sesh-test-connection', function(e) {
				e.preventDefault();
				self.testConnection($(this));
			});
		},

		/**
		 * Test API connection.
		 *
		 * @param {jQuery} $button Button element.
		 */
		testConnection: function($button) {
			const provider = $button.data('provider');
			const $result = $button.siblings('.sesh-test-result');
			const originalText = $button.text();

			// Get i18n strings from either localized variable.
			const i18n = (window.seshAdmin && window.seshAdmin.i18n) ||
				(window.seshAdminSettings && window.seshAdminSettings.i18n) || {};
			const ajaxUrl = (window.seshAdmin && window.seshAdmin.ajaxUrl) ||
				(window.seshAdminSettings && window.seshAdminSettings.ajax_url) ||
				ajaxurl;
			const nonce = (window.seshAdmin && window.seshAdmin.nonce) ||
				(window.seshAdminSettings && window.seshAdminSettings.nonce) || '';

			// Disable button and show loading.
			$button.prop('disabled', true).text(i18n.testing || 'Testing...');
			$result.text('').removeClass('sesh-success sesh-error');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'sesh_test_' + provider + '_connection',
					nonce: nonce
				},
				success: function(response) {
					if (response.success) {
						$result
							.text(response.data.message)
							.css('color', '#46b450')
							.addClass('sesh-success');
					} else {
						$result
							.text((i18n.error || 'Error:') + ' ' + (response.data.message || 'Unknown error'))
							.css('color', '#dc3232')
							.addClass('sesh-error');
					}
				},
				error: function(xhr, status, error) {
					$result
						.text((i18n.error || 'Error:') + ' ' + error)
						.css('color', '#dc3232')
						.addClass('sesh-error');
				},
				complete: function() {
					$button.prop('disabled', false).text(i18n.testButton || originalText);
				}
			});
		}
	};

	/**
	 * Initialize on document ready.
	 */
	$(document).ready(function() {
		SESHAdminSettings.init();
	});

})(jQuery);
