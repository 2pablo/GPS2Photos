<?php
/**
 * Various functions and hooks used by the plugin when initiating or saving settings.
 *
 * @package    GPS 2 Photos Add-on
 * @subpackage Administration
 * @since      1.0.0
 *
 * @author     Pawel Block &lt;pblock@op.pl&gt;
 * @copyright  Copyright (c) 2025, Pawel Block
 * @link       http://geo2maps.pasart.net
 * @license    https://www.gnu.org/licenses/gpl-2.0.html
 */

/*
* This plugin uses the PHP Exif Library (PEL) by Martin Geisler.
* Copyright (C) 2004–2006 Martin Geisler
* Licensed under the GNU GPL. See COPYING for details.
*/

/**
 * Plugin Name: GPS 2 Photos
 * Plugin URI:  https://wordpress.org/plugins/gps-2-photo/
 * Description: GPS 2 Photo Add-on allows to add GPS coordinates to the photo EXIF data by selecting a location on a map.
 * Version:     1.0.0
 * Author URI:  http://geo2maps.pasart.net
 * License:     GNLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: gps-2-photos
 */


// Security: Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Defines the universal path to GPS 2 Photos directory in plugins folder.
define( 'GPS_2_PHOTOS_DIR_URL', plugins_url( '', __FILE__ ) );

// Defines the universal path to WordPress plugins folder.
define( 'GPS_2_PHOTOS_PLUGINS_DIR_URL', plugins_url( '', basename( __DIR__ ) ) );

if ( is_admin() ) {
	/**
	 * Includes only on the admin pages.
	 *
	 * @since 1.0.0
	 */
	include_once 'administration.php';
}

/**
 * Includes always.
 */
require_once 'functions.php';
require_once 'azure-map.php';

/**
 * Convert string integers to actual integers in an array.
 *
 * This function iterates over each element of an array. If an element is a string
 * that represents an integer, it converts the string to an actual integer. All
 * other elements are left unchanged.
 *
 * @since 1.0.0
 *
 * @param array|string $options The input array with WordPress plugin options.
 * @return array|string The output array with string integers converted to actual integers.
 */
function gps2photos_convert_to_int( $options ) {
	// Check if $options is an array needed during activation.
	if ( is_array( $options ) ) {
		return array_map(
			function ( $item ) {
				if ( is_string( $item ) && ctype_digit( $item ) ) {
					return intval( $item );
				}
				return $item;
			},
			$options
		);
	} else {
		return $options;
	}
}

/**
 * Creates an array of default plugin settings.
 *
 * Code run only on plugin activation/deactivation or when settings are saved,
 *
 * @since  1.0.0
 *
 * @see    gps2photos_options_validate(), gps2photos_options_activation(), gps2photos_options_deactivation()
 * @return array
 */
function gps2photos_defaults_array() {
	// Defines $options defaults.
	$defaults =
	array(
		'geo_azure_key'               => null,
		'geo_azure_auth_status'       => 0,     // 0 - not activated, 1 - activated
		'zoom'                        => '16',
		'map_height'                  => '80%',
		'map_width'                   => '80%',
		'map_fullscreen'              => 1, // This option shows Azure Maps button to open not in a full browser window but on the full physical screen.
		'map'                         => 'satellite_road_labels', // road/satellite/satellite_road_labels/grayscale_light/grayscale_dark/night/road_shaded_relief(high_contrast_dark/high_contrast_light-only in the Plus version).
		'gps_media_library'           => 1, // Add GPS info to  WP Media Library.
		'backup_existing_coordinates' => 1, // GPS coordinates will be added to the User Comment Exif field as "Original GPS coordinates:Latitude,Longitude" if not already there. If present they can be restored.
		'always_override_gps'         => 0, // Do not ask again and always override existing GPS coordinates in a Photo.
		'exif_error_handler'          => 0,
		// MAP OPTIONS.
		'dashboard'                   => 1, // Shows/hides map navigation controls.
		'locate_me_button'            => 1, // Shows/hides Locate Me button in the map's navigation controls.
		'scalebar'                    => 1, // Shows/hides scalebar from the map.
		'map_search_bar'              => 1, // Shows/hides the search bar on the map.
		'logo'                        => 1, // Shows/hides Azure logo in the left bottom corner.
		// PINS OPTIONS.
		'pin_icon_type'               => 'marker', // Predefined icons: "marker", "marker-thick", "marker-arrow", "marker-ball-pin", "flag", "flag-triangle", "pin".
		'pin_color'                   => 'rgba(0, 255, 0, 1)', // Pins for images. Color of the main pin on a map.
		'pin_secondary_color'         => 'rgba(0, 0, 0, 1)', // Pins for images. Color of the main pin on a map.
		'search_pin_color'            => 'rgba(0, 123, 255, 1)', // Color of the search result pins on a map.
		'search_pin_icon_type'        => 'pin',
		'restore_defaults'            => 0,
	);
	return $defaults;
}

