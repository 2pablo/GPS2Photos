<?php
/**
 * Functions for the GPS 2 Photo Add-on.
 *
 * @package    GPS 2 Photo Add-on
 * @subpackage Functions
 * @since      1.0.0
 * @author     Pawel Block &lt;pblock@op.pl&gt;
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
	$options = gps2photos_convert_to_int( get_option( 'plugin_gps2photos_options' ) );

	$file_path = get_attached_file( $post->ID );

	if ( file_exists( $file_path ) ) {
		// Gets post mime type.
		$mime_type = get_post_mime_type( $post->ID );
		// Only for JPEG images.
		$gps_data_button_text = esc_html__( 'Add/Amend GPS Coordinates', 'gps-2-photos' );
		if ( $mime_type === 'image/jpeg' ) {

			// Only read GPS data on page load if the option is enabled.
			$gps_data = null;
			if ( isset( $options['gps_media_library'] ) && $options['gps_media_library'] === 1 ) {
				$gps_data = gps2photos_coordinates( $file_path );
			}

			$geo2_options = get_option( 'plugin_geo2_maps_plus_options' );
			if ( ( isset( $options['gps_media_library'] ) && $options['gps_media_library'] === 1 ) ||
				( ! is_plugin_active( 'ngg-geo2-maps-plus/plugin.php' ) &&
					$geo2_options && isset( $geo2_options['exif_viewer'] )
					&& $geo2_options['exif_viewer'] === 1 )
			) {
				$form_fields['header'] = array(
					'label' => '<h2><b>' . esc_html__( 'GPS 2 Photos - GPS Coordinates', 'gps-2-photos' ) . '</b></h2>',
					'input' => 'html',
					'html'  => '<div></div>',
				);

				$gps_lat                     = $gps_data ? $gps_data['latitude_format'] : '';
				$gps_lon                     = $gps_data ? $gps_data['longitude_format'] : '';
				$form_fields['GPSLatitude']  = array(
					'value' => $gps_lat,
					'label' => esc_html__( 'GPS Lat.', 'gps-2-photos' ),
					'input' => 'html',
					'html'  => "<input type='text' class='text' readonly='readonly' name='attachments[$post->ID][gps_latitude]' value='" . esc_attr( $gps_lat ) . "' /><br />",
				);
				$form_fields['GPSLongitude'] = array(
					'value' => $gps_lon,
					'label' => esc_html__( 'GPS Long.', 'gps-2-photos' ),
					'input' => 'html',
					'html'  => "<input type='text' class='text' readonly='readonly' name='attachments[$post->ID][gps_longitude]' value='" . esc_attr( $gps_lon ) . "' /><br />",
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

			if ( isset( $options['gps_coordinates_preview'] ) && $options['gps_coordinates_preview'] == 1 ) {
				$form_fields['gps_coordinates_preview'] = array(
					'label' => __( 'GPS Coordinates Preview', 'gps-2-photos' ),
					'input' => 'html',
					'html'  => '<div id="gps2photos-map-preview-' . $post->ID . '" style="width: 100%; height: 300px;"></div>',
				);
			}

			$form_fields['add_gps_button'] = array(
				'label' => '<button type="button" class="button" style="color: #d30000; border-color: #d30000;" id="gps2photos-open-map-btn-' . $post->ID . '">' . $gps_data_button_text . '</button>',
				'input' => 'html',
				'html'  => '<div></div>',
			);

			// Add the modal HTML directly, but hidden.
			$modal_html = gps2photos_get_map_for_modal( $options, $post->ID, $file_path, $gps_data );

			$form_fields['gps_modal'] = array(
				'input' => 'html',
				'html'  => $modal_html,
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
		$options       = get_option( 'plugin_gps2photos_options' );
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
 * @since 1.0.1
 *
 * @param int    $image_id The ID of the attachment or NextGEN image (pid).
 * @param bool   $is_nextgen    Flag to indicate if it's a NextGEN image.
 * @param string $image_url     The URL of the image, used as a fallback for NextGEN.
 * @return string|null The absolute file path or null if not found.
 */
