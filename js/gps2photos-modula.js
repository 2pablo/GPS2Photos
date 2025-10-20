/**
 * Adds "Add/Amend Coordinates" button to the Modula Gallery edit page.
 *
 * @package    GPS 2 Photo Add-on
 * @subpackage JavaScript
 * @since      1.0.0
 * @author     Pawel Block <pblock@op.pl>
 */
jQuery(document).ready(function ($) {
	'use strict';

	// =========================================================================
	// MODULA GALLERY INTEGRATION
	// =========================================================================

	// Use a MutationObserver to watch for when Modula adds its media modal to the DOM.
	var modulaObserver = new MutationObserver(function (mutations) {
		mutations.forEach(function (mutation) {
			if (!mutation.addedNodes) return;

			for (var i = 0; i < mutation.addedNodes.length; i++) {
				// Check if the added node is part of the Modula modal or contains it.
				var $node = $(mutation.addedNodes[i]);
				// We are looking for the modal content to be added.
				if ($node.find('.attachment-details').length) {
					// The content for an image has been loaded into the modal.
					var $settingsDiv = $node.find('.attachment-info .settings');
					if (!$settingsDiv.length) {
						continue;
					}

					var attachmentId = $settingsDiv.find('input[name="id"]').val();
					var imageUrl = $node.find('img.details-image').attr('src');
					var $gpsButton = $settingsDiv.find('.gps2photos-add-gps');

					if (attachmentId && imageUrl) {
						if ($gpsButton.length > 0) {
							// Button exists, just update its data attributes
							$gpsButton.attr('data-pid', attachmentId);
							$gpsButton.attr('data-image-url', imageUrl);
						} else {
							// Button doesn't exist, so create and append it before the save button
							var buttonHtml = `
								<div class="settings">
									<label class="settings setting gps2photos-setting">
										<span class="name">${gps2photos_modula.l10n.gps || 'GPS Coordinates'}</span>
										<a href="#" class="button button-primary gps2photos-add-gps" data-gallery-name="modula" data-pid="${attachmentId}" data-image-url="${imageUrl}">
											${gps2photos_modula.l10n.add_amend_gps || 'Add/Amend GPS'}
										</a>
									</label>
								</div>`;
							// The 'actions' div is a sibling of the 'settings' div.
							// We will add our button just before the 'actions' div.
							$settingsDiv.siblings('.actions').before(buttonHtml);
						}
					}
				}
			}
		});
	});
	// Start observing the body for changes.
	// This is necessary because Modula's modal is added dynamically.
	if ($('body.post-type-modula-gallery').length) {
		modulaObserver.observe(document.body, {
			childList: true,
			subtree: true
		});
	}
});
