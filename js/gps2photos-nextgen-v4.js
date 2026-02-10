/**
 * Adds button to the NextGEN v4+ galleries thumbs context menu to set GPS coordinates.
 *
 * This script adds button to to open a modal window for NextGEN Gallery v4 galleries 
 * thumbs context menu which allows to set GPS coordinates.
 *
 * @package    GPS 2 Photo Add-on
 * @subpackage JavaScript
 * @since      1.0.0
 * @author     Pawel Block <pb@pasart.net>
 */

jQuery(document).ready(function ($) {
	// We use delegation on the document because the gallery items might be re-rendered.
	$(document).on('click', 'button[title="Actions"]', function () {
		// 1. Capture Data immediately from the clicked element context
		// This ensures we always have the correct ID for the clicked image.
		var $container = $(this).closest('[data-testid^="sortable-image-"]');
		if (!$container.length) return;

		var testId = $container.attr('data-testid');
		var imageId = testId.replace('sortable-image-', '');
		var $img = $container.find('img');
		var imageUrl = $img.length ? $img.attr('src') : '';

		var menuFound = false;

		// 2. Define the injection function
		// This function checks if the passed element is the menu and injects (or updates) the button.
		var tryInject = function ($node) {
			// We look for the specific classes of the menu container
			// Classes: bg-white rounded-md shadow-lg border border-gray-200 overflow-hidden
			var isMenu = $node.hasClass('bg-white') && $node.hasClass('rounded-md') && $node.hasClass('shadow-lg');

			// If the node itself isn't the menu, check if it contains the menu (e.g. a wrapper)
			var $menu = isMenu ? $node : $node.find('.bg-white.rounded-md.shadow-lg');

			$menu.each(function () {
				var $thisMenu = $(this);

				// Double check it's the right menu by looking for "Edit Details"
				if ($thisMenu.find('button:contains("Edit Details")').length > 0) {
					menuFound = true;

					var $existingBtn = $thisMenu.find('.gps2photos-add-gps');
					if ($existingBtn.length > 0) {
						// If button exists, just update the data (handles menu reuse)
						$existingBtn.attr('data-pid', imageId);
						$existingBtn.attr('data-image-url', imageUrl);
						return;
					}

					var $innerContainer = $thisMenu.find('.py-1');

					// Create the button
					var label = gps2photos_nextgen_v4.l10n.add_amend_gps || 'Add/Amend GPS';
					var $newBtn = $('<button class="block w-full text-left px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer gps2photos-add-gps">' + label + '</button>');
					$newBtn.attr('data-gallery-name', 'nextgen');
					$newBtn.attr('data-pid', imageId);
					$newBtn.attr('data-image-url', imageUrl);

					// Insert
					var $targetBtn = $innerContainer.find('button:contains("Edit Image")');
					if ($targetBtn.length) {
						$newBtn.insertAfter($targetBtn);
					} else {
						$innerContainer.append($newBtn);
					}
				}
			});
		};

		// 3. Strategy A: Check if the menu is ALREADY in the DOM.
		// This handles the case where NextGEN creates the menu synchronously or before our handler runs.
		// This is the "simplest method" you suggested, and it runs first.
		$('.bg-white.rounded-md.shadow-lg').each(function () {
			tryInject($(this));
		});

		if (menuFound) return;

		// 4. Strategy B: Observe for NEW nodes.
		// This handles the case where the menu is created asynchronously after our handler.
		// This acts as a safety net for different browsers/speeds.
		var observer = new MutationObserver(function (mutations, obs) {
			mutations.forEach(function (mutation) {
				if (mutation.addedNodes.length) {
					$(mutation.addedNodes).each(function () {
						tryInject($(this));
					});
				}
			});
		});

		// Start observing
		observer.observe(document.body, { childList: true, subtree: true });

		// Stop observing after a short timeout (e.g., 500ms) to clean up.
		setTimeout(function () {
			observer.disconnect();
		}, 500);
	});
});
