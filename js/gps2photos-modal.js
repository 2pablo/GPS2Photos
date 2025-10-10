/**
 * Handles the modal for adding/editing GPS coordinates.
 *
 * This script manages the opening and closing of the map modal,
 * handling user interactions for saving and restoring coordinates,
 * and fetching GPS data via AJAX for both the WordPress Media Library
 * and NextGEN Gallery.
 *
 * @package    GPS 2 Photo Add-on
 * @subpackage JavaScript
 * @since      1.0.0
 * @author     Pawel Block <pblock@op.pl>
 */
jQuery(document).ready(function ($) {
	'use strict';
	/**
	 * Converts decimal degrees to a Degrees, Minutes, Seconds (DMS) string.
	 *
	 * @param {number|string} deg The decimal degree value.
	 * @param {boolean} isLat True if the coordinate is latitude, false for longitude.
	 * @returns {string} The formatted DMS string.
	 */
	function gps2photos_decimalToDMS(deg, isLat) {
		var numDeg = parseFloat(deg);
		if (deg === '' || deg === null || isNaN(numDeg)) {
			return '';
		}

		var d = Math.floor(Math.abs(numDeg));
		var minFloat = (Math.abs(numDeg) - d) * 60;
		var m = Math.floor(minFloat);
		var secFloat = (minFloat - m) * 60;
		var s = secFloat.toFixed(2);
		var dir;

		if (isLat) {
			dir = numDeg >= 0 ? 'N' : 'S';
		} else {
			dir = numDeg >= 0 ? 'E' : 'W';
		}

		// Replicate the format from PHP: N 40°43'46.06"
		// The HTML entities are decoded by jQuery's .val() method, so we use the characters directly.
		return dir + ' ' + d + '°' + m + "'" + s + '"';
	}

	/**
	 * Updates the map's camera and marker to a new position.
	 *
	 * @param {string} lat     The latitude.
	 * @param {string} lon     The longitude.
	 */
	function initOrUpdateMap(lat, lon) {
		if (!window.gps2photos_maps.map) {
			function initMapWithKeyAndPosition(apiKey) {
				window['gps2photos_init_map'](apiKey, [lat, lon]);
			}
			gps2photos_get_azure_api_key(initMapWithKeyAndPosition);
		} else {
			// Update map with the new coordinates.
			var map = window.gps2photos_maps.map;
			var marker = window.gps2photos_maps.marker;
			var zoomLevel = window.gps2photos_maps.zoom;

			if (lat && lon) {
				var newPosition = new atlas.data.Position(parseFloat(lon), parseFloat(lat));
				marker.setOptions({
					position: newPosition,
					visible: true
				});
				map.setCamera({
					center: newPosition,
					zoom: zoomLevel,
					type: 'fly'
				});
			} else {
				// No GPS data, hide the marker.
				if (marker) {
					marker.setOptions({ visible: false });
				}
				var newZoom = map.getCamera().zoom < zoomLevel ? map.getCamera().zoom : zoomLevel;
				map.setCamera({
					//center: [0, 30],
					zoom: newZoom,
					type: 'fly'
				});
			}
		}
	}
	// ----------------------------------------------------------------------------------------------

	// Use event delegation for buttons that might be added dynamically.
	$(document).on('click', '.gps2photos-open-map-btn', function () {
		var attachmentId = $(this).data('image-id');
		openModal(attachmentId, $(this).data('data-file-path'), false, $(this).data());
	});

	// Handle click on our custom action link for NextGEN Gallery.
	$(document).on('click', '.gps2photos-add-gps', function (e) {
		e.preventDefault();
		var galleryName = $(this).data('gallery-name');
		var pid = $(this).data('pid');
		var imageUrl = $(this).data('image-url');
		// For NextGEN, we use a generic modal but pass specific image data.
		openModal(pid, imageUrl, galleryName);
	});

	// ----------------------------------------------------------------------------------------------
	// Function to open the modal and initialize the map
	/**
	 * Opens the modal and initializes the map for a given image.
	 *
	 * Handles both standard Media Library attachments and NextGEN Gallery images.
	 * If GPS data is not pre-loaded, it fetches it via an AJAX request.
	 *
	 * @param {string}      imageId   The attachment ID or pid for galleries.
	 * @param {string}      imagePath The URL of the image, used for NextGEN.
	 * @param {boolean}     [galleryName=false] Flag to indicate if the image is from WP Media Library or other galleries.
	 * @param {object|null} [buttonData=null]   The data object from the clicked button (for Media Library).
	 */
	function openModal(imageId, imagePath, galleryName = false, buttonData = null) {
		var modal = $('#gps2photos-modal');
		var $saveBtn = modal.find('.gps2photos-save-coords-btn');
		// Argument 'galleryName' is either false, "nextgen" "envira", "foo" or "modula".
		var isGallery = !!galleryName; // Convert to boolean.

		if (isGallery) {
			// For NextGEN, original-lat is not set, so it will be undefined.
			var originalLat = $saveBtn.data('original-lat');
			var originalLon = $saveBtn.data('original-lon');
		} else {
			if (buttonData.lat) {
				var originalLat = buttonData.lat;
				var originalLon = buttonData.lon;
			} else {
				var originalLat = $saveBtn.data('original-lat');
				var originalLon = $saveBtn.data('original-lon');
			}
		}
		
		if (modal.length) {
			modal.css('display', 'flex');
			// For galleries we are reusing the same modal.
			// Only fetch if the imageId is different from the one already on the button.
			if ($saveBtn.data('image-id') !== imageId) {
				// Reset the originalLat to force an AJAX fetch.
				originalLat = undefined;
			}
			$saveBtn.data('image-id', imageId).data('gallery-name', galleryName);
			modal.find('.gps2photos-restore-coords-btn').data('image-id', imageId).data('gallery-name', galleryName);	

			// Initialize the map for WP Media Library only if not already done.
			// For NextGEN, we always initialize the map as imageId is always '0'.
			// The actual image is determined by the data-image-id attribute on the buttons.
			if (!isGallery && buttonData.lat) {
				initOrUpdateMap(originalLat, originalLon);
			}

			// If originalLat is empty, it could mean either the image has no GPS, or the data wasn't pre-loaded.
			// Ths happens for NextGEN Gallery. In either case, we fetch the data on-demand to be sure.
			if (originalLat === '' || originalLat === undefined) {
				$.ajax({
					url: gps2photos_ajax.ajaxurl,
					type: 'POST',
					data: {
						action: 'gps2photos_get_coordinates',
						nonce: gps2photos_ajax.get_gps_nonce,
						image_id: imageId,
						imagePath: imagePath,
						gallery_name: galleryName || '0'
					},
					success: function (response) {
						if (response.success) {
							var lat = response.data.latitude || '';
							var lon = response.data.longitude || '';
							var filePath = response.data.file_path || '';
							var backupExists = response.data.backup_exists || false;

							$('#gps2photos-modal-lat-input').val(lat);
							$('#gps2photos-modal-lon-input').val(lon);

							// Update data attributes for all cases to ensure consistency.
							$saveBtn.data('original-lat', lat).data('original-lon', lon);
							modal.find('.gps2photos-save-coords-btn, .gps2photos-restore-coords-btn').data('file-path', filePath);

							// Initialize or update the map with the new coordinates if not already done.
							initOrUpdateMap(lat, lon);


							// Show/hide the restore button based on the AJAX response.
							var $restoreBtn = modal.find('.gps2photos-restore-coords-btn');
							$restoreBtn.toggle(backupExists);
						}
					},
					error: function () {
						console.error('Ajax failed to fetch GPS coordinates.');
					}
				});
			}
		}
	}

	// Handle closing the modal.
	$(document).on('click', '.gps2photos-modal-close', function () {
		$(this).closest('.gps2photos-modal').hide();
	});

	// Handle clicking outside the modal to close it.
	$(window).on('click', function (event) {
		if ($(event.target).is('.gps2photos-modal')) {
			$(event.target).hide();
		}
	});

	// ----------------------------------------------------------------------------------------------
	// Handle saving the coordinates.
	$(document).on('click', '.gps2photos-save-coords-btn', function () {
		var attachmentId = $(this).data('image-id');
		var filePath = $(this).data('file-path') || '';
		var $button = $(this);
		var originalText = $button.text();
		var originalLat = $button.data('original-lat');
		var originalLon = $button.data('original-lon');
		var galleryName = $(this).data('gallery-name');
		var latitude = $('#gps2photos-modal-lat-input').val().trim();
		var longitude = $('#gps2photos-modal-lon-input').val().trim();
		var $overrideCheckbox = $('#gps2photos-override-checkbox');
		var $messageDiv = $('#gps2photos-modal-message').addClass('notice');
		var latNum, lonNum;

		// --- Validation ---
		if (latitude === '' && longitude === '') {
			if ((originalLat === '' || originalLat === undefined) && (originalLon === '' || originalLon === undefined)) {
				return; // Both are already empty, nothing to do.
			} else {
				// Both are empty, this is a request to erase coordinates.
				if (confirm(gps2photos_ajax.l10n.confirm_erase) == false) {
					return;
				} else {
					latNum = '';
					lonNum = '';
				}
			}
		} else {
			// If one is empty and the other is not, it's an error.
			if (latitude === '' || longitude === '') {
				alert(gps2photos_ajax.l10n.both_coords_required);
				return;
			}

			latNum = parseFloat(latitude);
			lonNum = parseFloat(longitude);

			if (isNaN(latNum) || latNum < -90 || latNum > 90) {
				alert(gps2photos_ajax.l10n.invalid_latitude);
				return;
			}

			if (isNaN(lonNum) || lonNum < -180 || lonNum > 180) {
				alert(gps2photos_ajax.l10n.invalid_longitude);
				return;
			}
		}

		// --- Check for changes ---
		// Use a small tolerance for float comparison.
		if (latitude !== '' && longitude !== '' && Math.abs(latNum - originalLat) < 0.000001 && Math.abs(lonNum - originalLon) < 0.000001) {
			$messageDiv.text(gps2photos_ajax.l10n.coords_not_changed).fadeIn();
			setTimeout(function () {
				$messageDiv.fadeOut(function () {
					$(this).removeClass('error').removeClass('notice-success').addClass('notice-warning').text('');
				});
			}, 3000);
			return; // Stop execution.
		} else if (latitude === '' && longitude === '' && (originalLat === '' || originalLat === undefined) && (originalLon === '' || originalLon === undefined)) {
			$messageDiv.text(gps2photos_ajax.l10n.coords_already_empty).fadeIn();
			setTimeout(function () {
				$messageDiv.fadeOut(function () {
					$(this).removeClass('error').removeClass('notice-success').addClass('notice-warning').text('');
				});
			}, 3000);
			return; // Stop execution.
		}

		var ajaxData = {
			action: 'gps2photos_save_coordinates',
			nonce: gps2photos_ajax.save_gps_nonce,
			latitude: latitude,
			longitude: longitude,
			file_path: filePath
		};

		// Check if the image has original GPS data (the checkbox will exist).
		if ($overrideCheckbox.length > 0) {
			// If the override checkbox is NOT checked, ask for confirmation.
			if (!$overrideCheckbox.is(':checked') && originalLat !== '' && originalLat !== undefined && originalLon !== '' && originalLon !== undefined) {
				if (!confirm(gps2photos_ajax.l10n.confirm_override)) {
					return; // Stop if the user cancels.
				}
			}
			// Include the state of the checkbox to update the global setting.
			ajaxData.override_setting = $overrideCheckbox.is(':checked') ? 1 : 0;
		}

		$button.text(gps2photos_ajax.l10n.saving);
		$button.prop('disabled', true);

		$.ajax({
			url: gps2photos_ajax.ajaxurl,
			type: 'POST',
			data: ajaxData,
			success: function (response) {
				if (response.success) {
					$messageDiv.text(response.data.message).removeClass('error').removeClass('notice-warning').addClass('notice-success').show();

					// Update original data attributes to prevent re-saving without changes.
					$button.data('original-lat', latNum);
					$button.data('original-lon', lonNum);

					// Update the map marker position if map exists.
					if (window.gps2photos_maps.map) {
						var map = window.gps2photos_maps.map;
						var zoomLevel = window.gps2photos_maps.zoom;
						var marker = window.gps2photos_maps.marker;

						if (!latNum || !lonNum) {
							// No GPS data, hide the marker and reset view.
							marker.setOptions({ visible: false });
							map.setCamera({
								center: [0, 30],
								zoom: 1,
								type: 'fly'
							});
						} else {
							zoomLevel = map.getCamera().zoom || zoomLevel;
							var newPosition = new atlas.data.Position(lonNum, latNum);

							marker.setOptions({
								position: newPosition,
								visible: true
							});
							map.setCamera({
								center: newPosition,
								zoom: zoomLevel,
								type: 'fly'
							});
						}
					}
					// If a backup was created, show the restore button.
					if (response.data.backup_created) {
						var modal = $('#gps2photos-modal');
						modal.find('.gps2photos-restore-coords-btn').show();
					}

					// If not NextGEN, update the attachment fields on the main page if they exist.
					if (!galleryName) {
						// Selector for attachment details modal (grid view).
						var latDMS = gps2photos_decimalToDMS(latitude, true);
						var lonDMS = gps2photos_decimalToDMS(longitude, false);

						$('input[name="attachments[' + attachmentId + '][gps_latitude]"]').val(latDMS);
						$('input[name="attachments[' + attachmentId + '][gps_longitude]"]').val(lonDMS);

						// Selector for attachment edit page (list view).
						$('#gps_latitude').val(latDMS);
						$('#gps_longitude').val(lonDMS);
					}

					setTimeout(function () { $messageDiv.fadeOut(); }, 5000);
				} else {
					$messageDiv.text(gps2photos_ajax.l10n.error_prefix + ' ' + response.data).removeClass('notice-success').removeClass('notice-warning').addClass('error').show();
				}
			},
			error: function () {
				$messageDiv.text(gps2photos_ajax.l10n.error_saving).removeClass('notice-success').removeClass('notice-warning').addClass('error').show();
				setTimeout(function () { $messageDiv.fadeOut(); }, 5000);
			},
			complete: function () {
				$button.text(originalText);
				$button.prop('disabled', false);
			}
		});
	});

	// ----------------------------------------------------------------------------------------------
	// Restore Coordinates button click handler.
	$(document).on('click', '.gps2photos-restore-coords-btn', function () {
		var $this = $(this);
		var attachmentId = $this.data('image-id');
		var galleryName = $(this).data('gallery-name');
		var filePath = $(this).data('file-path') || '';
		var $messageDiv = $('#gps2photos-modal-message').addClass('notice');
		var $restoreBtn = $this;

		if (!confirm(gps2photos_ajax.l10n.confirm_restore)) {
			return;
		}

		$messageDiv.removeClass('error notice-success').text(gps2photos_ajax.l10n.restoring).show();
		$restoreBtn.prop('disabled', true);

		$.ajax({
			url: gps2photos_ajax.ajaxurl,
			type: 'POST',
			data: {
				action: 'gps2photos_restore_from_backup',
				nonce: gps2photos_ajax.restore_gps_nonce,
				file_path: filePath
			},
			success: function (response) {
				if (response.success) {
					var restoredCoords = response.data.coords;
					$messageDiv.text(response.data.message).removeClass('error').addClass('notice-success').show();

					// Update the input fields with the restored coordinates.
					$('#gps2photos-modal-lat-input').val(restoredCoords.latitude.toFixed(6));
					$('#gps2photos-modal-lon-input').val(restoredCoords.longitude.toFixed(6));

					// Update the map marker position if map exists.
					if (window.gps2photos_maps.map) {
						var map = window.gps2photos_maps.map;
						var newPosition = new atlas.data.Position(restoredCoords.longitude, restoredCoords.latitude);
						var marker = window.gps2photos_maps.marker;
						var zoomLevel = window.gps2photos_maps.zoom;

						marker.setOptions({
							position: newPosition,
							visible: true
						});
						map.setCamera({
							center: newPosition,
							zoom: zoomLevel,
							type: 'fly'
						});
					}

					// Hide the restore button as the backup is now gone.
					$restoreBtn.hide();

					// If not NextGEN, update the attachment fields on the main page if they exist.
					if (!galleryName) {
						// Selector for attachment details modal (grid view).
						var restoredLatDMS = gps2photos_decimalToDMS(restoredCoords.latitude, true);
						var restoredLonDMS = gps2photos_decimalToDMS(restoredCoords.longitude, false);

						$('input[name="attachments[' + attachmentId + '][gps_latitude]"]').val(restoredLatDMS);
						$('input[name="attachments[' + attachmentId + '][gps_longitude]"]').val(restoredLonDMS);

						// Selector for attachment edit page (list view).
						$('#gps_latitude').val(restoredLatDMS);
						$('#gps_longitude').val(restoredLonDMS);
					}
				} else {
					$messageDiv.text(gps2photos_ajax.l10n.error_prefix + ' ' + response.data).removeClass('notice-success').addClass('error').show();
				}
			},
			error: function () {
				$messageDiv.text(gps2photos_ajax.l10n.error_restoring).removeClass('notice-success').addClass('error').show();
			},
			complete: function () {
				$restoreBtn.prop('disabled', false);
				setTimeout(function () { $messageDiv.fadeOut(); }, 5000);
			}
		});
	});
});
