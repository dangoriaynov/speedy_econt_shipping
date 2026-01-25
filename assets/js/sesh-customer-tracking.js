/**
 * Customer Tracking JavaScript
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

(function($) {
	'use strict';

	/**
	 * Customer Tracking Handler
	 */
	const SESHCustomerTracking = {

		/**
		 * Initialize
		 */
		init: function() {
			this.copyButton();
			this.refreshTracking();
		},

		/**
		 * Handle copy tracking number button
		 */
		copyButton: function() {
			$(document).on('click', '.sesh-copy-tracking', function(e) {
				e.preventDefault();

				const $button = $(this);
				const trackingNumber = $button.data('tracking');

				if (!trackingNumber) {
					return;
				}

				// Create temporary input
				const $temp = $('<input>');
				$('body').append($temp);
				$temp.val(trackingNumber).select();

				try {
					// Copy to clipboard
					document.execCommand('copy');

					// Visual feedback
					$button.addClass('copied');
					setTimeout(function() {
						$button.removeClass('copied');
					}, 600);

					// Optional: Show tooltip or notification
					// You can extend this with a toast notification library
				} catch (err) {
					console.error('Failed to copy tracking number:', err);
				}

				$temp.remove();
			});
		},

		/**
		 * Handle refresh tracking button
		 */
		refreshTracking: function() {
			$(document).on('click', '.sesh-refresh-tracking', function(e) {
				e.preventDefault();

				const $button = $(this);
				const labelId = $button.data('label-id');
				const $container = $button.closest('.sesh-tracking-container, .sesh-tracking-meta-box');
				const $loading = $container.find('.sesh-tracking-loading, .sesh-loading');

				if (!labelId) {
					return;
				}

				// Disable button and show loading
				$button.prop('disabled', true);
				$container.addClass('is-loading');
				$loading.show();

				// AJAX request
				$.ajax({
					url: sesh_tracking_params.ajax_url,
					type: 'POST',
					data: {
						action: 'sesh_refresh_tracking',
						nonce: sesh_tracking_params.nonce,
						label_id: labelId
					},
					success: function(response) {
						if (response.success) {
							// Reload page to show updated tracking info
							location.reload();
						} else {
							SESHCustomerTracking.showError(response.data.message);
						}
					},
					error: function() {
						SESHCustomerTracking.showError(sesh_tracking_params.i18n.error);
					},
					complete: function() {
						$button.prop('disabled', false);
						$container.removeClass('is-loading');
						$loading.hide();
					}
				});
			});
		},

		/**
		 * Show error message
		 *
		 * @param {string} message Error message
		 */
		showError: function(message) {
			// Simple alert for now - can be replaced with a toast notification
			alert(message);
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		SESHCustomerTracking.init();
	});

})(jQuery);