register_activation_hook( __FILE__, 'gps2photos_options_activation' );
/**
 * Runs on plugin activation and adds default options to MySQL database.
 *
 * @since 1.0.0
 *
 * @see   gps2photos_defaults_array()
 */
function gps2photos_options_activation() {
	// Security check.
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$defaults = gps2photos_defaults_array();

	if ( ! get_option( 'plugin_gps2photos_options' ) ) {
		add_option( 'plugin_gps2photos_options', $defaults );
	}
}

register_deactivation_hook( __FILE__, 'gps2photos_options_deactivation' );
/**
 * Runs on plugin deactivation and conditionally restores plugins default options.
 *
 * @since 1.0.0
 *
 * @see   gps2photos_defaults_array()
 */
function gps2photos_options_deactivation() {
	// Security check.
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$defaults = gps2photos_defaults_array();
	$options  = gps2photos_convert_to_int( get_option( 'plugin_gps2photos_options' ) );

	if ( $options['restore_defaults'] === 1 ) {
		update_option( 'plugin_gps2photos_options', $defaults );
	}
}

/**
 * Validates color
 *
 * Checks if string representing a color format is correct.
 *
 * @since  1.0.0
 *
 * @see      gps2photos_options_validate(), gps2photos_shortcodes_ajax()
 * @param  string $text Color value.
 * @return string
 */
function gps2photos_validate_color( $text ) {
	$error_message = '';
	if ( substr( $text, 0, 1 ) === '#' ) {
		$trimmed_text = ltrim( $text, '#' );
		if ( strlen( $trimmed_text ) !== 3 && strlen( $trimmed_text ) !== 6 ) {
			$error_message = esc_html__( ' is not a valid hex color. Please enter i.e. #000 or #9900ff!', 'gps-2-photos' );
			$color         = '#ccc';
		} else {
			$color = sanitize_hex_color( $text );
		}
	} elseif ( substr( $text, 0, 4 ) === 'rgba' || substr( $text, 0, 4 ) === 'rgb(' ) {
		// PHP trim() function removes a set of characters, not specific string.
		$trimmed_text   = rtrim( ltrim( $text, 'rgba(' ), ' )' );
		$color_no_array = explode( ',', $trimmed_text );
		foreach ( $color_no_array as $number ) {
			$number = trim( $number ); // Removes whitespace.
			if ( ! is_numeric( $number ) || strlen( $number ) === 0 || strlen( $number ) > 4 ) {
				$error_message = esc_html__( ' Please enter a valid RGB(A) color!', 'gps-2-photos' );
				$color         = 'rgba(0,0,0,1)';
				break;
			} else {
				$color = $text;
			}
		}
	} else {
		$error_message = esc_html__( ' is not a valid color code. Please enter a correct hex or RGB(A) color!', 'gps-2-photos' );
	}

	if ( ! empty( $error_message ) ) {
		add_settings_error( 'plugin_gps2photo', 'invalid_color_number_error', $text . $error_message, 'error' );
	}
	return $color;
}

/**
 * Validate a text value and return it if valid, or default value if not.
 *
 * This function checks if the text is 'auto', a valid pixel value, or a valid
 * percentage. If the text is not valid, it adds an error message and returns
 * the default value.
 *
 * @since 1.0.0
 *
 * @param string $text The text to validate.
 * @param int    $max The maximum valid pixel value.
 * @param mixed  $default_value The default value to return if the text is not valid.
 * @return mixed The validated text or the default value.
 */
