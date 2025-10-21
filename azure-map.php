<?php
/**
 * Azure Map function for GPS 2 Photos.
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
 * @return string The HTML for the modal content.
 */
function gps2photos_get_map_for_modal() {
	$options      = gps2photos_convert_to_int( get_option( 'plugin_gps2photos_options' ) );
	$html_output  = '
<div id="gps2photos-modal" class="gps2photos-modal">
	<div class="gps2photos-modal-content" style="max-width: ' . esc_attr( $options['map_width'] ) . '; max-height: ' . esc_attr( $options['map_height'] ) . ';">		
		<div class="gps2photos-modal-header">
			<h2>' . esc_html__( 'Add/Amend GPS Coordinates', 'gps-2-photos' ) . '
				<span class="gps2photos-tooltip-container">
					<img class="gps2photos-tooltip-trigger" src="' . esc_attr( GPS_2_PHOTOS_DIR_URL . '/img/information.png' ) . '" alt="Info">
					<span class="gps2photos-tooltip-text">'
						. esc_html__(
							'For manual entry for Latitude, please enter a value between -90 and 90. For Longitude, please enter a value between -180 and 180. Both Latitude and Longitude must be provided. If both fields will be left empty, this will erase the coordinates.',
							'gps-2-photos'
						) . '
					</span>
				</span>
			</h2>
			<span class="gps2photos-modal-close">&times;</span>
		</div>
		<div id="gps2photos-map-container" class="gps2photos-map-container">
			' . ( $options['map_search_bar'] ? '
			<div id="gps2photos-search-container">
				<input type="text" id="gps2photos-search-input" placeholder="' . esc_attr__( 'Search for a location...', 'gps-2-photos' ) . '" />
				<button type="button" id="gps2photos-search-btn" class="button">' . esc_html__( 'Search', 'gps-2-photos' ) . '</button>
				<button type="button" id="gps2photos-search-center-btn" class="button">' . esc_html__( 'Search Near Map Center', 'gps-2-photos' ) . '</button>
				<button type="button" id="gps2photos-clear-search-btn" class="button">' . esc_html__( 'Clear', 'gps-2-photos' ) . '</button>
			</div>
			' : '' ) . '
		</div>
		<div id="gps2photos-modal-inputs">
			<p>
                <label for="gps2photos-modal-lat-input">' . esc_html__( 'Latitude', 'gps-2-photos' ) . '</label><br/><input type="text" id="gps2photos-modal-lat-input" value="" style="width: 100%;" />
                <label for="gps2photos-modal-lon-input">' . esc_html__( 'Longitude', 'gps-2-photos' ) . '</label><br/><input type="text" id="gps2photos-modal-lon-input" value="" style="width: 100%;" /></p>';
	$checked      = isset( $options['always_override_gps'] ) && $options['always_override_gps'] === 1 ? 'checked' : '';
	$html_output .= '
			<p><input type="checkbox" id="gps2photos-override-checkbox" ' . $checked . '>&ensp;<label for="gps2photos-override-checkbox">' . esc_html__( 'Always override existing GPS coordinates without asking', 'gps-2-photos' ) . '</label></p>';
	$restore_btn  = esc_html__( 'Restore Original Coordinates', 'gps-2-photos' );
	$html_output .= '
			<div class="gps2photos-save-container">
				<button type="button" class="button button-primary gps2photos-save-coords-btn" data-image-id="" data-original-lat="" data-original-lon="" data-file-path="">' . esc_html__( 'Save Coordinates', 'gps-2-photos' ) . '</button>
				<div id="gps2photos-modal-message" class="gps2photos-modal-message" style="display: none;"></div>
			</div>
			<button type="button" class="button gps2photos-restore-coords-btn" data-image-id="" data-file-path="" style="display:none;">' . $restore_btn . '</button>
		</div>
	</div>
</div>';

	$map_output = '
// GPS 2 PHOTOS Azure Map
// Store map and marker instances globally to be accessible.
window.gps2photos_maps = window.gps2photos_maps || {};
window.gps2photos_maps.marker = window.gps2photos_maps.marker || {};

window.gps2photos_init_map = function(apiKey, position) {
    // Prevent re-initialization.
    if (window.gps2photos_maps.map) {
        return;
    }

    var latInput = document.getElementById("gps2photos-modal-lat-input");
    var lonInput = document.getElementById("gps2photos-modal-lon-input");

	var hasInitialCoords;

    if (!position) {
        var initialLat = parseFloat(latInput.value) || 30;
        var initialLon = parseFloat(lonInput.value) || 0;
		var hasInitialCoords = !!(latInput.value && lonInput.value);
    } else {
        var initialLat = position[0];
        var initialLon = position[1];
		var hasInitialCoords = !!(position[0] && position[1]);
    }

    var map = new atlas.Map("gps2photos-map-container", {
        renderWorldCopies: false,
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

	$map_output .= '

    window.gps2photos_maps.map = map;
    window.gps2photos_maps.zoom = ' . (int) $options['zoom'] . ';

    map.events.add("ready", function () {
        var marker;
        var searchURL;
        var searchDatasource;
        var popup;
		var searchResultsIconScale = 1;

        // Function to add/update the marker.
        function addMarker(position) {
            if (marker) {' . ( $options['map_search_bar'] ? '
                // When placing the main marker, deselect any selected search result.
                if (searchDatasource) {
                    deselectAllSearchResults();
                }' : '' ) . '

                marker.setOptions({ 
                    position: position,
                    visible: true
                });
            } else {
                createMarker(position);
            }
            latInput.value = position[1].toFixed(7);
            lonInput.value = position[0].toFixed(7);
        }

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

        function createMarker(position) {
            if (!marker) {
                // Create a custom SVG icon based on plugin settings.
                var pinColor = "' . esc_js( $options['pin_color'] ) . '";
                var secondaryColor = "' . esc_js( $options['pin_secondary_color'] ) . '";
                var iconType = "' . esc_js( $options['pin_icon_type'] ) . '";

                var svgIcon = atlas.getImageTemplate(iconType)
                    .replace(/{color}/g, pinColor)
                    .replace(/{secondaryColor}/g, secondaryColor);

				var h = 0; // horizontal pixel offset value to center not symmetric icons
				if (iconType === "flag") {
					// flag icon width is 23.5px /2 - 1px (half of flag post width)
					var h = 11; // pixel offset value to center the icon horizontally
				} else if (iconType === "flag-triangle") {
					// flag-triangle icon width is 27px
					var h = 12;
				}

                marker = new atlas.HtmlMarker({
                    position: position,
                    draggable: true,
                    htmlContent: svgIcon,
                    visible: hasInitialCoords,
					pixelOffset: [h, 0] // corrects horizontal position
                });
                map.markers.add(marker);
                window.gps2photos_maps.marker = marker;

                // Update inputs when dragging ends.
                map.events.add("dragend", marker, function () {
                    var pos = marker.getOptions().position;
                    latInput.value = pos[1].toFixed(7);
                    lonInput.value = pos[0].toFixed(7);
                });
                return marker;
            }
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
		// Waits until the user stops typing (e.g., ~500 ms of no input),
		// unless the user pastes a complete coordinate string.
		function updateMarkerFromInputs() {
			var lat = parseFloat(latInput.value);
			var lon = parseFloat(lonInput.value);
			
			// Validate that both are numbers and within valid ranges.
			if (!isNaN(lat) && !isNaN(lon) && lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180) {
				var newPosition = [lon, lat];
				addMarker(newPosition);
				map.setCamera({
					center: newPosition,
					duration: 2000,
					type: "fly"
				});
			}
		}

        var updateTimer = null; //The id of the timer.
		var isPasting = false;

		function handleInput(event) {
			// If user pastes text (usually fast, not typed), allow immediate update
			var wasPasted = event.inputType === "insertFromPaste" || isPasting;
			
			if (wasPasted) {
				// Run immediately on paste
				isPasting = false;
				updateMarkerFromInputs();
			} else {
				clearTimeout(updateTimer);
				// Wait until user stops typing (1000ms)
				updateTimer = setTimeout(updateMarkerFromInputs, 1000);
			}
		}

		//Track paste events
		latInput.addEventListener("paste", function() {
			isPasting = true;
		});
		lonInput.addEventListener("paste", function() {
			isPasting = true;
		});

        // Add event listeners to the input fields to update the marker.
        latInput.addEventListener("input", handleInput);
        lonInput.addEventListener("input", handleInput);';

	if ( $options['map_search_bar'] ) {

		if ( $options['search_pin_icon_type'] === 'flag' || $options['search_pin_icon_type'] === 'flag-triangle' ) {
			$anchor = 'bottom-left';
		} else {
			$anchor = 'bottom';
		}

		$map_output .= '
    // --- Popup for Search Results ---
    popup = new atlas.Popup({
        pixelOffset: [0, -18],
        closeButton: true
    });

    	// --- Fuzzy Search Implementation ---
        var pipeline = atlas.service.MapsURL.newPipeline(new atlas.service.MapControlCredential(map));
        searchURL = new atlas.service.SearchURL(pipeline);

        // Create a data source for search results.
        searchDatasource = new atlas.source.DataSource();
        map.sources.add(searchDatasource);

        // Create a custom SVG icon for search results.
        var searchIconType = "' . esc_js( $options['search_pin_icon_type'] ) . '";
        var searchIconColor = "' . esc_js( $options['search_pin_color'] ) . '";
        var searchSecondaryColor = "' . esc_js( $options['pin_secondary_color'] ) . '";
        var searchSvgIcon = atlas.getImageTemplate(searchIconType)
            .replace(/{color}/g, searchIconColor)
            .replace(/{secondaryColor}/g, searchSecondaryColor)
			.replace(/{text}/, "").replace(/{scale}/, searchResultsIconScale);

		// Create a selected version of the icon using the main pin color.
		var selectedSearchIconColor = "' . esc_js( $options['pin_color'] ) . '";
		var selectedSearchSvgIcon = atlas.getImageTemplate(searchIconType)
			.replace(/{color}/g, selectedSearchIconColor)
			.replace(/{secondaryColor}/g, searchSecondaryColor)
			.replace(/{text}/, "").replace(/{scale}/, searchResultsIconScale);

		map.imageSprite.add("searchResultIcon", searchSvgIcon);
		map.imageSprite.add("searchResultSelectedIcon", selectedSearchSvgIcon);

        // Add a layer for rendering the search results.
        var resultsLayer = new atlas.layer.SymbolLayer(searchDatasource, null, {
            iconOptions: {
                // Use a data-driven expression to change the icon based on the "isSelected" property.
                image: [
                    "case",
                    ["get", "isSelected"],
                    "searchResultSelectedIcon", // Use this icon if isSelected is true
                    "searchResultIcon"      // Use this icon otherwise (default)
                ],
                allowOverlap: true,
                ignorePlacement: true,
				anchor: "' . $anchor . '",
            }
        });
        map.layers.add(resultsLayer);

        // Function to deselect all search results.
        function deselectAllSearchResults() {
            var features = searchDatasource.getShapes();
            features.forEach(function(feature) {
                feature.addProperty("isSelected", false);
            });
            // This forces the datasource to re-evaluate and update the layer.
            searchDatasource.setShapes(features);
        }

        // Handle clicks on search result symbols.
        function symbolClicked(e) {
            if (e.shapes && e.shapes.length > 0 && e.shapes[0].getType() === "Point") {
                var clickedFeature = e.shapes[0];
                var properties = clickedFeature.getProperties();
                var coordinates = clickedFeature.getCoordinates();

                // First, deselect all other features.
                deselectAllSearchResults();

                // Now, select the clicked feature.
                var featureToSelect = searchDatasource.getShapeById(clickedFeature.getId());
                if (featureToSelect) {
                    featureToSelect.addProperty("isSelected", true);
                }
                // Update lat/lon inputs.
                latInput.value = coordinates[1].toFixed(7);
                lonInput.value = coordinates[0].toFixed(7);
				
                // Hide the main draggable marker.
                if (window.gps2photos_maps.marker) {
                    window.gps2photos_maps.marker.setOptions({ visible: false });
                }

                //Using the properties, create HTML to fill the popup with useful information.
                var html = ["<div style=\"padding:10px;\"><span style=\"font-size:14px;font-weight:bold;\">"];
                var addressInTitle = false;

                if (properties.type === "POI" && properties.poi && properties.poi.name) {
                    html.push(properties.poi.name);
                } else if (properties.address && properties.address.freeformAddress) {
                    html.push(properties.address.freeformAddress);
                    addressInTitle = true;
                }

                html.push("</span><br/>");

                if (!addressInTitle && properties.address && properties.address.freeformAddress) {
                    html.push(properties.address.freeformAddress, "<br/>");
                }

                html.push("<b>Type: </b>", properties.type, "<br/>");

                if (properties.entityType) {
                    html.push("<b>Entity Type: </b>", properties.entityType, "<br/>");
                }

                if (properties.type === "POI" && properties.poi) {
                    if (properties.poi.phone) {
                        html.push("<b>Phone: </b>", properties.poi.phone, "<br/>");
                    }

                    if (properties.poi.url) {
                        html.push("<b>URL: </b>", properties.poi.url, "<br/>");
                    }

                    if (properties.poi.classifications) {
                        html.push("<b>Classifications:</b><br/>");
                        for (var i = 0; i < properties.poi.classifications.length; i++) {
                            for (var j = 0; j < properties.poi.classifications[i].names.length; j++) {
                                html.push(" - ", properties.poi.classifications[i].names[j].name, "<br/>");
                            }
                        }
                    }
                }

                html.push("</div>");

                //Set the popup options.
                popup.setOptions({
                    content: html.join(""),
                    position: coordinates
                });

                //Open the popup.
                popup.open(map);
            }
        }
        map.events.add("click", resultsLayer, symbolClicked);

        // Function to perform the search.
        function performSearch(lat, lon) {
            var query = document.getElementById("gps2photos-search-input").value;
            if (popup) popup.close();
            searchDatasource.clear();

            searchURL.searchFuzzy(atlas.service.Aborter.timeout(10000), query, {
                lat: lat,
                lon: lon,
                radius: 100000, // 100km radius
                view: "Auto"
            }).then(results => {
                var data = results.geojson.getFeatures();
                searchDatasource.add(data);
				deselectAllSearchResults();

                if (data && data.bbox) {
                    map.setCamera({
                        bounds: data.bbox,
                        padding: 40
                    });
                }
            });
        }

        // Wire up the search buttons.
        document.getElementById("gps2photos-search-btn").onclick = function() {
            performSearch(); // Search without location bias.
        };

        document.getElementById("gps2photos-search-center-btn").onclick = function() {
            var cam = map.getCamera();
            performSearch(cam.center[1], cam.center[0]); // Search with map center bias.
        };

        document.getElementById("gps2photos-clear-search-btn").onclick = function() {
            if (popup) popup.close();
            searchDatasource.clear();
            document.getElementById("gps2photos-search-input").value = "";

            // Get coordinates from input fields and place the main marker.
            var lat = parseFloat(latInput.value);
            var lon = parseFloat(lonInput.value);

            if (!isNaN(lat) && !isNaN(lon) && lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180) {
                addMarker([lon, lat]);
            } else if (marker) {
                // If inputs are invalid/empty, just ensure the marker is visible.
                marker.setOptions({ visible: false });
            }
        };

        function closePopup() {
            if (popup) popup.close();
        }
    	// --- End Fuzzy Search ---';
	}
	$map_output .= '
	});
}';
	wp_add_inline_script( 'gps2photos-map-js', $map_output );
	return $html_output;
}
