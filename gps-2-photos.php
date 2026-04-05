<?php
/**
 * Various functions and hooks used by the plugin when initiating or saving settings.
 *
 * @package    GPS 2 Photos Add-on
 * @subpackage Administration
 * @since      1.0.0
 *
 * @author     Pawel Block <pb@pasart.net>
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
 * Plugin URI:  https://wordpress.org/plugins/gps-2-photos/
 * Description: GPS 2 Photo Add-on allows to add GPS coordinates to the photo EXIF data by selecting a location on a map.
 * Version:     1.0.1
 * Author URI:  http://geo2maps.pasart.net
 * License:     GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: gps-2-photos
 */

// Security: Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Defines the universal path to GPS 2 Photos directory in plugins folder.
define( 'GPS2PHOTOS_DIR_URL', plugins_url( '', __FILE__ ) );

// Defines the universal path to WordPress plugins folder.
define( 'GPS2PHOTOS_PLUGINS_DIR_URL', plugins_url( '', basename( __DIR__ ) ) );

// Defines admin page slug (can differ from folder/text-domain names).
define( 'GPS2PHOTOS_ADMIN_SLUG', 'gps-2-photos' );

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
 * @see   gps2photos_options_page() in administration.php
 * @see   gps2photos_get_map_for_modal() in azure-map.php
 * @see   gps2photos_add_attachment_fields_to_edit() in functions.php
 * @see   gps2photos_get_coordinates_callback() in functions.php
 * @see   gps2photos_save_coordinates_callback() in functions.php
 * @see   gps2photos_save_gps_to_jpeg() in functions.php
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
 * @see    gps2photos_options_validate()
 * @see    gps2photos_options_activation()
 * @see    gps2photos_options_deactivation()
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
 */
function gps2photos_options_activation() {
	// Security check.
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$defaults = gps2photos_defaults_array();

	if ( ! get_option( 'gps2photos_options' ) ) {
		add_option( 'gps2photos_options', $defaults );
	}
}

register_deactivation_hook( __FILE__, 'gps2photos_options_deactivation' );
/**
 * Runs on plugin deactivation and conditionally restores plugins default options.
 *
 * @since 1.0.0
 */
function gps2photos_options_deactivation() {
	// Security check.
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$defaults = gps2photos_defaults_array();
	$options  = gps2photos_convert_to_int( get_option( 'gps2photos_options' ) );

	if ( $options['restore_defaults'] === 1 ) {
		update_option( 'gps2photos_options', $defaults );
	}
}

/**
 * Validates color
 *
 * Checks if string representing a color format is correct.
 *
 * @since  1.0.0
 *
 * @see    gps2photos_options_validate()
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
		add_settings_error( 'gps2photos_options_group', 'invalid_color_number_error', $text . $error_message, 'error' );
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
 * @see   gps2photos_options_validate()
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
			add_settings_error( 'gps2photos_options_group', 'invalid_number_error', $error_message, 'error' );
			return $default_value;
		} elseif ( $string >= 24 && $string <= $max ) {
			return $text;
		} else {
			add_settings_error( 'gps2photos_options_group', 'invalid_number_error', $error_message, 'error' );
			return $default_value;
		}
	} elseif ( strpos( $text, '%' ) !== false ) {
		$string = str_replace( '%', '', $text );
		if ( ! is_numeric( $string ) ) {
			add_settings_error( 'gps2photos_options_group', 'invalid_number_error', $error_message, 'error' );
			return $default_value;
		} elseif ( $string > 0 && $string <= 100 ) {
			return $text;
		} else {
			add_settings_error( 'gps2photos_options_group', 'invalid_number_error', esc_html__( 'Please enter a number from 0% to 100%!', 'gps-2-photos' ), 'error' );
			return $default_value;
		}
	} else {
		add_settings_error( 'gps2photos_options_group', 'invalid_number_error', $error_message, 'error' );
		return $default_value;
	}
}

/**
 * Validates options ( administration )
 *
 * Runs when settings are registered - saved to MySQL Database
 *
 * @since  1.0.0
 *
 * @see    gps2photos_options_init() in administration.php
 * @param  array $opt Array of option values.
 * @return array
 */
