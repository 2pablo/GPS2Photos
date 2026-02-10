<?php
/**
 * Functions for the GPS 2 Photo Add-on.
 *
 * @package    GPS 2 Photo Add-on
 * @subpackage Functions
 * @since      1.0.0
 *
 * @author     Pawel Block &lt;pb@pasart.net&gt;
 * @copyright  Copyright (c) 2025, Pawel Block
 * @link       http://geo2maps.pasart.net
 * @license    https://www.gnu.org/licenses/gpl-2.0.html
 */

// Security: Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'lib/autoload.php';

use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelExif;
use lsolesen\pel\PelTiff;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelTag;
use lsolesen\pel\PelEntryByte;
use lsolesen\pel\PelEntryAscii;
use lsolesen\pel\PelEntryRational;
use lsolesen\pel\PelEntryUserComment;

add_filter( 'attachment_fields_to_edit', 'gps2photos_add_attachment_fields_to_edit', 10, 2 );
/**
 * Add GPS fields to the media attachment edit screen.
 *
 * This function is hooked into the 'attachment_fields_to_edit' filter. It adds
 * custom fields for displaying and editing GPS coordinates for JPEG images in the
 * media library. It displays existing GPS data and provides a button to launch a
 * modal with a map to add or amend the coordinates.
 *
 * @since 1.0.0
 *
 * @param array   $form_fields An array of form fields to display for the attachment.
 * @param WP_Post $post        The attachment post object.
 * @return array  $form_fields The modified array of form fields with GPS data and controls.
 */
function gps2photos_add_attachment_fields_to_edit( $form_fields, $post ) {
	$options = gps2photos_convert_to_int( get_option( 'gps2photos_options' ) );

	$file_path = get_attached_file( $post->ID );

	if ( file_exists( $file_path ) ) {
		// Gets post mime type.
		$mime_type = get_post_mime_type( $post->ID );
		// Only for JPEG images.
		$gps_data_button_text = esc_html__( 'Add/Amend GPS Coordinates', 'gps-2-photos' );
		if ( $mime_type === 'image/jpeg' || $mime_type === 'image/webp' ) {
			$gps_lat_dec = '';
			$gps_lon_dec = '';

			$geo2_options = get_option( 'plugin_geo2_maps_plus_options' );
			if ( ( isset( $options['gps_media_library'] ) && $options['gps_media_library'] === 1 ) ||
				( ! is_plugin_active( 'ngg-geo2-maps-plus/plugin.php' ) &&
					$geo2_options && isset( $geo2_options['exif_viewer'] )
					&& $geo2_options['exif_viewer'] === 1 )
			) {
				$form_fields['header'] = array(
					'label' => '<b>' . esc_html__( 'GPS 2 Photos - GPS Coordinates', 'gps-2-photos' ) . '</b>',
					'input' => 'html',
					'html'  => '<div></div>',
				);

				$gps_data = gps2photos_coordinates( $file_path, $options );

				$gps_lat_dms = $gps_data ? $gps_data['latitude_format'] : '';
				$gps_lon_dms = $gps_data ? $gps_data['longitude_format'] : '';
				$gps_lat_dec = $gps_data ? $gps_data['latitude'] : '';
				$gps_lon_dec = $gps_data ? $gps_data['longitude'] : '';

				$form_fields['gps-latitude']  = array(
					'value' => $gps_lat_dms,
					'label' => esc_html__( 'GPS Lat.', 'gps-2-photos' ),
					'input' => 'html',
					'html'  => "<input type='text' class='text' readonly='readonly' id='attachments-" . $post->ID . "-gps-latitude' name='attachments[$post->ID][gps-latitude]' value='" . esc_attr( $gps_lat_dms ) . "' /><br />",
				);
				$form_fields['gps-longitude'] = array(
					'value' => $gps_lon_dms,
					'label' => esc_html__( 'GPS Long.', 'gps-2-photos' ),
					'input' => 'html',
					'html'  => "<input type='text' class='text' readonly='readonly' id='attachments-" . $post->ID . "-gps-longitude' name='attachments[$post->ID][gps-longitude]' value='" . esc_attr( $gps_lon_dms ) . "' /><br />",
				);

				if ( $gps_data ) {
					$gps_data_button_text = esc_html__( 'Amend GPS Coordinates', 'gps-2-photos' );
				} else {
					// If data wasn't loaded, use a generic button text.
					$gps_data_button_text = ( isset( $options['gps_media_library'] ) && $options['gps_media_library'] === 1 )
											? esc_html__( 'Add GPS Coordinates', 'gps-2-photos' )
											: esc_html__( 'Add/Amend GPS Coordinates', 'gps-2-photos' );
				}
			}

			$backup_exists = gps2photos_get_backup_coordinates( $file_path );

			$form_fields['gps2photos-open-map-btn'] = array(
				'label' => '<button type="button" id="attachments-' . $post->ID . '-gps2photos-open-map-btn" class="button gps2photos-open-map-btn" style="color: #d30000; border-color: #d30000;" ' .
							'data-image-id="' . esc_attr( $post->ID ) . '" ' .
							'data-file-path="' . esc_attr( $file_path ) . '" ' .
							'data-lat="' . esc_attr( $gps_lat_dec ) . '" ' .
							'data-lon="' . esc_attr( $gps_lon_dec ) . '"' .
							'data-backup-exists="' . ( $backup_exists ? 'true' : 'false' ) . '">' .
							esc_html( $gps_data_button_text ) .
							'</button>',
				'input' => 'html',
				'html'  => '<div></div>',
			);
		}
	}
	return $form_fields;
}

/**
 * Sends a JSON response back to Ajax request with the Azure Key.
 *
 * Verifies the nonce and retrieves the Azure Maps API key either from a
 * constant or from the plugin options, and sends it via AJAX.
 *
 * @since 1.0.0
 *
 * @see    gps2photos_options_init()
 * @return void Sends a JSON response with the Azure Maps API key.
 */
function gps2photos_get_azure_maps_api_key_callback() {
	// Verify nonce before doing anything.
	check_ajax_referer( 'gps2photos-get-api-key-nonce', 'nonce' );

	// Check for constant first.
	// Check for constant first.
	if ( defined( 'AZURE_MAPS_API_KEY' ) ) {
		$azure_api_key = constant( 'AZURE_MAPS_API_KEY' );
	} else {
		$azure_api_key = getenv( 'AZURE_MAPS_API_KEY' ) ? getenv( 'AZURE_MAPS_API_KEY' ) : '';
	}

	// If the constant is not defined, get it from the options array.
	if ( empty( $azure_api_key ) ) {
		$options       = get_option( 'gps2photos_options' );
		$azure_api_key = isset( $options['geo_azure_key'] ) ? $options['geo_azure_key'] : '';
	}

	// Send the API key to the frontend via AJAX.
	wp_send_json_success( $azure_api_key );
}

/**
 * Helper function to get the absolute file path for an image.
 *
 * Handles both standard WordPress Library attachments and NextGEN Gallery images.
 *
 * @since 1.0.0
 *
 * @see   gps2photos_get_coordinates_callback()
 * @param int    $image_id The ID of the attachment or NextGEN image (pid).
 * @param string $gallery_name The name of the gallery plugin ('nextgen', 'envira', 'foo', 'modula').
 * @param string $image_url     The URL of the image, used as a fallback for NextGEN.
 * @return string|null The absolute file path or null if not found.
 */
function gps2photos_get_image_path( $image_id, $gallery_name = false, $image_url = '' ) {
	$file_path = '';

	if ( ! $gallery_name ) {
		// For standard WordPress Media Library.
		return get_attached_file( $image_id );
	}

	// For gallery plugins.
	if ( in_array( $gallery_name, array( 'envira', 'foogallery', 'modula' ), true ) ) {
		// These galleries use standard WP attachments, so get_attached_file is most reliable.
		$file_path = get_attached_file( $image_id );
		if ( $file_path && file_exists( $file_path ) ) {
			return $file_path;
		}
	} elseif ( $gallery_name === 'nextgen' ) {
		// Prioritize getting the path from the NextGEN object via its ID (pid).
		if ( class_exists( 'nggdb' ) ) {
			$image = nggdb::find_image( $image_id );
			if ( $image && isset( $image->imagePath ) ) {
				return $image->imagePath;
			}
		}
	}

	// Fallback for all galleries: convert URL to path.
	if ( ! empty( $image_url ) ) {
		if ( strpos( $image_url, 'wp-content' ) !== false ) {
			$file_path = str_replace( content_url(), WP_CONTENT_DIR, $image_url );
			return file_exists( $file_path ) ? $file_path : null;
		} else {
			// Fallback for paths outside wp-content (e.g., custom NextGEN directory).
			$file_path = str_replace( site_url( '/' ), ABSPATH, $image_url );
			return file_exists( $file_path ) ? $file_path : null;
		}
	}
	return null;
}

