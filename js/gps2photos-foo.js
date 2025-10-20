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
	// FOOGALLERY INTEGRATION
	// =========================================================================

	// Use a MutationObserver to watch for when FooGallery adds its media modal to the DOM.
	var fooGalleryObserver = new MutationObserver(function (mutations) {
		mutations.forEach(function (mutation) {
			if (!mutation.addedNodes) return;

			for (var i = 0; i < mutation.addedNodes.length; i++) {
				// The relevant node is a div, skip text nodes etc.
				if (mutation.addedNodes[i].nodeType !== 1) continue;

				var $node = $(mutation.addedNodes[i]);

				// We trigger the logic when the meta fields container is added.
				var $metaDiv = $node.is('.foogallery-image-edit-meta') ? $node : $node.find('.foogallery-image-edit-meta');

				if ($metaDiv.length) {
					// Find the container for the form fields.
					var $settingsDiv = $metaDiv.find('#foogallery-panel-main .settings');
					if (!$settingsDiv.length) continue;

					// Don't add the button if it's already there.
					if ($settingsDiv.find('.gps2photos-add-gps').length > 0) continue;

					// Get the attachment ID from the hidden input in the meta div.
					var attachmentId = $metaDiv.find('input[name="img_id"]').val();

					// The image div is a sibling to the meta div.
					var $imageDiv = $metaDiv.siblings('.foogallery-image-edit-main');
					var imageUrl = $metaDiv.find('#attachments-foogallery-file-url').val();

					if (attachmentId && imageUrl) {
						// Create and append the button.
						var buttonHtml = `
							<span class="setting gps2photos-setting">
								<label class="name">${gps2photos_foo.l10n.gps || 'GPS Coordinates'}</label>
								<a href="#" class="button button-primary gps2photos-add-gps" data-gallery-name="foo" data-pid="${attachmentId}" data-image-url="${imageUrl}">
									${gps2photos_foo.l10n.add_amend_gps || 'Add/Amend GPS'}
								</a>
							</span>
						`;
						$settingsDiv.append(buttonHtml);
					}
				}
			}
		});
	});

	// Start observing the body for changes.
	// This is necessary because FooGallery's modal is added dynamically.
	if ($('body.post-type-foogallery').length) {
		fooGalleryObserver.observe(document.body, {
			childList: true,
			subtree: true
		});
	}
});
