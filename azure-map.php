<?php
/**
 * Azure Map functions for GPS 2 Photos.
 *
 * @package    GPS 2 Photos Add-on
 * @subpackage Azure Map
 * @since      1.0.0
 */

// Security: Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the content for the modal window.
 *
 * @param array  $options The plugin options.
 * @param int    $id_no The attachment ID.
 * @param string $file_path The attachment (image) path.
 * @param array  $gps_data The GPS coordinates.
 * @return string The HTML for the modal content.
 */
function gps2photos_get_map_for_modal( $options, $id_no, $file_path = null, $gps_data ) {
	// If gps_data is not pre-loaded, the inputs will be empty.
	// JS will fetch the data on-demand.
	$lat = ( is_array( $gps_data ) && isset( $gps_data['latitude'] ) ) ? $gps_data['latitude'] : '';
	$lon = ( is_array( $gps_data ) && isset( $gps_data['longitude'] ) ) ? $gps_data['longitude'] : '';
	$idn = intval( $id_no );

	$output  = '
<div id="gps2photos-modal-' . $idn . '" class="gps2photos-modal">
	<div class="gps2photos-modal-content" style="max-width: ' . $options['map_width'] . '; max-height: ' . $options['map_height'] . ';">
		<span class="gps2photos-modal-close">&times;</span>
		<h2>' . esc_html__( 'Add/Amend GPS Coordinates', 'gps-2-photos' ) . '
			<span class="gps2photos-tooltip-container">
				<img class="gps2photos-tooltip-trigger" src="' . esc_attr( GPS_2_PHOTOS_DIR_URL . '/img/information.png' ) . '" alt="Info"><span class="gps2photos-tooltip-text">'
					. esc_html__(
						'For manual entry for Latitude please enter a value between -90 and 90. For Longitude please enter a value between -180 and 180. Both Latitude and Longitude must be provided. If both fields will be left empty this will erase coordinates.',
						'gps-2-photos'
					) . '
				</span>
			</span>
		</h2>
		<div id="gps2photos-map-container-' . $idn . '" class="gps2photos-map-container"></div>
		<div id="gps2photos-modal-inputs">
			<p><label for="gps2photos-modal-lat-input-' . $idn . '">' . esc_html__( 'Latitude', 'gps-2-photos' ) . '</label><br/><input type="text" id="gps2photos-modal-lat-input-' . $idn . '" value="' . esc_attr( $lat ) . '" style="width: 100%;" /></p>
			<p><label for="gps2photos-modal-lon-input-' . $idn . '">' . esc_html__( 'Longitude', 'gps-2-photos' ) . '</label><br/><input type="text" id="gps2photos-modal-lon-input-' . $idn . '" value="' . esc_attr( $lon ) . '" style="width: 100%;" /></p>';
	$checked = isset( $options['always_override_gps'] ) && $options['always_override_gps'] === 1 ? 'checked' : '';
	$output .= '
			<p><input type="checkbox" id="gps2photos-override-checkbox-' . $idn . '" ' . $checked . '>&ensp;<label for="gps2photos-override-checkbox-' . $idn . '">' . esc_html__( 'Always override existing GPS coordinates without asking', 'gps-2-photos' ) . '</label></p>';
	// Check for backup coordinates to determine if the restore button should be visible.
	$backup_coords     = gps2photos_get_backup_coordinates( $file_path );
	$restore_btn_style = $backup_coords ? '' : ' style="display:none;"';
	$restore_btn_text  = esc_html__( 'Restore Original Coordinates', 'gps-2-photos' );
	$output           .= '
			<div class="gps2photos-save-container">
				<button type="button" class="button button-primary gps2photos-save-coords-btn" data-image-id="' . $idn . '" data-original-lat="' . esc_attr( $lat ) . '" data-original-lon="' . esc_attr( $lon ) . '" data-file-path="' . esc_attr( $file_path ) . '">' . esc_html__( 'Save Coordinates', 'gps-2-photos' ) . '</button>
				<div id="gps2photos-modal-message-' . $idn . '" class="gps2photos-modal-message" style="display: none;"></div>
			</div>
			<p><button type="button" class="button gps2photos-restore-coords-btn" data-image-id="' . $idn . '" data-file-path="' . esc_attr( $file_path ) . '" ' . $restore_btn_style . '>' . $restore_btn_text . '</button></p>
		</div>
	</div>
</div>
<script>
    // Store map and marker instances globally to be accessible.
    window.gps2photos_maps = window.gps2photos_maps || {};
    window.gps2photos_markers = window.gps2photos_markers || {};

    function gps2photos_init_map_' . $idn . '(apiKey, position) {
        // Prevent re-initialization.
        if (window.gps2photos_maps[' . $idn . ']) {
            return;
        }

        var idn = ' . $idn . ';

		var latInput = document.getElementById("gps2photos-modal-lat-input-" + idn);
		var lonInput = document.getElementById("gps2photos-modal-lon-input-" + idn);

		if (!position) {
        	var initialLat = parseFloat(latInput.value) || 30;
       		var initialLon = parseFloat(lonInput.value) || 0;
		} else {
			var initialLat = position[0];
			var initialLon = position[1];
		}

        var hasInitialCoords = !!(latInput.value && lonInput.value);

        var map = new atlas.Map("gps2photos-map-container-" + idn, {
            authOptions: {
                authType: "subscriptionKey",
                subscriptionKey: apiKey
            },
            center: [initialLon, initialLat],
            zoom: hasInitialCoords ? ' . (int) $options['zoom'] . ' : 1,
            style: "' . esc_js( $options['map'] ) . '",
            showLogo: ' . ( $options['logo'] ? 'true' : 'false' ) . ',
            showFeedbackLink: false,
            view: "Auto"
        });';
	// About the "view" parameter: By default, the View parameter is set to Unified, even if you haven't defined it in the request. Determine the location of your users. Then, set the View parameter correctly for that location. Alternatively, you can set 'View=Auto', which returns the map data based on the IP address of the request. The View parameter in Azure Maps must be used in compliance with applicable laws, including those laws about mapping of the country/region where maps, images, and other data and third-party content that you're authorized to access via Azure Maps is made available.
	// https://learn.microsoft.com/en-us/azure/azure-maps/supported-languages#azure-maps-supported-views.

	$output .= '

        window.gps2photos_maps[idn] = map;
		window.gps2photos_maps["zoom"] = ' . (int) $options['zoom'] . ';

        map.events.add("ready", function () {
            var style = "auto"; // "auto", "light", "dark"
            if (' . ( $options['dashboard'] ? 'true' : 'false' ) . ') {
                map.controls.add([
                        new atlas.control.StyleControl({
                            style: style,
                            mapStyles: ["road", "satellite", "satellite_road_labels", "grayscale_light", "grayscale_dark", "night", "road_shaded_relief"] // "all" (shows some blank styles)
                        }),
                        new atlas.control.ZoomControl({style: style}),
                        new atlas.control.CompassControl({style: style}),
                        new atlas.control.PitchControl({style: style}),' . ( $options['scalebar'] ? '
                        new atlas.control.ScaleControl({
                            maxWidth: 100,
                            units: "metric" // or "imperial", "nautical"
                        }),' : '' ) . ( $options['locate_me_button'] ? '
                        new atlas.control.GeolocationControl({
                            style: style,
                            showUserLocation: true,
                        })' : '' ) . '
                    ], {
                        position: "top-right" // Options: "top-left", "top-right", "bottom-left", "bottom-right", "non-fixed"
                });
            }
            map.controls.add([
                        new atlas.control.FullscreenControl({style: style})
                    ], {
                        position: "top-left" // Options: "top-left", "top-right", "bottom-left", "bottom-right", "non-fixed"
                });
            var marker;

			function createMarker(position) {
                if (!marker) {
                    // Create a custom SVG icon based on plugin settings.
                    var pinColor = "' . esc_js( $options['pin_color'] ) . '";
                    var secondaryColor = "' . esc_js( $options['pin_secondary_color'] ) . '";
                    var iconType = "' . esc_js( $options['pin_icon_type'] ) . '";

                    var svgIcon = atlas.getImageTemplate(iconType)
                        .replace(/{color}/g, pinColor)
                        .replace(/{secondaryColor}/g, secondaryColor);

                    marker = new atlas.HtmlMarker({
                        position: position,
                        draggable: true,
                        htmlContent: svgIcon,
						visible: hasInitialCoords
                    });
                    map.markers.add(marker);
                    window.gps2photos_markers[idn] = marker;

                    // Update inputs when dragging ends.
                    map.events.add("dragend", marker, function () {
                        var pos = marker.getOptions().position;
                        latInput.value = pos[1].toFixed(6);
                        lonInput.value = pos[0].toFixed(6);
                    });
					return marker;
                }
            }
	
            function addMarker(position) {
                if (marker) {
                    marker.setOptions({ 
						position: position,
						visible: true
					});
                } else {
                    createMarker(position);
                }
                latInput.value = position[1].toFixed(6);
                lonInput.value = position[0].toFixed(6);
            }

            // If coordinates exist, add the marker right away.
            if (hasInitialCoords) {
                addMarker([initialLon, initialLat]);
            } else {
				createMarker([0, 30]); // Default position if no initial coordinates.
			}

            // Add a marker on map click if one doesn\'t exist.
            map.events.add("click", function (e) {
                addMarker(e.position);
            });

            // Function to update marker from input fields.
            function updateMarkerFromInputs() {
                var lat = parseFloat(latInput.value);
                var lon = parseFloat(lonInput.value);

                // Validate that both are numbers and within valid ranges.
                if (!isNaN(lat) && !isNaN(lon) && lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180) {
                    var newPosition = [lon, lat];
                    addMarker(newPosition);
                    map.setCamera({
                        center: newPosition
                    });
                }
            }

            // Add event listeners to the input fields to update the marker.
            latInput.addEventListener("input", updateMarkerFromInputs);
            lonInput.addEventListener("input", updateMarkerFromInputs);
        });
    }
</script>';

	return $output;
}