/**
 * AJAX handler for fetching coordinates for a single image.
 *
 * @since 1.0.0
 *
 * @see   gps2photos_options_init()
 */
function gps2photos_get_coordinates_callback() {
	check_ajax_referer( 'gps2photos-get-gps-nonce', 'nonce' );

	$image_id     = isset( $_POST['image_id'] ) ? intval( wp_unslash( $_POST['image_id'] ) ) : 0;
	$gallery_name = isset( $_POST['gallery_name'] ) ? sanitize_text_field( wp_unslash( $_POST['gallery_name'] ) ) : '';
	$image_url    = isset( $_POST['imagePath'] ) ? esc_url_raw( wp_unslash( $_POST['imagePath'] ) ) : '';

	if ( ! $image_id ) {
		wp_send_json_error( 'Invalid image ID.' );
	}

	$file_path = gps2photos_get_image_path( $image_id, $gallery_name, $image_url );

	if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
		wp_send_json_error( 'File not found.' );
	}

	$options       = gps2photos_convert_to_int( get_option( 'gps2photos_options' ) );
	$gps_data      = gps2photos_coordinates( $file_path, $options );
	$backup_exists = gps2photos_get_backup_coordinates( $file_path ) !== false;

	if ( $gps_data ) {
		$gps_data['file_path']     = $file_path;
		$gps_data['backup_exists'] = $backup_exists;
		wp_send_json_success( $gps_data );
	} elseif ( is_bool( $gps_data ) ) { // It's false, meaning no GPS data
		// Send success with empty coords so the JS knows it was a valid check.
		wp_send_json_success(
			array(
				'latitude'      => '',
				'longitude'     => '',
				'file_path'     => $file_path,
				'backup_exists' => $backup_exists,
			)
		);
	} else {
		wp_send_json_error( 'Failed to get GPS data.' );
	}
}

/**
 * AJAX handler for saving coordinates.
 *
 * @since 1.0.0
 *
 * @see   gps2photos_options_init()
 */
function gps2photos_save_coordinates_callback() {
	check_ajax_referer( 'gps2photos-save-gps-nonce', 'nonce' );

	$latitude         = isset( $_POST['latitude'] ) ? sanitize_text_field( wp_unslash( $_POST['latitude'] ) ) : '';
	$longitude        = isset( $_POST['longitude'] ) ? sanitize_text_field( wp_unslash( $_POST['longitude'] ) ) : '';
	$override_setting = isset( $_POST['override_setting'] ) ? intval( wp_unslash( $_POST['override_setting'] ) ) : 0;
	$file_path        = isset( $_POST['file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['file_path'] ) ) : '';

	if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
		if ( strlen( $file_path ) > 0 ) {
			$file_path = 'empty.';
		}
		wp_send_json_error( 'Invalid file path for saving. Path: ' . $file_path );
	}

	$options = gps2photos_convert_to_int( get_option( 'gps2photos_options' ) );

	// Update the 'always_override_gps' option if the checkbox was present and checked.
	if ( isset( $_POST['override_setting'] ) ) {
		$options['always_override_gps'] = $override_setting;
		update_option( 'gps2photos_options', $options );
	}

	// Convert to float only if not empty, otherwise keep as is for the save function to handle.
	$lat_val = ( $latitude !== '' ) ? floatval( $latitude ) : '';
	$lon_val = ( $longitude !== '' ) ? floatval( $longitude ) : '';

	$original_gps  = gps2photos_coordinates( $file_path, $options );
	$backup_exists = gps2photos_get_backup_coordinates( $file_path ) !== false;

	if ( gps2photos_is_webp( $file_path ) ) {
		$result = gps2photos_save_gps_to_webp( $file_path, $lat_val, $lon_val, false, $original_gps, $backup_exists );
	} else {
		$result = gps2photos_save_gps_to_jpeg( $file_path, $lat_val, $lon_val, false, $original_gps, $backup_exists );
	}

	if ( $result ) {
		// If coordinates were removed, send a specific success message.
		$backup_opt = $options['backup_existing_coordinates'];

		// Determine the backup message based on whether a backup was created or already exists.
		if ( $backup_opt === 1 && $original_gps && ! $backup_exists ) {
			$backup_text = __( 'A backup of the original coordinates has been saved in the Exif field User Comment.', 'gps-2-photos' );
		} elseif ( $backup_opt === 1 && $original_gps && $backup_exists ) {
			$backup_text = __( 'A backup of the original coordinates already exists in the Exif field User Comment.', 'gps-2-photos' );
		} elseif ( $backup_opt === 1 && ! $original_gps && $backup_exists ) {
			$backup_text = __( 'Nothing to backup, but a backup of the original coordinates already exists in the Exif field User Comment.', 'gps-2-photos' );
		} elseif ( ! $backup_opt === 1 && ! $original_gps && $backup_exists ) {
			$backup_text = __( 'Backup was not requested but a backup of the original coordinates exists in the Exif field User Comment.', 'gps-2-photos' );
		} elseif ( ! $backup_opt === 1 && $original_gps && ! $backup_exists ) {
			$backup_text = __( 'Backup was not requested. Original coordinates cannot be recovered.', 'gps-2-photos' );
		} else {
			$backup_text = '';
		}

		if ( $lat_val === '' && $lon_val === '' ) {
			wp_send_json_success(
				array(
					'message'        => __( ' GPS data erased successfully.', 'gps-2-photos' ) . ' ' . $backup_text,
					'backup_created' => ( $backup_opt === 1 && $original_gps ),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'message'        => __( 'GPS data saved successfully.', 'gps-2-photos' ) . ' ' . $backup_text,
					'backup_created' => ( $backup_opt === 1 && $original_gps ),
				)
			);
		}
	} else {
		wp_send_json_error( 'Failed to save GPS data.' );
	}
}

/**
 * Extracts backup GPS coordinates from an image file's UserComment EXIF tag.
 *
 * @since 1.0.0
 *
 * @see   gps2photos_add_attachment_fields_to_edit()
 * @see   gps2photos_get_coordinates_callback()
 * @see   gps2photos_save_coordinates_callback()
 * @see   gps2photos_restore_from_backup_callback()
 * @param string $file_path Path to the image file.
 * @return array|bool An array with 'latitude' and 'longitude' or false if not found.
 */
function gps2photos_get_backup_coordinates( $file_path ) {
	if ( gps2photos_is_webp( $file_path ) ) {
		return gps2photos_get_webp_backup_coordinates( $file_path );
	}

	try {
		$jpeg = new PelJpeg( $file_path );
		$exif = $jpeg->getExif();

		if ( ! $exif ) {
			return false;
		}

		$tiff = $exif->getTiff();
		$ifd0 = $tiff->getIfd();

		if ( ! $ifd0 ) {
			return false;
		}

		$exif_ifd = $ifd0->getSubIfd( PelIfd::EXIF );
		if ( ! $exif_ifd ) {
			return false;
		}

		$user_comment_entry = $exif_ifd->getEntry( PelTag::USER_COMMENT );
		if ( ! $user_comment_entry ) {
			return false;
		}

		$comment = $user_comment_entry->getValue();
		$pattern = '/Original GPS coordinates: ([-]?\d+\.\d+), ([-]?\d+\.\d+) saved by GPS-2-PHOTOS/';

		if ( preg_match( $pattern, $comment, $matches ) ) {
			return array(
				'latitude'  => floatval( $matches[1] ),
				'longitude' => floatval( $matches[2] ),
			);
		}

		return false;
	} catch ( Exception $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logging production errors for debugging corrupted EXIF data.
			error_log( 'GPS-2-Photos: Error reading backup GPS data - ' . $e->getMessage() );
		}
		return false;
	}
}

/**
 * AJAX handler for restoring coordinates from backup in a single request.
 *
 * @since 1.0.0
 *
 * @see   gps2photos_options_init()
 */
