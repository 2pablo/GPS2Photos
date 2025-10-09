/**
 * Adds "Add/Amend Coordinates" button to the Envira Gallery edit page.
 *
 * @package    GPS 2 Photo Add-on
 * @subpackage JavaScript
 * @since      1.0.0
 * @author     Pawel Block <pblock@op.pl>
 */
jQuery(document).ready(function ($) {
	'use strict';
	
	// =========================================================================
	// ENVIRA GALLERY INTEGRATION
	// =========================================================================

	// Use a MutationObserver to watch for when Envira adds its media modal to the DOM.
	var enviraObserver = new MutationObserver(function (mutations) {
		mutations.forEach(function (mutation) {
			if (!mutation.addedNodes) return;

			for (var i = 0; i < mutation.addedNodes.length; i++) {
				// Check if the added node is part of the Envira modal or contains it.
				var $node = $(mutation.addedNodes[i]);
				if ($node.find('.attachment-details').length) {
					// The content for an image has been loaded into the modal.
					var $settingsDiv = $node.find('.attachment-info .settings');

					if ($settingsDiv.length) {
						var attachmentId = $settingsDiv.find('input[name="id"]').val();
						var imageUrl = $node.find('img.details-image').attr('src');
						var $gpsButton = $settingsDiv.find('.gps2photos-add-gps');

						if (attachmentId && imageUrl) {
							if ($gpsButton.length > 0) {
								// Button exists, just update its data
								$gpsButton.data('pid', attachmentId).attr('data-pid', attachmentId);
								$gpsButton.data('image-url', imageUrl).attr('data-image-url', imageUrl);
							} else {
								// Button doesn't exist, so create and append it
								var buttonHtml = `
									<label class="setting gps2photos-setting">
										<span class="name">${gps2photos_envira.l10n.gps || 'GPS Coordinates'}</span>
										<a href="#" class="button button-primary gps2photos-add-gps" data-pid="${attachmentId}" data-image-url="${imageUrl}">
											${gps2photos_envira.l10n.add_amend_gps || 'Add/Amend GPS'}
										</a>
									</label>
								`;
								$settingsDiv.append(buttonHtml);
							}
						}
					}
				}
			}
		});
	});

	// Start observing the body for changes.
	// This is necessary because Envira's modal is added dynamically.
	if ($('body.post-type-envira').length) {
		enviraObserver.observe(document.body, {
			childList: true,
			subtree: true
		});
	}

	// =========================================================================
	// NEXTGEN GALLERY INTEGRATION
	// =========================================================================

	// NextGEN loads content via AJAX, so we need to use a delegated event handler
	// The logic for adding the button is handled via a PHP filter in this case,
	// so we just need to handle the click, which is already done by the generic handler above.
	// No specific JS is needed for NextGEN button injection if the PHP filter is used.

});