function gps2photos_options_validate( $opt ) {
	// Gets variable values already saved on the server.
	$saved_options = gps2photos_convert_to_int( get_option( 'gps2photos_options' ) );

	// Invalid characters in API key for validation below.
	$special_chars = array( '?', '[', ']', '/', '\\', '=', '<', '>', ':', ';', ',', "'", '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', '%', '+', '’', '«', '»', '”', '“', chr( 0 ), '<', '>', '.', ',' );

	// Sample query - location to check if the Azure or the MapQuest key is working.
	$query = 'London';

	// Checks if API keys are already validated and saved to avoid overriding by default value.
	if ( $saved_options['geo_azure_auth_status'] === 1 ) {
		$opt['geo_azure_auth_status'] = 1;
	}

	// Sanitize API Key.
	$opt['geo_azure_key'] = isset( $opt['geo_azure_key'] ) ? sanitize_text_field( $opt['geo_azure_key'] ) : '';

	// Validates and sanitizes Microsoft Azure API Key.
	if ( strlen( $opt['geo_azure_key'] ) !== 0 ) {
		if ( $saved_options['geo_azure_key'] !== $opt['geo_azure_key'] ) {
			$check = 0;
			foreach ( $special_chars as $char ) {
				if ( strpos( $opt['geo_azure_key'], $char ) !== false ) {
					$check = 1;
				}
			}
			if ( $check === 1 ) {
				add_settings_error( 'gps2photos_options_group', 'invalid_API_key_error', esc_html__( 'Please enter a valid API key! Special characters are not allowed.', 'gps-2-photos' ), 'error' );
				$opt['geo_azure_key']         = '';
				$opt['geo_azure_auth_status'] = 0;
			} else {
				// Sends sample query to the Azure REST server to validate credentials.
				// URL of Azure Maps REST Services Locations API.
				$base_url = 'https://atlas.microsoft.com/search/address/json';
				// Construct the final Locations API URI.
				$url = $base_url . '?api-version=1.0&subscription-key=' . $opt['geo_azure_key'] . '&query=' . rawurlencode( $query );

				// Gets the response from the Search Address API and store it in a string.
				$response = wp_remote_get( $url );
				// Get the body of the response.
				$jsonfile = wp_remote_retrieve_body( $response );
				// Decode the json.
				if ( ! json_decode( $jsonfile, true ) ) {
					add_settings_error( 'gps2photos_options_group', 'Azure API key validation.', esc_html__( 'API key validation request failed when trying to decode Azure Maps server response!', 'gps-2-photos' ), 'error' );
					$opt['geo_azure_auth_status'] = 0;
				} else {
					$response = json_decode( $jsonfile, true );
					// Check if there is an error in the response.
					if ( isset( $response['error'] ) ) {
						$status_code = wp_remote_retrieve_response_code( $response );
						add_settings_error( 'gps2photos_options_group', 'Azure API key validation error.', esc_html__( 'Azure API key validation unsuccessful!', 'gps-2-photos' ) . ' ' . esc_html__( 'Server response:', 'gps-2-photos' ) . ' ' . esc_html__( 'Status Code:', 'gps-2-photos' ) . ' ' . $status_code, 'error' );
						$opt['geo_azure_auth_status'] = 0;
					} else {
						// Assume valid response if there's no error field.
						add_settings_error( 'gps2photos_options_group', 'Azure API key validation.', esc_html__( 'Azure Maps API key validation successful! You can start using GPS 2 Photos!', 'gps-2-photos' ), 'success' );
						$opt['geo_azure_auth_status'] = 1;
					}
				}
			}
		}
	} else {
		$opt['geo_azure_auth_status'] = 0;
	}

	// Validate Checkboxes (Boolean 0 or 1).
	$checkboxes = array(
		'gps_media_library',
		'always_override_gps',
		'exif_error_handler',
		'backup_existing_coordinates',
		'dashboard',
		'locate_me_button',
		'scalebar',
		'map_search_bar',
		'map_fullscreen',
		'logo',
		'restore_defaults',
	);

	foreach ( $checkboxes as $checkbox ) {
		if ( ! isset( $opt[ $checkbox ] ) ) {
			$opt[ $checkbox ] = 0;
		} else {
			$opt[ $checkbox ] = ( (int) $opt[ $checkbox ] === 1 ) ? 1 : 0;
		}
	}

	// Validate Map Style.
	$valid_map_styles = array( 'road', 'satellite', 'satellite_road_labels', 'grayscale_light', 'grayscale_dark', 'night', 'road_shaded_relief' );
	if ( isset( $opt['map'] ) && ! in_array( $opt['map'], $valid_map_styles, true ) ) {
		$opt['map'] = 'satellite_road_labels';
	}

	// Validate Pin Icon Types.
	$valid_pin_icons = array( 'marker', 'marker-thick', 'marker-arrow', 'marker-ball-pin', 'flag', 'flag-triangle', 'pin' );
	if ( isset( $opt['pin_icon_type'] ) && ! in_array( $opt['pin_icon_type'], $valid_pin_icons, true ) ) {
		$opt['pin_icon_type'] = 'marker';
	}
	if ( isset( $opt['search_pin_icon_type'] ) && ! in_array( $opt['search_pin_icon_type'], $valid_pin_icons, true ) ) {
		$opt['search_pin_icon_type'] = 'pin';
	}

	// Validates options.
	if ( strlen( $opt['zoom'] ) !== 0 && $saved_options['zoom'] !== $opt['zoom'] ) {
		if ( ! is_numeric( $opt['zoom'] ) || $opt['zoom'] > 24 || $opt['zoom'] < 1 ) {
			unset( $opt['zoom'] );
			add_settings_error( 'gps2photos_options_group', 'invalid_zoom_number_error', esc_html__( 'Please enter a valid number for Zoom Level in the range 1-24!', 'gps-2-photos' ), 'error' );
		}
	}

	if ( strlen( $opt['map_height'] ) !== 0 && $saved_options['map_height'] !== $opt['map_height'] ) {
		if ( is_numeric( $opt['map_height'] ) && $opt['map_height'] <= 4320 && $opt['map_height'] >= 24 ) {
			$opt['map_height'] = strval( $opt['map_height'] ) . 'px';
		} else {
			$opt['map_height'] = gps2photos_validate_auto_number( $opt['map_height'], 4320, '80%' );
		}
	}

	if ( strlen( $opt['map_width'] ) !== 0 && $saved_options['map_width'] !== $opt['map_width'] ) {
		if ( is_numeric( $opt['map_width'] ) && $opt['map_width'] <= 7680 && $opt['map_width'] >= 24 ) {
			$opt['map_width'] = strval( $opt['map_width'] ) . 'px';
		} else {
			$opt['map_width'] = gps2photos_validate_auto_number( $opt['map_width'], 7680, '400px' );
		}
	}

	// Validates colors.
	$color_keys = array(
		'pin_color',
		'pin_secondary_color',
		'search_pin_color',
	);
	foreach ( $color_keys as $key ) {
		if ( isset( $opt[ $key ] ) && strlen( $opt[ $key ] ) !== 0 ) {
			$opt[ $key ] = gps2photos_validate_color( $opt[ $key ] );
		}
	}

	// Options defaults.
	$defaults = gps2photos_defaults_array();
	// Parses it.
	$opt = wp_parse_args( $opt, $defaults );

	return $opt;
}