function gps2photos_get_image_path( $image_id, $is_nextgen = false, $image_url = '' ) {
	$file_path = '';

	if ( $is_nextgen ) {
		// Prioritize getting the path from the NextGEN object via its ID (pid).
		if ( function_exists( 'nggdb' ) ) {
			$image = nggdb::find_image( $image_id );
			if ( $image && isset( $image->abspath ) ) {
				return $image->abspath;
			}
		}
		// Fallback to converting URL to path if the above fails or nggdb is not available.
		if ( ! empty( $image_url ) ) {
			$upload_dir     = wp_upload_dir();
			$upload_baseurl = trailingslashit( $upload_dir['baseurl'] );
			$upload_basedir = trailingslashit( $upload_dir['basedir'] );

			if ( strpos( $image_url, $upload_baseurl ) === 0 ) {
				$file_path = str_replace( $upload_baseurl, $upload_basedir, $image_url );
			} else {
				// Generic fallback for non-standard URL structures.
				$site_url  = trailingslashit( site_url() );
				$file_path = str_replace( $site_url, ABSPATH, $image_url );
			}
			return file_exists( $file_path ) ? $file_path : null;
		}
	} else {
		// For standard WordPress Media Library.
		return get_attached_file( $image_id );
	}

	return null;
}

/**
 * AJAX handler for fetching coordinates for a single image.
 *
 * @since 1.0.0
 */
function gps2photos_get_coordinates_callback() {
	check_ajax_referer( 'gps2photos-get-gps-nonce', 'nonce' );

	$image_id   = isset( $_POST['image_id'] ) ? intval( $_POST['image_id'] ) : 0;
	$is_nextgen = isset( $_POST['is_nextgen'] ) && $_POST['is_nextgen'] === '1';
	$image_url  = isset( $_POST['imagePath'] ) ? esc_url_raw( $_POST['imagePath'] ) : '';

	if ( ! $image_id ) {
		wp_send_json_error( 'Invalid image ID.' );
	}

	$file_path = gps2photos_get_image_path( $image_id, $is_nextgen, $image_url );

	if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
		wp_send_json_error( 'File not found.' );
	}

	$gps_data      = gps2photos_coordinates( $file_path );
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
 */
