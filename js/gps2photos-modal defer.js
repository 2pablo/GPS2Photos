jQuery(document).ready(function ($) {
	'use strict';

	// Use event delegation for buttons that might be added dynamically.
	$(document).on('click', '[id^="gps2photos-open-map-btn-"]', function () {
		var attachmentId = $(this).attr('id').replace('gps2photos-open-map-btn-', '');
		openModal(attachmentId);
	});

	// Handle click on our custom action link for NextGEN Gallery
	$(document).on('click', '.gps2photos-add-gps', function (e) {
		e.preventDefault();
		var pid = $(this).data('pid');
		var imageUrl = $(this).data('image-url');
		// For NextGEN, we use a generic modal but pass specific image data.
		openModal("1", imageUrl, true, pid);
	});

	// Function to open the modal and initialize the map
	function openModal(imageId, imagePath, isNextGen = false, pid = null) {
		var modal = $('#gps2photos-modal-' + imageId);
		var $saveBtn = modal.find('.gps2photos-save-coords-btn');
		var originalLat = isNextGen ? '' : $saveBtn.data('original-lat'); // Always force fetch for NextGEN to get fresh data

		if (modal.length) {
			modal.css('display', 'flex');

			var mapInitDeferred = $.Deferred();
			var coordsFetchDeferred = $.Deferred();

			// For NextGEN, we need to update the image-id and pid on the buttons
			if (isNextGen) {
				modal.find('.gps2photos-save-coords-btn').data('image-id', pid).data('is-nextgen', '1');
				modal.find('.gps2photos-restore-coords-btn').data('image-id', pid).data('is-nextgen', '1');
			}

			// 1. Initialize the map
			if (typeof window['gps2photos_init_map_' + imageId] === 'function') {
				// The map initialization is asynchronous. We pass the deferred object
				// to the init function, which will resolve it when the map is 'ready'.
				var initAndResolve = function (apiKey) {
					// The init function will be modified to accept the deferred object.
					// It will be responsible for calling .resolve(map) on it.
					window['gps2photos_init_map_' + imageId](apiKey, mapInitDeferred);
				};

				if (window.gps2photos_azure_api_key) {
					initAndResolve(window.gps2photos_azure_api_key);
				} else {
					// gps2photos_get_azure_api_key is also async.
					gps2photos_get_azure_api_key(function (apiKey) {
						initAndResolve(apiKey);
					});
				}
			} else {
				mapInitDeferred.resolve(null); // Resolve immediately if no init function
			}

			// 2. Fetch coordinates (for NextGEN or if not pre-loaded)
			if (originalLat === '') {
				$.ajax({
					url: gps2photos_ajax.ajaxurl,
					type: 'POST',
					data: {
						action: 'gps2photos_get_coordinates',
						nonce: gps2photos_ajax.get_gps_nonce,
						image_id: isNextGen ? pid : imageId,
						imagePath: imagePath,
						is_nextgen: isNextGen ? '1' : '0'
					},
					success: function (response) {
						if (response.success) {
							coordsFetchDeferred.resolve(response.data);
						} else {
							coordsFetchDeferred.reject(response.data);
						}
					},
					error: function () {
						console.error('Ajax failed to fetch GPS coordinates.');
						coordsFetchDeferred.reject();
					}
				});
			} else {
				// For Media Library, resolve with pre-loaded data.
				// backup_exists is not known here, but the button's visibility is already set correctly from PHP.
				coordsFetchDeferred.resolve({
					latitude: originalLat,
					longitude: $saveBtn.data('original-lon'),
					file_path: $saveBtn.data('file-path'),
					backup_exists: modal.find('.gps2photos-restore-coords-btn').is(':visible')
				});
			}

			// 3. When both are done, update the UI and map
			$.when(mapInitDeferred, coordsFetchDeferred).done(function (map, coordsResult) {
				if (!coordsResult) {
					return;
				}

				var lat = coordsResult.latitude || '';
				var lon = coordsResult.longitude || '';
				var filePath = coordsResult.file_path || '';
				var backupExists = coordsResult.backup_exists || false;
				var modalId = isNextGen ? '1' : imageId;

				$('#gps2photos-modal-lat-input-' + modalId).val(lat);
				$('#gps2photos-modal-lon-input-' + modalId).val(lon);

				// This data is only missing for the NextGEN AJAX case.
				if (isNextGen) {
					$saveBtn.data('original-lat', lat).data('original-lon', lon);
					modal.find('.gps2photos-save-coords-btn, .gps2photos-restore-coords-btn').data('file-path', filePath);
				}

				var marker = window.gps2photos_markers[imageId]; // The marker is created in gps2photos_init_map
				if (map && marker) {
					var zoomLevel = window.gps2photos_maps['zoom'] || map.getCamera().zoom;
					if (lat && lon) {
						var newPosition = new atlas.data.Position(parseFloat(lon), parseFloat(lat));
						marker.setOptions({ position: newPosition, visible: true });
						map.setCamera({ center: newPosition, zoom: zoomLevel });
					} else {
						marker.setOptions({ visible: false });
						map.setCamera({ center: [0, 30], zoom: 1 });
					}
				}

				var $restoreBtn = modal.find('.gps2photos-restore-coords-btn');
				if (backupExists) {
					$restoreBtn.show();
				} else {
					$restoreBtn.hide();
				}
			});
		}
	}

	// Handle closing the modal
	$(document).on('click', '.gps2photos-modal-close', function () {
		$(this).closest('.gps2photos-modal').hide();
	});

	// Handle clicking outside the modal to close it
	$(window).on('click', function (event) {
		if ($(event.target).is('.gps2photos-modal')) {
			$(event.target).hide();
		}
	});

	// Handle saving the coordinates
	$(document).on('click', '.gps2photos-save-coords-btn', function () {
		var attachmentId = $(this).data('image-id');
		var filePath = $(this).data('file-path') || '';
		var $button = $(this);
		var originalText = $button.text();
		var originalLat = $button.data('original-lat');
		var originalLon = $button.data('original-lon');
		var isNextGen = $(this).data('is-nextgen') === '1';
		// For NextGEN, the modal ID is always 1, but the attachmentId is the pid
		var modalId = isNextGen ? '1' : attachmentId;
		var latitude = $('#gps2photos-modal-lat-input-' + modalId).val().trim();
		var longitude = $('#gps2photos-modal-lon-input-' + modalId).val().trim();
		var $overrideCheckbox = $('#gps2photos-override-checkbox-' + modalId);
		var $messageDiv = $('#gps2photos-modal-message-' + modalId).addClass('notice');
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
		// Use a small tolerance for float comparison
		if (latitude !== '' && longitude !== '' && Math.abs(latNum - originalLat) < 0.000001 && Math.abs(lonNum - originalLon) < 0.000001) {
			$messageDiv.text(gps2photos_ajax.l10n.coords_not_changed).addClass('gps2photos-notice').fadeIn();
			setTimeout(function () {
				$messageDiv.fadeOut(function () {
					$(this).removeClass('gps2photos-notice').text('');
				});
			}, 435553000);
			return; // Stop execution
		} else if (latitude === '' && longitude === '' && (originalLat === '' || originalLat === undefined) && (originalLon === '' || originalLon === undefined)) {
			$messageDiv.text(gps2photos_ajax.l10n.coords_already_empty).addClass('gps2photos-notice').fadeIn();
			setTimeout(function () {
				$messageDiv.fadeOut(function () {
					$(this).removeClass('gps2photos-notice').text('');
				});
			}, 3000);
			return; // Stop execution
		}

		var ajaxData = {
			action: 'gps2photos_save_coordinates',
			nonce: gps2photos_ajax.save_gps_nonce,
			attachment_id: modalId,
			latitude: latitude,
			longitude: longitude,
			file_path: filePath
		};

		// Check if the image has original GPS data (the checkbox will exist)
		if ($overrideCheckbox.length > 0) {
			// If the override checkbox is NOT checked, ask for confirmation
			if (!$overrideCheckbox.is(':checked')) {
				if (!confirm(gps2photos_ajax.l10n.confirm_override)) {
					return; // Stop if the user cancels
				}
			}
			// Include the state of the checkbox to update the global setting
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
					$messageDiv.text(response.data.message).removeClass('error').addClass('notice-success').show();

					// Update original data attributes to prevent re-saving without changes
					$button.data('original-lat', latNum);
					$button.data('original-lon', lonNum);

					// Update the map marker position if map exists
					if (window.gps2photos_maps && window.gps2photos_maps[modalId]) {
						var map = window.gps2photos_maps[modalId];
						var marker = window.gps2photos_markers[modalId];
						var zoomLevel = window.gps2photos_maps['zoom'];

						if (!latNum || !lonNum) {
							// No GPS data, hide the marker and reset view
							if (marker) {
								marker.setOptions({ visible: false });
							}
							map.setCamera({ center: [0, 30], zoom: 1, type: 'fly' });
						} else {
							zoomLevel = map.getCamera().zoom || zoomLevel;
							var newPosition = new atlas.data.Position(lonNum, latNum);
							if (marker) {
								marker.setOptions({ position: newPosition, visible: true });
							}
							map.setCamera({ center: newPosition, zoom: zoomLevel, type: 'fly' });
						}
					}
					console.log('Response data:', response.data);
					// If a backup was created, show the restore button.
					if (response.data.backup_created) {
						var modal = $('#gps2photos-modal-' + modalId);
						modal.find('.gps2photos-restore-coords-btn').show();
					}

					setTimeout(function () { $messageDiv.fadeOut(); }, 5000);
				} else {
					$messageDiv.text(gps2photos_ajax.l10n.error_prefix + ' ' + response.data).removeClass('notice-success').addClass('error').show();
				}
			},
			error: function () {
				$messageDiv.text(gps2photos_ajax.l10n.error_saving).removeClass('notice-success').addClass('error').show();
				setTimeout(function () { $messageDiv.fadeOut(); }, 5000);
			},
			complete: function () {
				$button.text(originalText);
				$button.prop('disabled', false);
			}
		});
	});

	// Restore Coordinates button click handler
	$(document).on('click', '.gps2photos-restore-coords-btn', function () {
		var $this = $(this);
		var attachmentId = $this.data('image-id');
		var isNextGen = $(this).data('is-nextgen') === '1';
		var filePath = $(this).data('file-path') || '';
		var $messageDiv = $('#gps2photos-modal-message-' + attachmentId).addClass('notice');
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
				attachment_id: attachmentId,
				file_path: filePath
			},
			success: function (response) {
				if (response.success) {
					var restoredCoords = response.data.coords;
					$messageDiv.text(response.data.message).removeClass('error').addClass('notice-success').show();

					var modalId = isNextGen ? '1' : attachmentId;
					// Update the input fields with the restored coordinates
					$('#gps2photos-modal-lat-input-' + modalId).val(restoredCoords.latitude.toFixed(6));
					$('#gps2photos-modal-lon-input-' + modalId).val(restoredCoords.longitude.toFixed(6));

					// Update the save button's original data to match the restored coordinates
					var $saveBtn = $('#gps2photos-modal-' + modalId).find('.gps2photos-save-coords-btn');
					$saveBtn.data('original-lat', restoredCoords.latitude);
					$saveBtn.data('original-lon', restoredCoords.longitude);

					// Update the map marker position if map exists
					if (window.gps2photos_maps && window.gps2photos_maps[modalId]) {
						var map = window.gps2photos_maps[modalId];
						var newPosition = new atlas.data.Position(restoredCoords.longitude, restoredCoords.latitude);
						var marker = window.gps2photos_markers[modalId];
						var zoomLevel = window.gps2photos_maps['zoom'] || map.getCamera().zoom || 10;

						marker.setOptions({
							position: newPosition,
							visible: true
						});
						map.setCamera({
							center: newPosition,
							zoom: zoomLevel
						});
					}

					// Hide the restore button as the backup is now gone
					$restoreBtn.hide();
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
