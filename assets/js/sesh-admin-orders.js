/**
 * SESH Admin Orders
 *
 * Handles admin order page functionality including label generation,
 * printing, cancellation, and tracking updates.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

(function ($) {
	'use strict';

	const SeshAdminOrders = {
		/**
		 * Initialize.
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function () {
			// Copy tracking number.
			$(document).on('click', '.sesh-copy-tracking', this.copyTrackingNumber);

			// Generate label.
			$(document).on('click', '.sesh-generate-label', this.generateLabel);

			// Regenerate label.
			$(document).on('click', '.sesh-regenerate-label', this.regenerateLabel);

			// Cancel label.
			$(document).on('click', '.sesh-cancel-label', this.cancelLabel);

			// Refresh tracking.
			$(document).on('click', '.sesh-refresh-tracking', this.refreshTracking);
		},

		/**
		 * Copy tracking number to clipboard.
		 */
		copyTrackingNumber: function (e) {
			e.preventDefault();

			const $button = $(this);
			const trackingNumber = $button.data('tracking');

			if (!trackingNumber) {
				return;
			}

			// Use modern Clipboard API if available.
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(trackingNumber)
					.then(function () {
						SeshAdminOrders.showNotice('success', seshAdminOrders.i18n.copy_success);
						SeshAdminOrders.flashButton($button);
					})
					.catch(function () {
						SeshAdminOrders.fallbackCopyToClipboard(trackingNumber);
					});
			} else {
				// Fallback for older browsers.
				SeshAdminOrders.fallbackCopyToClipboard(trackingNumber);
			}
		},

		/**
		 * Fallback method to copy text to clipboard.
		 */
		fallbackCopyToClipboard: function (text) {
			const $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();

			try {
				document.execCommand('copy');
				SeshAdminOrders.showNotice('success', seshAdminOrders.i18n.copy_success);
			} catch (err) {
				SeshAdminOrders.showNotice('error', seshAdminOrders.i18n.copy_error);
			}

			$temp.remove();
		},

		/**
		 * Flash button to indicate success.
		 */
		flashButton: function ($button) {
			$button.addClass('sesh-copied');
			setTimeout(function () {
				$button.removeClass('sesh-copied');
			}, 1000);
		},

		/**
		 * Generate label for order.
		 */
		generateLabel: function (e) {
			e.preventDefault();

			const $button = $(this);
			const orderId = $button.data('order-id');

			if (!orderId) {
				return;
			}

			// Show loading state.
			SeshAdminOrders.setLoading($button, true, seshAdminOrders.i18n.generating_label);

			// Send AJAX request.
			$.ajax({
				url: seshAdminOrders.ajax_url,
				type: 'POST',
				data: {
					action: 'sesh_generate_label',
					security: seshAdminOrders.nonces.generate_label,
					order_id: orderId,
				},
				success: function (response) {
					if (response.success) {
						SeshAdminOrders.showNotice('success', response.data.message);
						// Reload page to show new label.
						location.reload();
					} else {
						SeshAdminOrders.showNotice('error', response.data.message);
					}
				},
				error: function () {
					SeshAdminOrders.showNotice('error', seshAdminOrders.i18n.error_occurred);
				},
				complete: function () {
					SeshAdminOrders.setLoading($button, false);
				},
			});
		},

		/**
		 * Regenerate label for order.
		 */
		regenerateLabel: function (e) {
			e.preventDefault();

			const $button = $(this);
			const orderId = $button.data('order-id');

			if (!orderId) {
				return;
			}

			// Confirm action.
			if (!confirm(seshAdminOrders.i18n.confirm_cancel)) {
				return;
			}

			// Use same logic as generate.
			SeshAdminOrders.setLoading($button, true, seshAdminOrders.i18n.generating_label);

			$.ajax({
				url: seshAdminOrders.ajax_url,
				type: 'POST',
				data: {
					action: 'sesh_generate_label',
					security: seshAdminOrders.nonces.generate_label,
					order_id: orderId,
				},
				success: function (response) {
					if (response.success) {
						SeshAdminOrders.showNotice('success', response.data.message);
						location.reload();
					} else {
						SeshAdminOrders.showNotice('error', response.data.message);
					}
				},
				error: function () {
					SeshAdminOrders.showNotice('error', seshAdminOrders.i18n.error_occurred);
				},
				complete: function () {
					SeshAdminOrders.setLoading($button, false);
				},
			});
		},

		/**
		 * Cancel label.
		 */
		cancelLabel: function (e) {
			e.preventDefault();

			const $button = $(this);
			const labelId = $button.data('label-id');

			if (!labelId) {
				return;
			}

			// Confirm cancellation.
			if (!confirm(seshAdminOrders.i18n.confirm_cancel)) {
				return;
			}

			// Show loading state.
			SeshAdminOrders.setLoading($button, true, seshAdminOrders.i18n.cancelling_label);

			// Send AJAX request.
			$.ajax({
				url: seshAdminOrders.ajax_url,
				type: 'POST',
				data: {
					action: 'sesh_cancel_label',
					security: seshAdminOrders.nonces.cancel_label,
					label_id: labelId,
				},
				success: function (response) {
					if (response.success) {
						SeshAdminOrders.showNotice('success', response.data.message);
						// Reload page to update status.
						location.reload();
					} else {
						SeshAdminOrders.showNotice('error', response.data.message);
					}
				},
				error: function () {
					SeshAdminOrders.showNotice('error', seshAdminOrders.i18n.error_occurred);
				},
				complete: function () {
					SeshAdminOrders.setLoading($button, false);
				},
			});
		},

		/**
		 * Refresh tracking information.
		 */
		refreshTracking: function (e) {
			e.preventDefault();

			const $button = $(this);
			const labelId = $button.data('label-id');
			const $metaBox = $button.closest('.sesh-tracking-meta-box');

			if (!labelId) {
				return;
			}

			// Show loading state.
			SeshAdminOrders.setLoading($button, true, seshAdminOrders.i18n.refreshing_tracking);

			// Send AJAX request.
			$.ajax({
				url: seshAdminOrders.ajax_url,
				type: 'POST',
				data: {
					action: 'sesh_refresh_tracking',
					security: seshAdminOrders.nonces.refresh_tracking,
					label_id: labelId,
				},
				success: function (response) {
					if (response.success) {
						SeshAdminOrders.showNotice('success', response.data.message);

						// Update tracking events if provided.
						if (response.data.events) {
							SeshAdminOrders.updateTrackingTimeline($metaBox, response.data.events);
						}

						// Update timestamp.
						if (response.data.updated_at) {
							$metaBox.find('.sesh-updated-time').text(response.data.updated_at);
						}
					} else {
						SeshAdminOrders.showNotice('error', response.data.message);
					}
				},
				error: function () {
					SeshAdminOrders.showNotice('error', seshAdminOrders.i18n.error_occurred);
				},
				complete: function () {
					SeshAdminOrders.setLoading($button, false);
				},
			});
		},

		/**
		 * Update tracking timeline with new events.
		 */
		updateTrackingTimeline: function ($metaBox, events) {
			const $timeline = $metaBox.find('.sesh-timeline-events');

			if (!$timeline.length || !events || !events.length) {
				return;
			}

			// Clear existing events.
			$timeline.empty();

			// Add new events.
			events.forEach(function (event) {
				const $event = $('<li class="sesh-timeline-event"></li>');

				// Date.
				if (event.date) {
					$event.append('<div class="sesh-event-date">' + event.date + '</div>');
				}

				// Details container.
				const $details = $('<div class="sesh-event-details"></div>');

				// Status.
				if (event.status) {
					$details.append('<div class="sesh-event-status">' + event.status + '</div>');
				}

				// Description.
				if (event.description) {
					$details.append('<div class="sesh-event-description">' + event.description + '</div>');
				}

				// Location.
				if (event.location) {
					$details.append(
						'<div class="sesh-event-location">' +
						'<span class="dashicons dashicons-location"></span> ' +
						event.location +
						'</div>'
					);
				}

				$event.append($details);
				$timeline.append($event);
			});

			// Update current status.
			if (events[0] && events[0].status) {
				$metaBox.find('.sesh-status-text').text(events[0].status);
			}
		},

		/**
		 * Set loading state for button.
		 */
		setLoading: function ($element, loading, message) {
			const $container = $element.closest('.sesh-label-meta-box, .sesh-tracking-meta-box');
			const $loading = $container.find('.sesh-loading');

			if (loading) {
				$element.prop('disabled', true).addClass('sesh-loading-state');
				$loading.show();

				if (message) {
					$loading.find('.sesh-loading-text').text(message);
				}
			} else {
				$element.prop('disabled', false).removeClass('sesh-loading-state');
				$loading.hide();
			}
		},

		/**
		 * Show admin notice.
		 */
		showNotice: function (type, message) {
			// Create notice element.
			const $notice = $(
				'<div class="notice notice-' + type + ' is-dismissible">' +
				'<p>' + message + '</p>' +
				'<button type="button" class="notice-dismiss">' +
				'<span class="screen-reader-text">Dismiss this notice.</span>' +
				'</button>' +
				'</div>'
			);

			// Insert notice at top of page.
			$('.wrap > h1').first().after($notice);

			// Make dismissible.
			$notice.find('.notice-dismiss').on('click', function () {
				$notice.fadeOut(function () {
					$(this).remove();
				});
			});

			// Auto-dismiss after 5 seconds.
			setTimeout(function () {
				$notice.fadeOut(function () {
					$(this).remove();
				});
			}, 5000);
		},
	};

	// Initialize on document ready.
	$(document).ready(function () {
		SeshAdminOrders.init();
	});

})(jQuery);