function gps2photos_save_coordinates_callback() {
	check_ajax_referer( 'gps2photos-save-gps-nonce', 'nonce' );

	$latitude         = isset( $_POST['latitude'] ) ? sanitize_text_field( $_POST['latitude'] ) : '';
	$longitude        = isset( $_POST['longitude'] ) ? sanitize_text_field( $_POST['longitude'] ) : '';
	$override_setting = isset( $_POST['override_setting'] ) ? intval( $_POST['override_setting'] ) : 0;
	$file_path        = isset( $_POST['file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['file_path'] ) ) : '';

	if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
		wp_send_json_error( 'Invalid file path for saving. Path: ' . $file_path );
	}

	// Update the 'always_override_gps' option if the checkbox was present and checked.
	if ( isset( $_POST['override_setting'] ) ) {
		$options                        = get_option( 'plugin_gps2photos_options' );
		$options['always_override_gps'] = $override_setting;
		update_option( 'plugin_gps2photos_options', $options );
	}

	// Convert to float only if not empty, otherwise keep as is for the save function to handle.
	$lat_val = ( $latitude !== '' ) ? floatval( $latitude ) : '';
	$lon_val = ( $longitude !== '' ) ? floatval( $longitude ) : '';

	$original_gps  = gps2photos_coordinates( $file_path );
	$backup_exists = gps2photos_get_backup_coordinates( $file_path ) !== false;

	$result = gps2photos_save_gps_to_jpeg( $file_path, $lat_val, $lon_val, false, $original_gps );

	if ( $result ) {
		// If coordinates were removed, send a specific success message.
		$options    = gps2photos_convert_to_int( get_option( 'plugin_gps2photos_options' ) );
		$backup_opt = $options['backup_existing_coordinates'];

		// Determine the backup message based on whether a backup was created or already exists.
		if ( $backup_opt === 1 && $original_gps && ! $backup_exists ) {
			$backup_text = __( 'A backup of the original coordinates has been saved in the Exif field User Comment.', 'gps-2-photos' );
		} elseif ( $backup_opt === 1 && $original_gps && $backup_exists ) {
			$backup_text = __( 'A backup of the original coordinates already exists in the Exif field User Comment.', 'gps-2-photos' );
		} elseif ( $backup_opt === 1 && ! $original_gps && $backup_exists ) {
			$backup_text = __( 'Nothing to backup but a backup of the original coordinates already exists in the Exif field User Comment.', 'gps-2-photos' );
		} elseif ( ! $backup_opt === 1 && ! $original_gps && $backup_exists ) {
			$backup_text = __( 'Backup was not requested but a backup of the original coordinates exists in the Exif field User Comment.', 'gps-2-photos' );
		} elseif ( ! $backup_opt === 1 && $original_gps && ! $backup_exists ) {
			$backup_text = __( 'Backup was not requested. Original coordinates cannot be recovered', 'gps-2-photos' );
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
 * @param string $file_path Path to the image file.
 * @return array|bool An array with 'latitude' and 'longitude' or false if not found.
 */
function gps2photos_get_backup_coordinates( $file_path ) {
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
		error_log( 'Error reading backup GPS data: ' . $e->getMessage() );
		return false;
	}
}

/**
 * AJAX handler for restoring coordinates from backup in a single request.
 *
 * @since 1.0.0
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
	$result  = gps2photos_save_gps_to_jpeg( $file_path, $backup_coords['latitude'], $backup_coords['longitude'], $restore );

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
 * @param  string $picture_path A path to a picture.
 * @return string[]|bool  $geo Latitude and longitude coordinates
 */
function gps2photos_coordinates( $picture_path ) {
	// Sets error handler for potential errors in exif_read_data().
	set_error_handler(
		function ( $err_no, $err_str, $err_file, $err_line ) {
			$error = 'Error no: ' . $err_no . '\\nError message: ' . $err_str . '\\nError file: ' . str_replace( '\\', '\\\\', $err_file ) . '\\nError line: ' . $err_line;
			// Shows errors in the browser console.
			echo esc_html( "<script>console.log('exif_read_data() error: \\n" . $error . "') );</script>" );
		}
	);

	// Gets Exif data.
	$exif = exif_read_data( $picture_path, 'GPS', false );

	// Restores error handler to stop errors from being displayed.
	restore_error_handler();

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
 * @param string $file_path Path to the JPEG file.
 * @param float  $latitude Latitude in decimal degrees.
 * @param float  $longitude Longitude in decimal degrees.
 * @param bool   $restore Whether this is a restore operation.
 * @param array  $original_gps Original GPS coordinates for backup (if any).
 * @return bool True on success, false on failure.
 */
function gps2photos_save_gps_to_jpeg( $file_path, $latitude, $longitude, $restore = false, $original_gps = array() ) {
	try {
		$options    = gps2photos_convert_to_int( get_option( 'plugin_gps2photos_options' ) );
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
		if ( ( $backup_opt === 1 && $original_gps ) || $restore ) {
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
					$exif_ifd->addEntry( new PelEntryUserComment( '' ) ); // setValue().
				} elseif ( $user_comment_entry ) {
					$exif_ifd->addEntry( new PelEntryUserComment( $new_comment ) ); // setValue().
				}
			} elseif ( $backup_opt === 1 && $original_gps ) {
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

		// Reuse existing GPS IFD or create a new one.
		$gps_ifd = $ifd0->getSubIfd( PelIfd::GPS );
		if ( $gps_ifd === null ) {
			$gps_ifd = new PelIfd( PelIfd::GPS );
			$ifd0->addSubIfd( $gps_ifd ); // adds pointer in IFD0 to this GPS sub-IFD.
			// Required tag when a GPS IFD is present (EXIF 2.2 most common).
			$gps_ifd->addEntry( new PelEntryByte( PelTag::GPS_VERSION_ID, 2, 2, 0, 0 ) ); // "2.2.0.0"
		}

		// If latitude and longitude are empty, it's a request to remove GPS data.
		if ( $latitude === '' && $longitude === '' ) {
			if ( $gps_ifd ) {

				// Unset just these four entries if present.
				$targets = array(
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
		error_log( 'Error saving GPS data: ' . $e->getMessage() );
		return false;
	}
}