function gps2photos_validate_auto_number( $text, $max, $default_value ) {
		$error_message = sprintf(
			/* translators: %s: maximum pixel value */
			esc_html__( 'Please enter a number from 24 to %s, percentage 1-100%% or "auto"!', 'gps-2-photos' ),
			$max
		);

	if ( $text === 'auto' ) {
		return $text;
	} elseif ( strpos( $text, 'px' ) !== false ) {
		$string = str_replace( 'px', '', $text );
		if ( ! is_numeric( $string ) ) {
			add_settings_error( 'plugin_gps2photo', 'invalid_number_error', $error_message, 'error' );
			return $default_value;
		} elseif ( $string >= 24 && $string <= $max ) {
			return $text;
		} else {
			add_settings_error( 'plugin_gps2photo', 'invalid_number_error', $error_message, 'error' );
			return $default_value;
		}
	} elseif ( strpos( $text, '%' ) !== false ) {
		$string = str_replace( '%', '', $text );
		if ( ! is_numeric( $string ) ) {
			add_settings_error( 'plugin_gps2photo', 'invalid_number_error', $error_message, 'error' );
			return $default_value;
		} elseif ( $string > 0 && $string <= 100 ) {
			return $text;
		} else {
			add_settings_error( 'plugin_gps2photo', 'invalid_number_error', esc_html__( 'Please enter a number from 0% to 100%!', 'gps-2-photos' ), 'error' );
			return $default_value;
		}
	} else {
		add_settings_error( 'plugin_gps2photo', 'invalid_number_error', $error_message, 'error' );
		return $default_value;
	}
}

/**
 * Validates options ( administration )
 *
 * Runs when settings are registered - saved to MySQL Database
 *
 * @since  1.0.0
 * @see    gps2photos_options_init() in administration.php, gps2photos_defaults_array()
 * @param  array $input Array of option values.
 * @return array
 */
