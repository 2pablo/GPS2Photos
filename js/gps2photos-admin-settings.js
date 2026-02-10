/**
 * GPS 2 Photos Admin Settings.
 *
 * @package    GPS 2 Photo Add-on
 * @subpackage Administration
 * @since      1.0.0
 */

/* global gps2photos_admin_settings */

/**
 * Updates the pushpin icon preview image when the selection changes.
 */
function gps2photos_update_image() {
	const select = document.getElementById('gps2image_pin_icon_type');
	const image = document.getElementById('gps2image_pin_icon_image');
	const selectedValue = select.value;

	// Update the image source.
	image.src = gps2photos_admin_settings.plugin_url + '/img/pin-types/' + selectedValue + '.png';
}

/**
 * Updates the search results pushpin icon preview image when the selection changes.
 */
function gps2photos_update_search_image() {
	const select = document.getElementById('gps2image_search_pin_icon_type');
	const image = document.getElementById('gps2image_search_pin_icon_image');
	const selectedValue = select.value;

	// Update the image source.
	image.src = gps2photos_admin_settings.plugin_url + '/img/pin-types/' + selectedValue + '.png';
}