function gps2photos_restore_from_backup_callback() {
	check_ajax_referer( 'gps2photos-restore-gps-nonce', 'nonce' );

	$file_path = isset( $_POST['file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['file_path'] ) ) : '';

	if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
		wp_send_json_error( 'Invalid file path for restore. Path: ' . $file_path );
	}

	$backup_coords = gps2photos_get_backup_coordinates( $file_path );
	if ( ! $backup_coords ) {
		wp_send_json_error( 'No backup GPS data found to restore.' );
	}

	// Signal to the save function that this is a restore operation.
	$restore = true;
	if ( gps2photos_is_webp( $file_path ) ) {
		$result = gps2photos_save_gps_to_webp( $file_path, $backup_coords['latitude'], $backup_coords['longitude'], $restore );
	} else {
		$result = gps2photos_save_gps_to_jpeg( $file_path, $backup_coords['latitude'], $backup_coords['longitude'], $restore );
	}

	if ( $result ) {
		wp_send_json_success(
			array(
				'coords'  => $backup_coords,
				'message' => __( 'Coordinates restored successfully.', 'gps-2-photos' ),
			)
		);
	} else {
		wp_send_json_error( 'Failed to restore GPS data.' );
	}
}

/**
 * Extracts GPS coordinates from an image file and converts them to a proper format.
 *
 * @since  1.0.0
 *
 * @see    gps2photos_add_attachment_fields_to_edit()
 * @see    gps2photos_get_coordinates_callback()
 * @see    gps2photos_save_coordinates_callback()
 * @param  string $picture_path A path to a picture.
 * @param  array  $options      Plugin options array.
 * @return string[]|bool  $geo Latitude and longitude coordinates
 */
function gps2photos_coordinates( $picture_path, $options ) {
	if ( gps2photos_is_webp( $picture_path ) ) {
		return gps2photos_coordinates_webp( $picture_path );
	}

	// Exif read error handler.
	if ( $options['exif_error_handler'] === 1 ) {
		// Sets error handler for potential errors in exif_read_data().
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Necessary to catch EXIF read errors from corrupted images
		set_error_handler(
			function ( $err_no, $err_str, $err_file, $err_line ) {
				// For AJAX requests (like the Media Library grid view), log errors to the server
				// to avoid long opening time. For all other page loads, show the
				// error in the browser console for easier debugging.
				$error = 'GPS 2 Photos (AJAX) - exif_read_data() error:\\nError no: ' . $err_no . '\\nError message: ' . $err_str . '\\nError file: ' . str_replace( '\\', '\\\\', $err_file ) . '\\nError line: ' . $err_line;
				if ( wp_doing_ajax() ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logging EXIF errors for user support
					error_log( $error );
				} else {
					// Shows errors in the browser console.
					echo "<script>console.warn('" . esc_textarea( $error ) . "' );</script>";
				}
				// Return true to signify the error has been handled and prevent PHP's default handler.
				return true;
			}
		);
	}

	// Gets Exif data.
	$exif = exif_read_data( $picture_path, 'GPS', false );

	if ( $options['exif_error_handler'] === 1 ) {
		// Restores error handler to stop errors from being displayed.
		restore_error_handler();
	}

	if ( $exif !== false ) {
		// Any coordinates available?
		if ( ! isset( $exif['GPSLongitude'][0] ) ) {
			return false;
		} else {
			// South or West?
			if ( $exif['GPSLatitudeRef'] === 'S' ) {
				$gps['latitude_string']    = -1;
				$gps['latitude_direction'] = 'S';
			} else {
				$gps['latitude_string']    = 1;
				$gps['latitude_direction'] = 'N';
			}
			if ( $exif['GPSLongitudeRef'] === 'W' ) {
				$gps['longitude_string']    = -1;
				$gps['longitude_direction'] = 'W';
			} else {
				$gps['longitude_string']    = 1;
				$gps['longitude_direction'] = 'E';
			}

			$gps['latitude_hour']    = $exif['GPSLatitude'][0];
			$gps['latitude_minute']  = $exif['GPSLatitude'][1];
			$gps['latitude_second']  = $exif['GPSLatitude'][2];
			$gps['longitude_hour']   = $exif['GPSLongitude'][0];
			$gps['longitude_minute'] = $exif['GPSLongitude'][1];
			$gps['longitude_second'] = $exif['GPSLongitude'][2];

			// Calculates.
			foreach ( $gps as $key => $value ) {
				$pos = strpos( $value, '/' );
				if ( $pos !== false ) {
					$temp        = explode( '/', $value );
					$gps[ $key ] = $temp[0] / $temp[1];
				}
			}

			$geo['latitude_format']  = $gps['latitude_direction'] . ' ' . $gps['latitude_hour'] . '&deg;' . $gps['latitude_minute'] . '&#x27;' . round( $gps['latitude_second'], 4 ) . '&#x22;';
			$geo['longitude_format'] = $gps['longitude_direction'] . ' ' . $gps['longitude_hour'] . '&deg;' . $gps['longitude_minute'] . '&#x27;' . round( $gps['longitude_second'], 4 ) . '&#x22;';

			$geo['latitude']  = $gps['latitude_string'] * ( $gps['latitude_hour'] + ( $gps['latitude_minute'] / 60 ) + ( $gps['latitude_second'] / 3600 ) );
			$geo['longitude'] = $gps['longitude_string'] * ( $gps['longitude_hour'] + ( $gps['longitude_minute'] / 60 ) + ( $gps['longitude_second'] / 3600 ) );
		}
	} else {
		return false;
	}
	return $geo;
}

/**
 * Convert a decimal degree into degrees, minutes, and seconds.
 *
 * @since  1.0.0
 *
 * @see   gps2photos_save_gps_to_jpeg()
 * @param int $degree the degree in the form 123.456. Must be in the interval [-180, 180].
 * @return array a triple with the degrees, minutes, and seconds. Each value is an array itself, suitable for passing to a PelEntryRational. If the degree is outside the allowed interval, null is returned instead.
 */
function gps2photos_convert_decimal_to_dms( $degree ) {
	if ( $degree > 180 || $degree < -180 ) {
		return array();
	}

	$degree   = abs( $degree );
	$seconds  = $degree * 3600;
	$degrees  = floor( $degree );
	$seconds -= $degrees * 3600;
	$minutes  = floor( $seconds / 60 );
	$seconds -= $minutes * 60;
	$seconds  = round( $seconds * 100, 0 );

	return array(
		array( $degrees, 1 ),
		array( $minutes, 1 ),
		array( $seconds, 100 ),
	);
}

// PHPCS: File operations should use WP_Filesystem methods instead of direct PHP filesystem calls like: file_put_contents().
global $wp_filesystem;
if ( ! $wp_filesystem ) {
	require_once ABSPATH . '/wp-admin/includes/file.php';
	WP_Filesystem();
}

/**
 * Add GPS information to a JPEG file.
 *
 * @since 1.0.0
 *
 * @see   gps2photos_save_coordinates_callback()
 * @see   gps2photos_get_backup_coordinates()
 * @param string $file_path Path to the JPEG file.
 * @param float  $latitude Latitude in decimal degrees.
 * @param float  $longitude Longitude in decimal degrees.
 * @param bool   $restore Whether this is a restore operation.
 * @param array  $original_gps Original GPS coordinates for backup (if any).
 * @param bool   $backup_exists If a backup already exists.
 * @return bool True on success, false on failure.
 */
function gps2photos_save_gps_to_jpeg( $file_path, $latitude, $longitude, $restore = false, $original_gps = array(), $backup_exists = false ) {
	try {
		$options    = gps2photos_convert_to_int( get_option( 'gps2photos_options' ) );
		$backup_opt = isset( $options['backup_existing_coordinates'] ) ? $options['backup_existing_coordinates'] : 0;
		$jpeg       = new PelJpeg( $file_path );
		$exif       = $jpeg->getExif();
		$tiff       = null;

		if ( $exif === null ) {
			$exif = new PelExif();
			$jpeg->setExif( $exif );
			$tiff = new PelTiff();
			$exif->setTiff( $tiff );
		} else {
			$tiff = $exif->getTiff();
		}

		$ifd0 = $tiff->getIfd();
		if ( $ifd0 === null ) {
			$ifd0 = new PelIfd( PelIfd::IFD0 );
			$tiff->setIfd( $ifd0 );
		}

		// --- Backup/Restore Logic ---
		if ( ( $backup_opt === 1 && $original_gps && ! $backup_exists ) || $restore ) {
			$exif_ifd = $ifd0->getSubIfd( PelIfd::EXIF );
			if ( ! $exif_ifd ) {
				$exif_ifd = new PelIfd( PelIfd::EXIF );
				$ifd0->addSubIfd( $exif_ifd );
			}

			$user_comment_entry = $exif_ifd->getEntry( PelTag::USER_COMMENT );
			$current_comment    = $user_comment_entry ? $user_comment_entry->getValue() : '';

			if ( $restore ) {
				// On restore, remove the backup string from the comment.
				$pattern     = '/Original GPS coordinates: ([-]?\d+\.\d+), ([-]?\d+\.\d+) saved by GPS-2-PHOTOS\n?/';
				$new_comment = preg_replace( $pattern, '', $current_comment );
				$new_comment = trim( (string) $new_comment ); // Clean up any trailing newlines.

				if ( empty( $new_comment ) && $user_comment_entry ) {
					$exif_ifd->addEntry( new PelEntryUserComment( '' ) ); // setValue() probably could work as well.
				} elseif ( $user_comment_entry ) {
					$exif_ifd->addEntry( new PelEntryUserComment( $new_comment ) );
				}
			} elseif ( $backup_opt === 1 && $original_gps && ! $backup_exists ) {
				// On save (not restore), add the backup string if it doesn't exist.
				$backup_signature = 'Original GPS coordinates:';
				$backup_string    = sprintf(
					'%s %f, %f saved by GPS-2-PHOTOS',
					$backup_signature,
					$original_gps['latitude'],
					$original_gps['longitude']
				);

				// Do not replace existing backup check.
				if ( strpos( $current_comment, $backup_signature ) === false ) {
					$new_comment = empty( $current_comment ) ? $backup_string : $current_comment . "\n" . $backup_string;

					if ( $user_comment_entry ) {
						$user_comment_entry->setValue( $new_comment );
					} else {
						$exif_ifd->addEntry( new PelEntryUserComment( $new_comment ) );
					}
				}
			}
		}

		// Reuse existing GPS IFD.
		$gps_ifd = $ifd0->getSubIfd( PelIfd::GPS );

		// If latitude and longitude are empty, it's a request to remove GPS data.
		if ( $latitude === '' && $longitude === '' ) {
			if ( $gps_ifd ) {

				// Unset just these entries if present.
				$targets = array(
					PelTag::GPS_VERSION_ID,
					PelTag::GPS_LATITUDE,
					PelTag::GPS_LATITUDE_REF,
					PelTag::GPS_LONGITUDE,
					PelTag::GPS_LONGITUDE_REF,
				);

				$changed = false;
				foreach ( $targets as $tag ) {
					// ArrayAccess API: isset()/unset() are mapped to offsetExists/offsetUnset.
					if ( isset( $gps_ifd[ $tag ] ) ) {
						unset( $gps_ifd[ $tag ] );
						$changed = true;
					}
				}

				unset( $gps_ifd );

				// To remove all entries from the GPS IFD - not tested.
				// foreach ( $gps_ifd_to_remove->getEntries() as $entry ) {
				// $gps_ifd_to_remove->offsetUnset( $entry->getTag() );
				// }.

				if ( ! $changed ) {
					return false; // nothing was present.
				}
				// The PEL library doesn't have a direct `removeSubIfd` method.
				// We can remove the pointer entry from the parent IFD:
				// unset( $ifd0[ PelTag::GPS_INFO_IFD_POINTER ] ).
				// or
				// $ifd0->offsetUnset( PelTag::GPS_INFO_IFD_POINTER ); - this did not work.
			}
			global $wp_filesystem;
			$wp_filesystem->put_contents( $file_path, $jpeg->getBytes(), FS_CHMOD_FILE );
			return true;
		}

		// Create a new GPS IFD if none.
		if ( $gps_ifd === null ) {
			$gps_ifd = new PelIfd( PelIfd::GPS );
			$ifd0->addSubIfd( $gps_ifd ); // adds pointer in IFD0 to this GPS sub-IFD.
			// Required tag when a GPS IFD is present (EXIF 2.2 most common).
			$gps_ifd->addEntry( new PelEntryByte( PelTag::GPS_VERSION_ID, 2, 3, 0, 0 ) ); // "2.2.0.0"
		}

		// Set Latitude.
		$lat_ref                           = ( $latitude < 0 ) ? 'S' : 'N';
		list( $hours, $minutes, $seconds ) = gps2photos_convert_decimal_to_dms( $latitude );
		// Update-or-add pattern to avoid affecting any other GPS entries.
		$ref = $gps_ifd->getEntry( PelTag::GPS_LATITUDE_REF );
		$ref ? $ref->setValue( $lat_ref ) : $gps_ifd->addEntry( new PelEntryAscii( PelTag::GPS_LATITUDE_REF, $lat_ref ) );
		$lat = $gps_ifd->getEntry( PelTag::GPS_LATITUDE );
		if ( $lat ) {
			$lat->setValue( $hours, $minutes, $seconds );
		} else {
			$gps_ifd->addEntry( new PelEntryRational( PelTag::GPS_LATITUDE, $hours, $minutes, $seconds ) );
		}

		// Set Longitude.
		$lon_ref                           = ( $longitude < 0 ) ? 'W' : 'E';
		list( $hours, $minutes, $seconds ) = gps2photos_convert_decimal_to_dms( $longitude );
		// Update-or-add pattern to avoid affecting any other GPS entries.
		$ref = $gps_ifd->getEntry( PelTag::GPS_LONGITUDE_REF );
		$ref ? $ref->setValue( $lon_ref ) : $gps_ifd->addEntry( new PelEntryAscii( PelTag::GPS_LONGITUDE_REF, $lon_ref ) );
		$lon = $gps_ifd->getEntry( PelTag::GPS_LONGITUDE );
		if ( $lon ) {
			$lon->setValue( $hours, $minutes, $seconds );
		} else {
			$gps_ifd->addEntry( new PelEntryRational( PelTag::GPS_LONGITUDE, $hours, $minutes, $seconds ) );
		}

		global $wp_filesystem;
		$wp_filesystem->put_contents( $file_path, $jpeg->getBytes(), FS_CHMOD_FILE );

		return true;
	} catch ( Exception $e ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logging EXIF errors for user support
		error_log( 'Error saving GPS data: ' . $e->getMessage() );
		return false;
	}
}

/**
 * Detect if file is WebP by checking file extension and magic bytes
 *
 * @since 1.0.0
 *
 * @see   gps2photos_save_coordinates_callback()
 * @see   gps2photos_get_backup_coordinates()
 * @see   gps2photos_restore_from_backup_callback()
 * @see   gps2photos_coordinates()
 * @param string $file_path Path to image file.
 * @return bool True if file is WebP.
 */
function gps2photos_is_webp( $file_path ) {
	if ( file_exists( $file_path ) && function_exists( 'mime_content_type' ) ) {
		return mime_content_type( $file_path ) === 'image/webp';
	}
	$ext = pathinfo( $file_path, PATHINFO_EXTENSION );
	return strtolower( (string) $ext ) === 'webp';
}


/**
 * Converts extracted GPS coordinates from a WebP file.
 *
 * @since 1.0.0
 *
 * @see   gps2photos_coordinates()
 * @param string $file_path Path to the WebP file.
 * @return array|bool GPS data array or false.
 */
function gps2photos_coordinates_webp( $file_path ) {
	$exif = gps2photos_get_webp_gps( $file_path );

	if ( $exif !== false && isset( $exif['GPSLongitude'][0] ) ) {
		// South or West?
		if ( $exif['GPSLatitudeRef'] === 'S' ) {
			$gps['latitude_string']    = -1;
			$gps['latitude_direction'] = 'S';
		} else {
			$gps['latitude_string']    = 1;
			$gps['latitude_direction'] = 'N';
		}
		if ( $exif['GPSLongitudeRef'] === 'W' ) {
			$gps['longitude_string']    = -1;
			$gps['longitude_direction'] = 'W';
		} else {
			$gps['longitude_string']    = 1;
			$gps['longitude_direction'] = 'E';
		}

		$gps['latitude_hour']    = $exif['GPSLatitude'][0];
		$gps['latitude_minute']  = $exif['GPSLatitude'][1];
		$gps['latitude_second']  = $exif['GPSLatitude'][2];
		$gps['longitude_hour']   = $exif['GPSLongitude'][0];
		$gps['longitude_minute'] = $exif['GPSLongitude'][1];
		$gps['longitude_second'] = $exif['GPSLongitude'][2];

		// Calculates.
		foreach ( $gps as $key => $value ) {
			$pos = strpos( $value, '/' );
			if ( $pos !== false ) {
				$temp        = explode( '/', $value );
				$gps[ $key ] = $temp[0] / $temp[1];
			}
		}

		$geo['latitude_format']  = $exif['GPSLatitudeRef'] . ' ' . $gps['latitude_hour'] . '&deg;' . $gps['latitude_minute'] . '&#x27;' . round( $gps['latitude_second'], 4 ) . '&#x22;';
		$geo['longitude_format'] = $exif['GPSLongitudeRef'] . ' ' . $gps['longitude_hour'] . '&deg;' . $gps['longitude_minute'] . '&#x27;' . round( $gps['longitude_second'], 4 ) . '&#x22;';

		if ( isset( $exif['GPSLatitudeDecimal'] ) && ! empty( $exif['GPSLatitudeDecimal'] ) ) {
			$geo['latitude'] = $exif['GPSLatitudeDecimal'];
		} else {
			$geo['latitude'] = $gps['latitude_string'] * ( $gps['latitude_hour'] + ( $gps['latitude_minute'] / 60 ) + ( $gps['latitude_second'] / 3600 ) );
		}
		if ( isset( $exif['GPSLongitudeDecimal'] ) && ! empty( $exif['GPSLongitudeDecimal'] ) ) {
			$geo['longitude'] = $exif['GPSLongitudeDecimal'];
		} else {
			$geo['longitude'] = $gps['longitude_string'] * ( $gps['longitude_hour'] + ( $gps['longitude_minute'] / 60 ) + ( $gps['longitude_second'] / 3600 ) );
		}

		return $geo;
	}
	return false;
}


/**
 * Reads metadata from a WebP file (Simplified for GPS).
 *
 * @since 1.0.0
 *
 * @see   gps2photos_coordinates_webp()
 * @param string $file_path The path to the WebP file.
 * @return array|false An array containing the metadata, or false on failure.
 */
function gps2photos_get_webp_gps( $file_path ) {
	if ( ! file_exists( $file_path ) ) {
		return false;
	}
	// phpcs:disable WordPress.WP.AlternativeFunctions
	$fp = fopen( $file_path, 'rb' );
	if ( ! $fp ) {
		return false;
	}

	$hdr = fread( $fp, 12 );
	if ( strlen( $hdr ) < 12 || substr( $hdr, 0, 4 ) !== 'RIFF' || substr( $hdr, 8, 4 ) !== 'WEBP' ) {
		fclose( $fp );
		return false;
	}

	$has_xmp  = false;
	$has_exif = false;

	// Skip header.
	$pos_after_header = 12;
	fseek( $fp, $pos_after_header, SEEK_SET );
	$pos_after_vp8x = 12;

	// Iterate chunks to find VP8X to determine if EXIF/XMP exist.
	while ( ! feof( $fp ) ) {
		$chunk_header = fread( $fp, 8 );
		if ( strlen( $chunk_header ) < 8 ) {
			break;
		}
		$chunk_id   = substr( $chunk_header, 0, 4 );
		$chunk_size = unpack( 'V', substr( $chunk_header, 4, 4 ) )[1];

		if ( $chunk_id === 'VP8X' ) {
			$payload = fread( $fp, 1 );
			if ( strlen( $payload ) >= 1 ) {
				$flags    = ord( $payload );
				$has_exif = (bool) ( $flags & 0x08 );
				$has_xmp  = (bool) ( $flags & 0x04 );
			}
			fseek( $fp, $chunk_size - 1 + ( $chunk_size % 2 ), SEEK_CUR );
			$pos_after_vp8x = ftell( $fp );
			break; // VP8X found.
		} else {
			fseek( $fp, $chunk_size + ( $chunk_size % 2 ), SEEK_CUR );
		}
	}

	$gps_data = false;

	// 1. Try XMP first.
	if ( $has_xmp ) {
		$xmp_data = gps2photos_webp_search_xmp( $fp );
		if ( $xmp_data ) {
			$gps_data = gps2photos_webp_search_xmp_gps( $xmp_data );
		}
	}

	// 2. Try EXIF if XMP failed.
	if ( ! $gps_data && $has_exif ) {
		fseek( $fp, $pos_after_vp8x, SEEK_SET );
		while ( ! feof( $fp ) ) {
			$chunk_header = fread( $fp, 8 );
			if ( strlen( $chunk_header ) < 8 ) {
				break;
			}
			$chunk_id   = substr( $chunk_header, 0, 4 );
			$chunk_size = unpack( 'V', substr( $chunk_header, 4, 4 ) )[1];
			$padding    = $chunk_size % 2;

			if ( $chunk_id === 'EXIF' ) {
				$blob     = fread( $fp, $chunk_size );
				$gps_data = gps2photos_parse_exif_blob( $blob );
				break;
			} else {
				fseek( $fp, $chunk_size + $padding, SEEK_CUR );
			}
		}
	}

	fclose( $fp );
	// phpcs:enable WordPress.WP.AlternativeFunctions
	return $gps_data;
}


/**
 * Searches for an XMP metadata block within a file stream.
 *
 * This function parses the RIFF structure of the WebP file to locate
 * the 'XMP ' chunk and extract its content.
 *
 * @since 1.0.0
 *
 * @see   gps2photos_get_webp_gps()
 * @param resource $fp A valid file pointer resource.
 * @return string|false The XMP data as a string if found, otherwise false.
 */
function gps2photos_webp_search_xmp( $fp ) {
	rewind( $fp );
	$header = fread( $fp, 12 );
	if ( strlen( $header ) < 12 ) {
		return false;
	}

	$riff = substr( $header, 0, 4 );
	$webp = substr( $header, 8, 4 );

	if ( $riff !== 'RIFF' || $webp !== 'WEBP' ) {
		return false;
	}

	$xmp_data = '';

	while ( ! feof( $fp ) ) {
		$chunk_header = fread( $fp, 8 );
		if ( strlen( $chunk_header ) < 8 ) {
			break;
		}

		$chunk_tag = substr( $chunk_header, 0, 4 );
		// WebP chunk size is little-endian 32-bit integer.
		$chunk_size = unpack( 'V', substr( $chunk_header, 4, 4 ) )[1];

		if ( $chunk_tag === 'XMP ' ) {
			// Found XMP chunk, read the full content based on chunk size.
			$read = 0;
			while ( $read < $chunk_size && ! feof( $fp ) ) {
				$buffer = fread( $fp, min( 8192, $chunk_size - $read ) );
				if ( $buffer === false ) {
					break;
				}
				$xmp_data .= $buffer;
				$read     += strlen( $buffer );
			}
			// Padding byte if size is odd (RIFF standard).
			if ( $chunk_size % 2 !== 0 ) {
				fseek( $fp, 1, SEEK_CUR );
			}
			continue;
		}

		// Skip chunk data.
		fseek( $fp, $chunk_size, SEEK_CUR );

		// Padding byte if size is odd (RIFF standard).
		if ( $chunk_size % 2 !== 0 ) {
			fseek( $fp, 1, SEEK_CUR );
		}
	}

	return ! empty( $xmp_data ) ? $xmp_data : false;
}


/**
 * Parses GPS coordinates from an XMP data block.
 *
 * @since 1.0.0
 *
 * @see   gps2photos_get_webp_gps()
 * @param string $xmp The XMP data block as a string.
 * @return array|false An array with GPS data compatible with other EXIF functions, or false if not found.
 */
function gps2photos_webp_search_xmp_gps( $xmp ) {
	$lat = null;
	$lon = null;
	// For debugging: write XMP to temp file.
	if ( preg_match( '/(?:<exif:GPSLatitude>([^<]+)<\/|exif:GPSLatitude="([^"]+)")/i', $xmp, $m ) ) {
		$lat = trim( ! empty( $m[1] ) ? $m[1] : $m[2] );
	}
	if ( preg_match( '/(?:<exif:GPSLongitude>([^<]+)<\/|exif:GPSLongitude="([^"]+)")/i', $xmp, $m ) ) {
		$lon = trim( ! empty( $m[1] ) ? $m[1] : $m[2] );
	}

	if ( ! $lat || ! $lon ) {
		return false;
	}

	list($lat_decimal, $lat_dms, $lat_ref) = gps2photos_parse_xmp_coord( $lat, 'lat' );
	list($lon_decimal, $lon_dms, $lon_ref) = gps2photos_parse_xmp_coord( $lon, 'lon' );

	return array(
		'GPSLatitudeDecimal'  => $lat_decimal,
		'GPSLongitudeDecimal' => $lon_decimal,
		'GPSLatitude'         => $lat_dms,
		'GPSLongitude'        => $lon_dms,
		'GPSLatitudeRef'      => $lat_ref,
		'GPSLongitudeRef'     => $lon_ref,
	);
}

/**
 * Convert a decimal degree into degrees, minutes, and seconds for webP files.
 *
 * @since  1.0.0
 *
 * @see   gps2photos_webp_search_xmp_gps()
 * @param int    $val the degree in the form 123.456. Must be in the interval [-180, 180].
 * @param string $type 'lat' for latitude or 'lon' for longitude, used to determine hemisphere reference.
 * @return array a triple with the degrees, minutes, and seconds. Each value is an array itself, suitable for passing to a PelEntryRational. If the degree is outside the allowed interval, null is returned instead.
 */
function gps2photos_parse_xmp_coord( $val, $type ) {

	// Detect hemisphere letter.
	$has_s = stripos( $val, 'S' ) !== false;
	$has_w = stripos( $val, 'W' ) !== false;

	if ( $type === 'lat' ) {
		$ref = $has_s ? 'S' : 'N';
	} else {
		$ref = $has_w ? 'W' : 'E';
	}

	// Remove hemisphere letters.
	$clean = str_replace( array( 'N', 'S', 'E', 'W' ), '', strtoupper( $val ) );
	$clean = trim( $clean );

	// Detect negative sign.
	$negative = false;
	if ( strpos( $clean, '-' ) === 0 ) {
		$negative = true;
		$clean    = ltrim( $clean, '-' );
	}

	// Locale decimal comma (only if single comma and no dot).
	if ( substr_count( $clean, ',' ) === 1 && preg_match( '/^\d+,\d+$/', $clean ) ) {
		$clean = str_replace( ',', '.', $clean );
	}

	// Split by comma to detect DMM or DMS.
	$parts = explode( ',', $clean );

	if ( count( $parts ) === 1 ) {
		// Decimal degrees.
		$decimal = floatval( $parts[0] );

	} elseif ( count( $parts ) === 2 ) {
		// DMM: deg, minutes.decimal.
		$deg     = floatval( $parts[0] );
		$min     = floatval( $parts[1] );
		$decimal = $deg + ( $min / 60 );

	} elseif ( count( $parts ) === 3 ) {
		// DMS: deg, min, sec.
		$deg     = floatval( $parts[0] );
		$min     = floatval( $parts[1] );
		$sec     = floatval( $parts[2] );
		$decimal = $deg + ( $min / 60 ) + ( $sec / 3600 );

	} else {
		// Garbage input.
		return array( 0, array( 0, 0, 0 ), $ref );
	}

	// Apply hemisphere rules.
	if ( $ref === 'S' || $ref === 'W' ) {
		$decimal = -abs( $decimal );
	} elseif ( $negative ) {
		// Negative sign but no hemisphere letter.
		$decimal = -abs( $decimal );
		$ref     = ( $type === 'lat' ) ? 'S' : 'W';
	}

	// Convert to DMS for EXIF.
	$dms = gps2photos_decimal_to_dms( abs( $decimal ) );

	return array( $decimal, $dms, $ref );
}

/**
 * Convert a decimal degree into degrees, minutes, and seconds for webP files.
 *
 * @since  1.0.0
 *
 * @see   gps2photos_webp_search_xmp_gps()
 * @param int $decimal the degree in the form 123.456. Must be in the interval [-180, 180].
 * @return array a triple with the degrees, minutes, and seconds. Each value is an array itself, suitable for passing to a PelEntryRational. If the degree is outside the allowed interval, null is returned instead.
 */
function gps2photos_decimal_to_dms( $decimal ) {
	$deg       = floor( $decimal );
	$min_float = ( $decimal - $deg ) * 60;
	$min       = floor( $min_float );
	$sec       = ( $min_float - $min ) * 60;

	return array(
		$deg,
		$min,
		$sec,
	);
}

/**
 * Parse EXIF/TIFF blob and return GPS section.
 *
 * @since 1.0.0
 *
 * @see   gps2photos_get_webp_gps()
 * @param string $blob The raw TIFF blob, starting with 'II' or 'MM'.
 * @return array|false An array of the requested metadata or false on failure.
 */
function gps2photos_parse_exif_blob( $blob ) {
	$len = strlen( $blob );
	if ( $len < 8 ) {
		return false;
	}
	// --- Endianness ---
	$byte_order = substr( $blob, 0, 2 );
	if ( $byte_order === 'II' ) {
		$is_le = true;
	} elseif ( $byte_order === 'MM' ) {
		$is_le = false;
	} else {
		return false;
	}
	$magic = substr( $blob, 2, 2 );
	if ( $magic !== "\x2A\x00" && $magic !== "\x00\x2A" ) {
		return false;
	}
	// --- Integer readers (endian-aware) ---
	$read_uint16 = function ( $off ) use ( $blob, $len, $is_le ) {
		if ( $off + 2 > $len ) {
			return false;
		}
		$bytes = substr( $blob, $off, 2 );
		return $is_le ? unpack( 'v', $bytes )[1] : unpack( 'n', $bytes )[1];
	};
	$read_uint32 = function ( $off ) use ( $blob, $len, $is_le ) {
		if ( $off + 4 > $len ) {
			return false;
		}
		$bytes = substr( $blob, $off, 4 );
		return $is_le ? unpack( 'V', $bytes )[1] : unpack( 'N', $bytes )[1];
	};
	// --- TIFF type sizes ---
	$type_sizes = array(
		1  => 1, // BYTE.
		2  => 1, // ASCII.
		3  => 2, // SHORT.
		4  => 4, // LONG.
		5  => 8, // RATIONAL.
		7  => 1, // UNDEFINED.
		9  => 4, // SLONG.
		10 => 8, // SRATIONAL.
	);
	// --- Parse a single IFD at offset ---
	$parse_ifd = function ( $ifd_off ) use ( $blob, $len, $read_uint16, $read_uint32 ) {
		$entries = array();
		$num     = $read_uint16( $ifd_off );
		if ( $num === false ) {
			return array(
				'entries' => $entries,
				'next'    => 0,
			);
		}
		$entry_base = $ifd_off + 2;
		for ( $i = 0; $i < $num; $i++ ) {
			$entry_off = $entry_base + ( $i * 12 );
			if ( $entry_off + 12 > $len ) {
				break;
			}
			$tag   = $read_uint16( $entry_off );
			$type  = $read_uint16( $entry_off + 2 );
			$count = $read_uint32( $entry_off + 4 );
			if ( $tag === false || $type === false || $count === false ) {
				continue;
			}
			$value_raw       = substr( $blob, $entry_off + 8, 4 );
			$value_offset    = $read_uint32( $entry_off + 8 );
			$entries[ $tag ] = array(
				'type'         => $type,
				'count'        => $count,
				'value_raw'    => $value_raw,
				'value_offset' => $value_offset,
			);
		}
		return array( 'entries' => $entries );
	};
	// --- Decode value(s) for a single IFD entry ---
	$get_ifd_value = function ( $entry ) use ( $blob, $len, $type_sizes, $is_le ) {
		if ( ! isset( $entry['type'], $entry['count'] ) ) {
			return false;
		}
		$type  = $entry['type'];
		$count = $entry['count'];
		$size  = isset( $type_sizes[ $type ] ) ? $type_sizes[ $type ] : 1;
		$total = $size * $count;
		if ( $total <= 4 ) {
			$raw = substr( $entry['value_raw'], 0, $total );
		} else {
			$off = $entry['value_offset'];
			if ( $off + $total > $len ) {
				return false;
			}
			$raw = substr( $blob, $off, $total );
		}
		$vals = array();
		$pos  = 0;
		for ( $i = 0; $i < $count; $i++ ) {
			if ( $type === 2 ) { // ASCII.
				return rtrim( $raw, "\0" );
			} elseif ( $type === 5 ) { // RATIONAL.
				$chunk_num = substr( $raw, $pos, 4 );
				$chunk_den = substr( $raw, $pos + 4, 4 );
				$num       = $is_le ? unpack( 'V', $chunk_num )[1] : unpack( 'N', $chunk_num )[1];
				$den       = $is_le ? unpack( 'V', $chunk_den )[1] : unpack( 'N', $chunk_den )[1];
				$vals[]    = array(
					'num' => $num,
					'den' => $den,
				);
				$pos      += 8;
			} else {
				// For GPS we mostly care about RATIONAL and ASCII (Refs).
				// Simplified fallback.
				$vals[] = substr( $raw, $pos, $size );
				$pos   += $size;
			}
		}
		return ( count( $vals ) === 1 ) ? $vals[0] : $vals;
	};

	// --- TIFF header: byte 4..7 = offset to IFD0 ---
	$ifd0_offset = $read_uint32( 4 );
	if ( $ifd0_offset === false || $ifd0_offset >= $len ) {
		return false;
	}
	$ifd0         = $parse_ifd( $ifd0_offset );
	$ifd0_entries = $ifd0['entries'];

	// GPSInfoIFDPointer (0x8825) is in IFD0.
	if ( isset( $ifd0_entries[0x8825] ) ) {
		$gps_ifd_offset = $ifd0_entries[0x8825]['value_offset'];
		if ( $gps_ifd_offset > 0 && $gps_ifd_offset < $len ) {
			$gps_ifd  = $parse_ifd( $gps_ifd_offset );
			$gps_tags = array();
			foreach ( $gps_ifd['entries'] as $tagnum => $entry ) {
				$gps_tags[ $tagnum ] = $get_ifd_value( $entry );
			}

			$rational_to_float = function ( $r ) {
				return ( isset( $r['num'], $r['den'] ) && $r['den'] != 0 ) ? $r['num'] / $r['den'] : 0.0;
			};

			if ( ! empty( $gps_tags ) ) {
				$gps_result = array();
				// 0x0001 = GPSLatitudeRef ('N'/'S')
				// 0x0002 = GPSLatitude (3 RATIONALs)
				// 0x0003 = GPSLongitudeRef ('E'/'W')
				// 0x0004 = GPSLongitude (3 RATIONALs)
				if ( isset( $gps_tags[0x0002] ) && is_array( $gps_tags[0x0002] ) ) {
					$gps_result['GPSLatitude']    = array_map( $rational_to_float, $gps_tags[0x0002] );
					$gps_result['GPSLatitudeRef'] = isset( $gps_tags[0x0001] ) ? trim( $gps_tags[0x0001] ) : 'N';
				}
				if ( isset( $gps_tags[0x0004] ) && is_array( $gps_tags[0x0004] ) ) {
					$gps_result['GPSLongitude']    = array_map( $rational_to_float, $gps_tags[0x0004] );
					$gps_result['GPSLongitudeRef'] = isset( $gps_tags[0x0003] ) ? trim( $gps_tags[0x0003] ) : 'E';
				}
				return ! empty( $gps_result ) ? $gps_result : false;
			}
		}
	}
	return false;
}


/**
 * Extracts backup GPS coordinates from a WebP file's XMP.
 *
 * @since 1.0.0
 *
 * @see   gps2photos_get_backup_coordinate()
 * @param string $file_path Path to the WebP file.
 * @return array|bool An array with 'latitude' and 'longitude' or false if not found.
 */
function gps2photos_get_webp_backup_coordinates( $file_path ) {
	// phpcs:disable WordPress.WP.AlternativeFunctions
	$fp = fopen( $file_path, 'rb' );
	if ( ! $fp ) {
		return false;
	}

	// Skip RIFF header.
	fseek( $fp, 12 );

	while ( ! feof( $fp ) ) {
		$chunk_header = fread( $fp, 8 );
		if ( strlen( $chunk_header ) < 8 ) {
			break;
		}
		$chunk_id   = substr( $chunk_header, 0, 4 );
		$chunk_size = unpack( 'V', substr( $chunk_header, 4, 4 ) )[1];

		if ( $chunk_id === 'XMP ' ) {
			$xmp_data = fread( $fp, $chunk_size );
			fclose( $fp );
			if ( preg_match( '/<gps2photos:OriginalGPS>([^<]+)<\/gps2photos:OriginalGPS>/', $xmp_data, $matches ) ) {
				$parts = explode( ',', $matches[1] );
				if ( count( $parts ) === 2 ) {
					return array(
						'latitude'  => floatval( $parts[0] ),
						'longitude' => floatval( $parts[1] ),
					);
				}
			}
			return false;
		}
		fseek( $fp, $chunk_size + ( $chunk_size % 2 ), SEEK_CUR );
	}
	fclose( $fp );
	// phpcs:enable WordPress.WP.AlternativeFunctions
	return false;
}

/**
 * Add or update GPS information in a WebP file using XMP.
 *
 * @since 1.0.0
 *
 * @see   gps2photos_save_coordinates_callback()
 * @see   gps2photos_restore_from_backup_callback()
 * @param string $file_path Path to the WebP file.
 * @param float  $latitude Latitude in decimal degrees.
 * @param float  $longitude Longitude in decimal degrees.
 * @param bool   $restore Whether this is a restore operation.
 * @param array  $original_gps Original GPS coordinates for backup (if any).
 * @param bool   $backup_exists If a backup already exists.
 * @return bool True on success, false on failure.
 */
function gps2photos_save_gps_to_webp( $file_path, $latitude, $longitude, $restore = false, $original_gps = array(), $backup_exists = false ) {
	global $wp_filesystem;
	$file_content = $wp_filesystem->get_contents( $file_path );
	if ( ! $file_content ) {
		return false;
	}

	$len = strlen( $file_content );
	if ( $len < 12 || substr( $file_content, 0, 4 ) !== 'RIFF' || substr( $file_content, 8, 4 ) !== 'WEBP' ) {
		return false;
	}

	$pos          = 12;
	$chunks       = array();
	$vp8x_chunk   = null;
	$xmp_chunk    = null;
	$image_width  = 0;
	$image_height = 0;

	// Parse chunks.
	while ( $pos < $len ) {
		$chunk_id   = substr( $file_content, $pos, 4 );
		$chunk_size = unpack( 'V', substr( $file_content, $pos + 4, 4 ) )[1];
		$chunk_data = substr( $file_content, $pos + 8, $chunk_size );

		$chunk = array(
			'id'   => $chunk_id,
			'data' => $chunk_data,
		);

		if ( $chunk_id === 'VP8X' ) {
			$vp8x_chunk = $chunk;
		} elseif ( $chunk_id === 'XMP ' ) {
			$xmp_chunk = $chunk;
		} elseif ( $chunk_id === 'VP8 ' ) {
			// Extract dims if VP8X missing.
			// Frame tag (3 bytes) + Start code (3 bytes) + Width (2 bytes) + Height (2 bytes).
			$w_raw        = unpack( 'v', substr( $chunk_data, 6, 2 ) )[1];
			$h_raw        = unpack( 'v', substr( $chunk_data, 8, 2 ) )[1];
			$image_width  = $w_raw & 0x3FFF;
			$image_height = $h_raw & 0x3FFF;
			$chunks[]     = $chunk;
		} elseif ( $chunk_id === 'VP8L' ) {
			// Extract dims if VP8X missing.
			// Signature 1 byte (0x2f).
			// 14 bits width, 14 bits height.
			$b1           = ord( $chunk_data[1] );
			$b2           = ord( $chunk_data[2] );
			$b3           = ord( $chunk_data[3] );
			$b4           = ord( $chunk_data[4] );
			$image_width  = ( $b1 | ( ( $b2 & 0x3F ) << 8 ) ) + 1;
			$image_height = ( ( ( $b2 & 0xC0 ) >> 6 ) | ( $b3 << 2 ) | ( ( $b4 & 0x0F ) << 10 ) ) + 1;
			$chunks[]     = $chunk;
		} else {
			$chunks[] = $chunk;
		}

		$pos += 8 + $chunk_size + ( $chunk_size % 2 );
	}

	// Prepare XMP.
	$xmp_content = $xmp_chunk ? $xmp_chunk['data'] : '';
	$new_xmp     = gps2photos_update_xmp( $xmp_content, $latitude, $longitude, $restore, $original_gps, $backup_exists );

	if ( ! $new_xmp && ! $xmp_chunk ) {
		// XMP removed (empty coords).
		$xmp_chunk = null;
	} elseif ( $new_xmp ) {
		$xmp_chunk = array(
			'id'   => 'XMP ',
			'data' => $new_xmp,
		);
	}

	// Handle VP8X.
	if ( ! $vp8x_chunk ) {
		// Create VP8X if we have XMP and it didn't exist.
		if ( $xmp_chunk ) {
			$flags = 0;
			if ( $xmp_chunk ) {
				$flags |= 0x04; // Set XMP bit.
			}
			// If we have other chunks like EXIF, ICCP, Alpha, we should set flags too, but for now assuming simple conversion.
			// Construct VP8X data: Flags(1) + Reserved(3) + Width(3) + Height(3).
			$vp8x_data  = chr( $flags );
			$vp8x_data .= chr( 0 ) . chr( 0 ) . chr( 0 );
			$w_minus    = $image_width - 1;
			$h_minus    = $image_height - 1;
			$vp8x_data .= chr( $w_minus & 0xFF ) . chr( ( $w_minus >> 8 ) & 0xFF ) . chr( ( $w_minus >> 16 ) & 0xFF );
			$vp8x_data .= chr( $h_minus & 0xFF ) . chr( ( $h_minus >> 8 ) & 0xFF ) . chr( ( $h_minus >> 16 ) & 0xFF );

			$vp8x_chunk = array(
				'id'   => 'VP8X',
				'data' => $vp8x_data,
			);
		}
	} else {
		// Update flags in existing VP8X.
		$flags = ord( $vp8x_chunk['data'][0] );
		if ( $xmp_chunk ) {
			$flags |= 0x04;
		} else {
			$flags &= ~0x04;
		}
		$vp8x_chunk['data'][0] = chr( $flags );
	}

	// Reconstruct file.
	$new_content = 'RIFF' . pack( 'V', 0 ) . 'WEBP'; // Size placeholder.

	if ( $vp8x_chunk ) {
		$new_content .= $vp8x_chunk['id'] . pack( 'V', strlen( $vp8x_chunk['data'] ) ) . $vp8x_chunk['data'];
	}

	// Order: ICCP, ANIM, ANMF, ALPH, VP8/VP8L, EXIF, XMP.
	// We kept order of others in $chunks, just need to place XMP at end.
	foreach ( $chunks as $chunk ) {
		$new_content .= $chunk['id'] . pack( 'V', strlen( $chunk['data'] ) ) . $chunk['data'];
		if ( strlen( $chunk['data'] ) % 2 !== 0 ) {
			$new_content .= chr( 0 ); // Padding.
		}
	}

	if ( $xmp_chunk ) {
		$new_content .= $xmp_chunk['id'] . pack( 'V', strlen( $xmp_chunk['data'] ) ) . $xmp_chunk['data'];
		if ( strlen( $xmp_chunk['data'] ) % 2 !== 0 ) {
			$new_content .= chr( 0 );
		}
	}

	// Update file size.
	$file_size   = strlen( $new_content ) - 8;
	$new_content = substr_replace( $new_content, pack( 'V', $file_size ), 4, 4 );

	return $wp_filesystem->put_contents( $file_path, $new_content, FS_CHMOD_FILE );
}

/**
 * Helper to update XMP XML string.
 *
 * @since 1.0.0
 *
 * @see   gps2photos_save_gps_to_webp()
 * @param string $xmp_content Existing XMP content.
 * @param float  $lat Latitude.
 * @param float  $lon Longitude.
 * @param bool   $restore Restore mode.
 * @param array  $original_gps Original GPS.
 * @param bool   $backup_exists Backup exists.
 * @return string|false New XMP content or false if empty.
 */
function gps2photos_update_xmp( $xmp_content, $lat, $lon, $restore, $original_gps, $backup_exists ) {
	$options    = gps2photos_convert_to_int( get_option( 'gps2photos_options' ) );
	$backup_opt = isset( $options['backup_existing_coordinates'] ) ? $options['backup_existing_coordinates'] : 0;

	// Basic XMP template if empty.
	if ( empty( $xmp_content ) && ( $lat !== '' && $lon !== '' ) ) {
		$xmp_content = '<x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="GPS 2 Photos">
            <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
                <rdf:Description rdf:about=""
                    xmlns:exif="http://ns.adobe.com/exif/1.0/"
                    xmlns:gps2photos="urn:gps2photos:1.0">
                </rdf:Description>
            </rdf:RDF>
        </x:xmpmeta>';
	}

	if ( empty( $xmp_content ) && $lat === '' && $lon === '' ) {
		return false;
	}

	$dom                     = new DOMDocument();
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput       = true;
	// Suppress warnings for malformed XMP.
	libxml_use_internal_errors( true );
	$dom->loadXML( $xmp_content, LIBXML_NOBLANKS );
	libxml_clear_errors();

	// Creates DOMXPath for querying and namespace handling.
	$xpath = new DOMXPath( $dom );
	$xpath->registerNamespace( 'rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#' );

	$descriptions = $xpath->query( '//rdf:Description' );
	if ( ! $descriptions || $descriptions->length === 0 ) {
		// Should not happen with template, but safety check.
		return false;
	}

	$desc = $descriptions->item( 0 );
	// Detect existing namespace URIs.
	if ( $desc ) {
		$exif_ns = $desc->lookupNamespaceURI( 'exif' );
		$gps2_ns = $desc->lookupNamespaceURI( 'gps2photos' );

		// Add missing namespaces.
		if ( ! $exif_ns ) {
			$exif_ns = 'http://ns.adobe.com/exif/1.0/';
			$desc->setAttribute( 'xmlns:exif', $exif_ns );
		}

		if ( ! $gps2_ns ) {
			$gps2_ns = 'urn:gps2photos:1.0';
			$desc->setAttribute( 'xmlns:gps2photos', $gps2_ns );
		}

		// Register common namespaces (even if file uses different ones).

		// Handle Backup.
		if ( ( $backup_opt === 1 && $original_gps && ! $backup_exists ) || $restore ) {
			$xpath->registerNamespace( 'gps2photos', $gps2_ns );
			$backup_query = $xpath->query( '//gps2photos:OriginalGPS' );
			$backup_node  = ( $backup_query !== false && $backup_query->length > 0 ) ? $backup_query->item( 0 ) : null;
			// $backup_node = $xpath->query( '//*[local-name()="OriginalGPS"]' )->item( 0 );

			if ( $restore ) {
				if ( $backup_node instanceof DOMNode && $backup_node->parentNode ) {
					$backup_node->parentNode->removeChild( $backup_node );
				}
			} elseif ( $backup_opt === 1 && $original_gps && ! $backup_exists ) {
				if ( ! $backup_node ) {
					$backup_val = $original_gps['latitude'] . ',' . $original_gps['longitude'];
					$node       = $dom->createElement(
						'gps2photos:OriginalGPS',
						$backup_val
					);
					if ( $node ) {
						$desc->appendChild( $node );
					}
				}
			}
		}

		// Handle GPS.
		$tags = array( 'exif:GPSLatitude', 'exif:GPSLongitude', 'exif:GPSLatitudeRef', 'exif:GPSLongitudeRef' );
		$xpath->registerNamespace( 'exif', $exif_ns );
		foreach ( $tags as $tag ) {
			$nodes = $xpath->query( '//' . $tag );
			if ( $nodes ) {
				foreach ( $nodes as $node ) {
					if ( $node instanceof DOMNode && $node->parentNode ) {
						$node->parentNode->removeChild( $node );
					}
				}
			}
			// Also remove attributes from all descriptions to prevent duplication.
			$attr = str_replace( 'exif:', '', $tag );
			foreach ( $descriptions as $description ) {
				if ( $description->hasAttribute( 'exif:' . $attr ) ) {
					$description->removeAttribute( 'exif:' . $attr );
				}
			}
		}

		if ( $lat !== '' && $lon !== '' ) {
			$lat_val = (float) $lat;
			$lon_val = (float) $lon;

			$lat_ref = ( $lat_val < 0 ) ? 'S' : 'N';
			$lon_ref = ( $lon_val < 0 ) ? 'W' : 'E';

			$lat_abs = abs( $lat_val );
			$lon_abs = abs( $lon_val );

			// XMP Standard format: "DDD,MM.mmk" (Degrees, Decimal Minutes).
			$lat_deg = floor( $lat_abs );
			$lat_min = ( $lat_abs - $lat_deg ) * 60;

			$lon_deg = floor( $lon_abs );
			$lon_min = ( $lon_abs - $lon_deg ) * 60;

			$lat_str = $lat_deg . ',' . number_format( $lat_min, 4, '.', '' ) . $lat_ref;
			$lon_str = $lon_deg . ',' . number_format( $lon_min, 4, '.', '' ) . $lon_ref;

			$lat_elem = $dom->createElement( 'exif:GPSLatitude', $lat_str );
			if ( $lat_elem ) {
				$desc->appendChild( $lat_elem );
			}
			$lon_elem = $dom->createElement( 'exif:GPSLongitude', $lon_str );
			if ( $lon_elem ) {
				$desc->appendChild( $lon_elem );
			}
			$lat_ref_elem = $dom->createElement( 'exif:GPSLatitudeRef', $lat_ref );
			if ( $lat_ref_elem ) {
				$desc->appendChild( $lat_ref_elem );
			}
			$lon_ref_elem = $dom->createElement( 'exif:GPSLongitudeRef', $lon_ref );
			if ( $lon_ref_elem ) {
				$desc->appendChild( $lon_ref_elem );
			}
		}
	}
	return $dom->saveXML();
}