function gps2photos_options_validate( $input ) {
	// Gets variable values already saved on the server.
	$saved_options = gps2photos_convert_to_int( get_option( 'plugin_gps2photos_options' ) );

	// Invalid characters in API key for validation below.
	$special_chars = array( '?', '[', ']', '/', '\\', '=', '<', '>', ':', ';', ',', "'", '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', '%', '+', '’', '«', '»', '”', '“', chr( 0 ), '<', '>', '.', ',' );

	// Sample query - location to check if the Azure or the MapQuest key is working.
	$query = 'London';

	// Checks if API keys are already validated and saved to avoid overriding by default value.
	if ( $saved_options['geo_azure_auth_status'] === 1 ) {
		$input['geo_azure_auth_status'] = 1;
	}

	// Validates and sanitizes Microsoft Azure API Key.
	if ( strlen( $input['geo_azure_key'] ) !== 0 ) {
		if ( $saved_options['geo_azure_key'] !== $input['geo_azure_key'] ) {
			$check = 0;
			foreach ( $special_chars as $char ) {
				if ( strpos( $input['geo_azure_key'], $char ) !== false ) {
					$check = 1;
				}
			}
			if ( $check === 1 ) {
				add_settings_error( 'plugin_gps2photo', 'invalid_API_key_error', esc_html__( 'Please enter a valid API key! Special characters are not allowed.', 'gps-2-photos' ), 'error' );
				$input['geo_azure_key']         = '';
				$input['geo_azure_auth_status'] = 0;
			} else {
				// Sends sample query to the Azure REST server to validate credentials.
				// URL of Azure Maps REST Services Locations API.
				$base_url = 'https://atlas.microsoft.com/search/address/json';
				// Construct the final Locations API URI.
				$url = $base_url . '?api-version=1.0&subscription-key=' . $input['geo_azure_key'] . '&query=' . rawurlencode( $query );

				// Gets the response from the Search Address API and store it in a string.
				$response = wp_remote_get( $url );
				// Get the body of the response.
				$jsonfile = wp_remote_retrieve_body( $response );
				// Decode the json.
				if ( ! json_decode( $jsonfile, true ) ) {
					add_settings_error( 'plugin_gps2photo', 'Azure API key validation.', esc_html__( 'API key validation request failed when trying to decode Azure Maps server response!', 'gps-2-photos' ), 'error' );
					$input['geo_azure_auth_status'] = 0;
				} else {
					$response = json_decode( $jsonfile, true );
					// Check if there is an error in the response.
					if ( isset( $response['error'] ) ) {
						$status_code = wp_remote_retrieve_response_code( $response );
						add_settings_error( 'plugin_gps2photo', 'Azure API key validation error.', esc_html__( 'Azure API key validation unsuccessful!', 'gps-2-photos' ) . ' ' . esc_html__( 'Server response:', 'gps-2-photos' ) . ' ' . esc_html__( 'Status Code:', 'gps-2-photos' ) . ' ' . $status_code, 'error' );
						$input['geo_azure_auth_status'] = 0;
					} else {
						// Assume valid response if there's no error field.
						add_settings_error( 'plugin_gps2photo', 'Azure API key validation.', esc_html__( 'Azure Maps API key validation successful! You can start using GPS 2 Photos!', 'gps-2-photos' ), 'success' );
						$input['geo_azure_auth_status'] = 1;
					}
				}
			}
		}
	} else {
		$input['geo_azure_auth_status'] = 0;
	}

	// Clears undefined checkboxes (undefined = unchecked!).
	if ( ! isset( $input['gps_media_library'] ) ) {
		$input['gps_media_library'] = 0; }
	if ( ! isset( $input['always_override_gps'] ) ) {
		$input['always_override_gps'] = 0; }
	if ( ! isset( $input['exif_error_handler'] ) ) {
		$input['exif_error_handler'] = 0; }
	if ( ! isset( $input['backup_existing_coordinates'] ) ) {
		$input['backup_existing_coordinates'] = 0; }
	if ( ! isset( $input['dashboard'] ) ) {
		$input['dashboard'] = 0; }
	if ( ! isset( $input['locate_me_button'] ) ) {
		$input['locate_me_button'] = 0; }
	if ( ! isset( $input['scalebar'] ) ) {
		$input['scalebar'] = 0; }
	if ( ! isset( $input['map_search_bar'] ) ) {
		$input['map_search_bar'] = 0; }
	if ( ! isset( $input['map_fullscreen'] ) ) {
		$input['map_fullscreen'] = 0; }
	if ( ! isset( $input['logo'] ) ) {
		$input['logo'] = 0; }
	if ( ! isset( $input['restore_defaults'] ) ) {
		$input['restore_defaults'] = 0; }

	// Validates options.
	if ( strlen( $input['zoom'] ) !== 0 && $saved_options['zoom'] !== $input['zoom'] ) {
		if ( ! is_numeric( $input['zoom'] ) || $input['zoom'] > 24 || $input['zoom'] < 1 ) {
			unset( $input['zoom'] );
			add_settings_error( 'plugin_gps2photo', 'invalid_zoom_number_error', esc_html__( 'Please enter a valid number for Zoom Level in the range 1-24!', 'gps-2-photos' ), 'error' );
		}
	}

	if ( strlen( $input['map_height'] ) !== 0 && $saved_options['map_height'] !== $input['map_height'] ) {
		if ( is_numeric( $input['map_height'] ) && $input['map_height'] <= 4320 && $input['map_height'] >= 24 ) {
			$input['map_height'] = strval( $input['map_height'] ) . 'px';
		} else {
			$input['map_height'] = gps2photos_validate_auto_number( $input['map_height'], 4320, '80%' );
		}
	}

	if ( strlen( $input['map_width'] ) !== 0 && $saved_options['map_width'] !== $input['map_width'] ) {
		if ( is_numeric( $input['map_width'] ) && $input['map_width'] <= 7680 && $input['map_width'] >= 24 ) {
			$input['map_width'] = strval( $input['map_width'] ) . 'px';
		} else {
			$input['map_width'] = gps2photos_validate_auto_number( $input['map_width'], 7680, '400px' );
		}
	}

	// Validates colors.
	$colors = array(
		$input['pin_color']           => 'pin_color',
		$input['pin_secondary_color'] => 'pin_secondary_color',
		$input['search_pin_color']    => 'search_pin_color',
	);
	foreach ( $colors as $color => $key ) {
		if ( strlen( $color ) !== 0 ) {
				$input[ $key ] = gps2photos_validate_color( $color );
		}
	}

	// Options defaults.
	$defaults = gps2photos_defaults_array();
	// Parses it.
	$input = wp_parse_args( $input, $defaults );

	return $input;
}
