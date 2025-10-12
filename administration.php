<?php
/**
 * Administration functions for the GPS 2 Photo Add-on.
 *
 * @package    GPS 2 Photo Add-on
 * @subpackage Administration
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

add_action( 'admin_init', 'gps2photos_options_init' );
/**
 * Init plugin options.
 *
 * @since 1.0.0
 */
function gps2photos_options_init() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	register_setting(
		'plugin_gps2photos_options',
		'plugin_gps2photos_options',
		array(
			'sanitize_callback' => 'gps2photos_options_validate',
		)
	);

	// Migrate Azure Maps Key from NextGEN Gallery Geo or Geo2Maps Plus if available.
	$gps2photos_options = get_option( 'plugin_gps2photos_options' );

	if ( ! isset( $gps2photos_options['geo_azure_key'] ) || empty( $gps2photos_options['geo_azure_key'] ) ) {
		$geo2_options = get_option( 'plugin_geo2_maps_options' );
		if ( isset( $geo2_options['geo_azure_key'] ) ) {
			$options['geo_azure_key'] = $geo2_options['geo_azure_key'];
			update_option( 'plugin_gps2photos_options', $options );
		}
	} elseif ( is_plugin_active( 'ngg-geo2-maps-plus/plugin.php' ) ) {
		$geo2_options = get_option( 'plugin_geo2_maps_plus_options' );
		if ( isset( $geo2_options['geo_azure_key'] ) ) {
			$options['geo_azure_key'] = $geo2_options['geo_azure_key'];
			update_option( 'plugin_gps2photos_options', $options );
		}
	}
	// Hook for authenticated users to access AJAX calls.
	add_action( 'wp_ajax_gps2photos_get_azure_maps_api_key', 'gps2photos_get_azure_maps_api_key_callback' );
	add_action( 'wp_ajax_gps2photos_save_coordinates', 'gps2photos_save_coordinates_callback' );
	add_action( 'wp_ajax_gps2photos_restore_from_backup', 'gps2photos_restore_from_backup_callback' );
	add_action( 'wp_ajax_gps2photos_get_coordinates', 'gps2photos_get_coordinates_callback' );
}

//add_action( 'admin_footer-nextgen-gallery5_page_nggallery-manage-gallery', 'gps2photos_add_hidden_modal' );

/**
 * Outputs the hidden modal HTML for the GPS 2 Photos plugin in the admin footer.
 *
 * @since 1.0.0
 */
function gps2photos_add_hidden_modal() {
	// Add the modal HTML directly, but hidden.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gps2photos_get_map_for_modal is escaping its output.
	echo gps2photos_get_map_for_modal();
}

add_action( 'admin_enqueue_scripts', 'gps2photos_plugin_admin_scripts' );
/**
 * Adds scripts required by admin settings page.
 * Enqueue scripts for Azure Maps.
 * Plugin translations
 * Ajax for Azure API Key
 *
 * @since 1.0.0
 *
 * @param string $hook The current admin page hook.
 */
function gps2photos_plugin_admin_scripts( $hook ) {
	// Adds I18n.
	load_plugin_textdomain( 'gps-2-photos', false, basename( __DIR__ ) . '/languages' );

	// Get current screen object to get post type.
	// If not, we're not on an Envira or Foo Gallery page.
	// Optional method: 'envira' === get_post_type( $_GET['post'] ).
	if ( function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
	}
	// Get post ID from query.
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	// Scripts for Media Library, NextGEN Gallery, and our own page.
	if ( in_array( $hook, array( 'upload.php', 'post.php', 'nextgen-gallery5_page_nggallery-manage-gallery' ), true ) ||
		( $hook === 'post.php' && is_object( $screen ) && $screen->post_type === 'envira' && $post_id !== 0 ) ||
		( $hook === 'post.php' && is_object( $screen ) && $screen->post_type === 'foogallery' && $post_id !== 0 ) ||
		( $hook === 'post.php' && is_object( $screen ) && $screen->post_type === 'modula-gallery' && $post_id !== 0 ) ) {

		// --- Register and Enqueue Modal Scripts ---
		if ( ! wp_script_is( 'gps2photos-modal', 'registered' ) ) {
			wp_register_script( 'gps2photos-modal', GPS_2_PHOTOS_DIR_URL . '/js/gps2photos-modal.js', array( 'jquery' ), '1.0.0', true );
			wp_localize_script(
				'gps2photos-modal',
				'gps2photos_ajax',
				array(
					'ajaxurl'           => admin_url( 'admin-ajax.php' ),
					'get_gps_nonce'     => wp_create_nonce( 'gps2photos-get-gps-nonce' ),
					'save_gps_nonce'    => wp_create_nonce( 'gps2photos-save-gps-nonce' ),
					'restore_gps_nonce' => wp_create_nonce( 'gps2photos-restore-gps-nonce' ),
					'l10n'              => array(
						'invalid_latitude'     => esc_html__( 'Invalid Latitude. Please enter a value between -90 and 90.', 'gps-2-photos' ),
						'invalid_longitude'    => esc_html__( 'Invalid Longitude. Please enter a value between -180 and 180.', 'gps-2-photos' ),
						'both_coords_required' => esc_html__( 'Both Latitude and Longitude must be provided, or both must be empty to erase coordinates.', 'gps-2-photos' ),
						'coords_not_changed'   => esc_html__( 'GPS coordinates were not changed.', 'gps-2-photos' ),
						'coords_already_empty' => esc_html__( 'GPS coordinates are already empty.', 'gps-2-photos' ),
						'confirm_override'     => esc_html__( 'This image already has GPS coordinates. Are you sure you want to override them?', 'gps-2-photos' ),
						'saving'               => esc_html__( 'Saving...', 'gps-2-photos' ),
						'error_prefix'         => esc_html__( 'Error:', 'gps-2-photos' ),
						'error_saving'         => esc_html__( 'An error occurred while saving.', 'gps-2-photos' ),
						'confirm_erase'        => esc_html__( 'Are you sure you want to erase the GPS coordinates?', 'gps-2-photos' ) . "\n" .
													esc_html__( 'This will not erase backup coordinates saved in the Exif field User Comment.', 'gps-2-photos' ),
						'confirm_restore'      => esc_html__( 'Are you sure you want to restore the original coordinates?', 'gps-2-photos' ) . "\n" .
													esc_html__( 'This will overwrite any current GPS data on the image.', 'gps-2-photos' ),
						'restoring'            => esc_html__( 'Restoring...', 'gps-2-photos' ),
						'error_restoring'      => esc_html__( 'An unknown error occurred during restore.', 'gps-2-photos' ),
					),
				)
			);
		}
		wp_enqueue_script( 'gps2photos-modal' );

		// Register and Enqueue Ajax for Azure Maps API Key.
		if ( ! wp_script_is( 'gps2photos-map-api-key', 'registered' ) ) {
			wp_register_script( 'gps2photos-map-api-key', GPS_2_PHOTOS_DIR_URL . '/js/gps2photos-ajax-map-api-key.js', array( 'jquery', 'gps2photos-modal' ), '1.0.0', true );
			wp_localize_script(
				'gps2photos-map-api-key',
				'gps2photos_api_key_ajax',
				array(
					'ajaxurl'           => admin_url( 'admin-ajax.php' ),
					'get_api_key_nonce' => wp_create_nonce( 'gps2photos-get-api-key-nonce' ),
				)
			);
		}
		wp_enqueue_script( 'gps2photos-map-api-key' );

		// Enqueue modal css.
		wp_enqueue_style( 'gps2photos-modal-css', GPS_2_PHOTOS_DIR_URL . '/css/gps2photos-modal.css', array(), '1.0.0' );

		// --- Register and Enqueue Azure Maps Scripts (with conflict check) ---
		if ( ! wp_style_is( 'azure-maps-css', 'enqueued' ) ) {
			wp_enqueue_style( 'azure-maps-css', 'https://atlas.microsoft.com/sdk/javascript/mapcontrol/3/atlas.min.css', array(), null, 'all' );
		}
		if ( ! wp_script_is( 'azure-maps-js', 'enqueued' ) ) {
			wp_enqueue_script( 'azure-maps-js', 'https://atlas.microsoft.com/sdk/javascript/mapcontrol/3/atlas.min.js', array(), null, false );
		}
		if ( ! wp_script_is( 'azure-maps-geolocation-js', 'enqueued' ) ) {
			wp_enqueue_script( 'azure-maps-geolocation-js', GPS_2_PHOTOS_DIR_URL . '/js/geolocation-module/azure-maps-geolocation-control.min.js', array( 'azure-maps-js' ), '1.0.0', false );
		}
		if ( ! wp_script_is( 'azure-maps-fullscreen-js', 'enqueued' ) ) {
			wp_enqueue_script( 'azure-maps-fullscreen-js', GPS_2_PHOTOS_DIR_URL . '/js/fullscreen-module/azure-maps-fullscreen-control.min.js', array( 'azure-maps-js' ), '1.0.0', false );
		}

		add_action( 'admin_footer', 'gps2photos_add_hidden_modal' );

    	wp_add_inline_script( 'gps2photos_azure_map_script', 'gps2photos_azure_map_script' );
	}

	// Enqueue for Envira Gallery edit screen.
	if ( function_exists( 'get_current_screen' ) && $hook === 'post.php' && $post_id !== 0 ) {
		// Data to pass to our script.
		$localized_data = array(
			'l10n' => array(
				'gps'           => esc_html__( 'GPS Coordinates', 'gps-2-photos' ),
				'add_amend_gps' => esc_html__( 'Add/Amend GPS', 'gps-2-photos' ),
			),
		);
		if ( is_object( $screen ) && $screen->post_type === 'envira' ) {
			wp_register_script( 'gps2photos-envira', GPS_2_PHOTOS_DIR_URL . '/js/gps2photos-envira.js', array( 'jquery', 'gps2photos-modal' ), '1.0.0', true );

			// Pass the data to the script.
			wp_enqueue_script( 'gps2photos-envira' );
			// Pass the data to the script.
			wp_localize_script( 'gps2photos-envira', 'gps2photos_envira', $localized_data );
		}

		// Enqueue for Foo Gallery edit screen.
		if ( is_object( $screen ) && $screen->post_type === 'foogallery' ) {
			wp_register_script( 'gps2photos-foo', GPS_2_PHOTOS_DIR_URL . '/js/gps2photos-foo.js', array( 'jquery', 'gps2photos-modal' ), '1.0.0', true );

			// Pass the data to the script.
			wp_localize_script( 'gps2photos-foo', 'gps2photos_foo', $localized_data );

			wp_enqueue_script( 'gps2photos-foo' );
		}

		// Enqueue for Modula Gallery edit screen.
		if ( is_object( $screen ) && $screen->post_type === 'modula-gallery' ) {
			wp_register_script( 'gps2photos-modula', GPS_2_PHOTOS_DIR_URL . '/js/gps2photos-modula.js', array( 'jquery', 'gps2photos-modal' ), '1.0.0', true );

			// Pass the data to the script.
			wp_localize_script( 'gps2photos-modula', 'gps2photos_modula', $localized_data );

			wp_enqueue_script( 'gps2photos-modula' );
		}
	}

	// Add the single hidden modal to the footer on all relevant pages.
	// The check inside the function prevents it from being added multiple times.
	// if ( ! has_action( 'admin_footer', 'gps2photos_add_hidden_modal' ) ) {
	// 	add_action( 'admin_footer', 'gps2photos_add_hidden_modal' );
	// }


	// Only load the settings page scripts on our plugin's admin page.
	if ( $hook === 'toplevel_page_gps-2-photos' ) {
		// Enqueue admin styles css.
		wp_enqueue_style( 'gps2photos_admin_styles', GPS_2_PHOTOS_DIR_URL . '/css/admin-style.css', array(), '1.0.0', 'all' );
		// Enqueue color picker scripts.
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script(
			'wp-color-picker',
			'jQuery( function() { jQuery( ".color-picker" ).wpColorPicker(); } );'
		);

		// Allows switching between tabs.
		add_action( 'admin_print_footer_scripts', 'gps2photos_admin_tabs_script' );
	}
}

add_filter( 'ngg_manage_images_row_actions', 'gps2photos_add_nextgen_action_callback', 10, 1 );
/**
 * Adds our action link callback to the list of actions for a NextGEN image.
 *
 * @since 1.0.0
 *
 * @param array $actions An array of action links.
 * @return array The modified array of action links.
 */
function gps2photos_add_nextgen_action_callback( $actions ) {
	// Add our function to the array of callbacks.
	// NextGEN will execute this function later, passing the $picture object to it.
	$actions['gps2photos_add_gps'] = 'gps2photos_render_gps_action_link';
	return $actions;
}

/**
 * Renders the "Add/Amend GPS" action link HTML.
 * This function is called by NextGEN Gallery for each image.
 *
 * @param string $id The action ID ('gps2photos_add_gps').
 * @param object $picture The image object from NextGEN.
 * @return string The HTML for the action link.
 */
function gps2photos_render_gps_action_link( $id, $picture ) {
	return sprintf(
		'<a href="#" class="gps2photos-add-gps" data-gallery-name="nextgen" data-pid="%d" data-image-url="%s">%s</a>',
		esc_attr( $picture->pid ),
		esc_url( $picture->imageURL ), // imageURL is the absolute URL to the image.
		esc_html__( 'Add/Amend GPS', 'gps-2-photos' )
	);
}

add_action( 'admin_notices', 'gps2photos_admin_notices' );
/**
 * Shows messages to users about settings validation problems.
 *
 * @since 1.0.0
 */
function gps2photos_admin_notices() {
	// Only show on your plugin settings page.
	if ( isset( $_GET['page'] ) && $_GET['page'] === 'gps-2-photos' ) {

		if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
			echo '<div id="message" class="notice notice-success is-dismissible">
                <p><strong>' . esc_html__( 'Settings saved.', 'gps-2-photos' ) . '</strong></p>
            </div>';
		} elseif ( isset( $_GET['settings-error'] ) && ! $_GET['settings-error'] ) {
			echo '<div id="message" class="notice notice-error is-dismissible">
                <p><strong>' . esc_html__( 'An error occurred when saving!', 'gps-2-photos' ) . '</strong></p>
            </div>';
		}

		settings_errors( 'plugin_gps2photo' );
	}
}

add_action( 'admin_menu', 'gps2photos_add_page', 99 );
/**
 * Adds admin settings page.
 *
 * @since 1.0.0
 */
function gps2photos_add_page() {
	add_menu_page(
		__( 'GPS 2 Photos', 'gps-2-photos' ),
		__( 'GPS 2 Photos', 'gps-2-photos' ),
		'manage_options',
		'gps-2-photos',
		'gps2photos_options_page',
		'dashicons-location-alt'
	);
}

/**
 * Allows switching between tabs.
 *
 * @since 1.0.0
 */
function gps2photos_admin_tabs_script() {
	?>
	<script type="text/javascript">
		jQuery(document).ready(function($) {
			var active_tab = localStorage.getItem("gps2photos_active_tab");
			if (active_tab) {
				let tab_content, tab_links;
				tab_content = document.getElementsByClassName("gps2photos_tab_content");
				for (let i = 0; i < tab_content.length; i++) {
					tab_content[i].style.display = "none";
				}
				tab_links = document.getElementsByClassName("gps2photos_tab_links");
				for (let i = 0; i < tab_links.length; i++) {
					tab_links[i].className = tab_links[i].className.replace("gps2photos_active", "");
				}
				document.getElementById(active_tab).style.display = "block";
				$('button[name="' + active_tab + '"]').addClass("gps2photos_active");
			} else {
				$('button[name="general"]').addClass("gps2photos_active");
				document.getElementById('general').style.display = "block";
			}
		});

		function gps2photos_openTab(evt, tagName) {
			localStorage.setItem('gps2photos_active_tab', jQuery(evt.currentTarget).attr('name'));
			let tab_content, tab_links;
			tab_content = document.getElementsByClassName("gps2photos_tab_content");
			for (let i = 0; i < tab_content.length; i++) {
				tab_content[i].style.display = "none";
			}
			tab_links = document.getElementsByClassName("gps2photos_tab_links");
			for (let i = 0; i < tab_links.length; i++) {
				tab_links[i].className = tab_links[i].className.replace(" gps2photos_active", "");
			}
			document.getElementById(tagName).style.display = "block";
			evt.currentTarget.className += " gps2photos_active";
		}
	</script>
	<?php
}

/**
 * Creates the options page.
 *
 * @since 1.0.0
 */
function gps2photos_options_page() {
	wp_enqueue_media();

	?>
	<div class="wrap gps2photos_wrap">
		<h2><?php esc_html_e( 'GPS 2 Photos Settings', 'gps-2-photos' ); ?></h2>
		<br />

		<div class="gps2photos_tab">
			<button class="gps2photos_tab_links" onclick="gps2photos_openTab( event, 'general' )" name="general"><?php esc_html_e( 'General', 'gps-2-photos' ); ?></button>
			<button class="gps2photos_tab_links" onclick="gps2photos_openTab( event, 'map' )" name="map"><?php esc_html_e( 'Map', 'gps-2-photos' ); ?></button>
			<button class="gps2photos_tab_links" onclick="gps2photos_openTab( event, 'gps2photos_maps_addon' )" name="gps2photos_maps_addon"><?php esc_html_e( 'Geo2Maps Add-on', 'gps-2-photos' ); ?></button>
		</div>

		<form action="options.php" method="post">
			<div class="gps2photos_float_save_button">
				<p class="submit gps2photos_submit_button">
					<input type="submit" class="gps2photos_save_button button-primary" value="<?php esc_html_e( 'Save Changes', 'gps-2-photos' ); ?>" />
				</p>
			</div>

			<?php settings_fields( 'plugin_gps2photos_options' ); ?>
			<?php $options = gps2photos_convert_to_int( get_option( 'plugin_gps2photos_options' ) ); ?>

			<div id="general" class="postbox gps2photos_tab_content">
				<h3>
					<p>&ensp;&ensp;<?php esc_html_e( 'Map Service Provider', 'gps-2-photos' ); ?></p>
				</h3>
				<div class="inside">
					<h3>&ensp;&ensp;&ensp;&ensp;Azure Maps Service -
						<?php
						if ( ! isset( $options['geo_azure_auth_status'] ) || $options['geo_azure_auth_status'] === 0 ) {
							echo '<span class="gps2photos_key_not_activated">' . esc_html_e( 'NOT ACTIVATED', 'gps-2-photos' ) . '</span></h3><br />
								<span class="description">';
							printf(
								/* translators: 1: HTML link opening tag. 2: HTML link closing tag. 3: HTML link opening tag. 4: HTML link closing tag. */
								esc_html_e( 'Get the Azure Maps Key by following the instruction in %1$shere%2$s or go directly to: %3$sAzure Maps Dev Center%4$s.', 'gps-2-photos' ),
								'<a href="https://msdn.microsoft.com/en-us/library/ff428642.aspx title="Getting a Azure Maps Key" target="_blank">',
								'</a> ',
								'<a href="https://www.bingmapsportal.com/" title="Azure Maps Dev Center" target="_blank">',
								'</a>.</span>'
							);
						} else {
							echo '<span class="gps2photos_key_activated">' . esc_html_e( 'ACTIVATED', 'gps-2-photos' ) . '</h3></span>';
						}
						?>
						<h4><?php esc_html_e( 'Azure Maps API Key', 'gps-2-photos' ); ?></h4>

						<input type="text" name="plugin_gps2photos_options[geo_azure_key]" value="<?php echo esc_textarea( $options['geo_azure_key'] ); ?>" style='min-width:25em' size='<?php echo esc_textarea( ( strlen( $options['geo_azure_key'] ) + 16 ) ); ?>' /><br />
						<p><span class="description">
							<?php
							printf(
								/* translators: 1: HTML link opening tag. 2: HTML link closing tag. 3: HTML link opening tag. 4: HTML link closing tag. 5: HTML link opening tag. 6: HTML link closing tag. */
								esc_html__( 'Azure Maps API platform is free to use up to a certain limit. For more information refer to: %1$sLicensing Use Rights%2$s, %3$sFAQ%4$s, %5$sPricing%6$s.', 'gps-2-photos' ),
								'<a href="https://www.microsoft.com/licensing/docs/view/Licensing-Use-Rights" title="Azure Maps API Licensing Use Rights" target="_blank">',
								'</a>',
								'<a href="https://azure.microsoft.com/en-gb/products/azure-maps/?msockid=0992d89d236e617e044eca08276e6764#faq" title="Azure Maps API FAQ" target="_blank">',
								'</a>',
								'<a href="https://azure.microsoft.com/en-gb/pricing/details/azure-maps/" title="Azure Maps API Pricing" target="_blank">',
								'</a>'
							);
							?>
						</span><br />
						<span class="description">
							<?php
							printf(
								/* translators: 1: HTML link opening tag. 2: HTML link closing tag. */
								esc_html__( 'Here you can create the %1$sAzure Free Account%2$s.', 'gps-2-photos' ),
								'<a href="https://azure.microsoft.com/en-gb/pricing/purchase-options/azure-account?icid=azurefreeaccount" title="Azure Free Account" target="_blank">',
								'</a>'
							);
							?>
						</span><br />
						<span class="description"><b>
							<?php
							esc_html_e( 'Important: ', 'gps-2-photos' );
							?>
							</b>
							<?php
							esc_html_e( 'Add your website domain to the CORS Allowed Origins field in your Azure account.', 'gps-2-photos' );
							?>
						</span></p>
				</div>
				<div class="inside">
					<p><input type="checkbox" name="plugin_gps2photos_options[gps_media_library]" value="1" <?php checked( $options['gps_media_library'], 1 ); ?>>&ensp;<b><?php esc_html_e( 'Add GPS info to WP Media Library', 'gps-2-photos' ); ?></b></p>
					<p><input type="checkbox" name="plugin_gps2photos_options[gps_coordinates_preview]" value="1" <?php checked( $options['gps_coordinates_preview'], 1 ); ?>>&ensp;<b><?php esc_html_e( 'Add GPS Coordinates preview on a mini map to WP Media Library', 'gps-2-photos' ); ?></b></p>
					<p><input type="checkbox" name="plugin_gps2photos_options[always_override_gps]" value="1" <?php checked( $options['always_override_gps'], 1 ); ?>>&ensp;<b><?php esc_html_e( 'Always override existing GPS coordinates without asking', 'gps-2-photos' ); ?></b></p>
					<p><input type="checkbox" name="plugin_gps2photos_options[backup_existing_coordinates]" value="1" <?php checked( $options['backup_existing_coordinates'], 1 ); ?>>&ensp;<b><?php esc_html_e( 'Backup Existing Coordinates	', 'gps-2-photos' ); ?></b>
						<span class="gps2photos-tooltip-container">
							<img class="gps2photos-tooltip-trigger" src='<?php echo esc_attr( GPS_2_PHOTOS_DIR_URL . '/img/information.png' ); ?>' alt="Info">
							<span class="gps2photos-tooltip-text">
								<?php esc_html_e( 'GPS coordinates will be added to the User Comment Exif field as "Original GPS coordinates:Latitude:...,Longitude:..." if not already there. If present they can be restored.', 'gps-2-photos' ); ?>
							</span>
						</span>
					</p>
				</div>
				<div class="gps2photos_restore_defaults">
					<h3>
						<p><?php esc_html_e( 'Restore default settings', 'gps-2-photos' ); ?> </p>
					</h3>
					<input type="checkbox" name="plugin_gps2photos_options[restore_defaults]" value="1" <?php checked( $options['restore_defaults'], 1 ); ?>>&ensp;<?php esc_html_e( 'Enable only if you want to restore default settings on deactivation/activation of this plugin.', 'gps-2-photos' ); ?>
				</div>
			</div>

			<div id="map" class="postbox gps2photos_tab_content">
				<h3>
					<p>&ensp;&ensp;<?php esc_html_e( 'Map Options', 'gps-2-photos' ); ?></p>
				</h3>

				<div class="inside">
					<b><?php esc_html_e( 'Zoom Level', 'gps-2-photos' ); ?></b><br />
					<input type="text" class="code gps2photos_margin_top gps2photos_margin_bottom" name="plugin_gps2photos_options[zoom]" value="<?php echo (int) $options['zoom']; ?>" /><br />
					<span class="description"><?php esc_html_e( 'Zoom Level for single image. Maps with several pins are focused automatically.', 'gps-2-photos' ); ?></span><br /><br />
					<b><?php esc_html_e( 'Map Container Height', 'gps-2-photos' ); ?></b><br />
					<input type="text" class="code gps2photos_margin_top" name="plugin_gps2photos_options[map_height]" value="<?php echo esc_attr( $options['map_height'] ); ?>" /><br /><br />
					<b><?php esc_html_e( 'Map Container Width', 'gps-2-photos' ); ?></b><br />
					<input type="text" class="code gps2photos_margin_top gps2photos_margin_bottom" name="plugin_gps2photos_options[map_width]" value="<?php echo esc_attr( $options['map_width'] ); ?>" /><br />
					<span class="description">
						<?php
						printf(
							/* translators: 1: HTML line break tag. 2: HTML code opening tag. 3: HTML code closing tag. */
							esc_html__( 'You can use something like "235px", "auto" or "78%%". Number only "235" will be changed to "235px" automatically. "auto" does not work with the Map Height.%1$sHeight and width of the maps can be changed directly by using CSS. The class is named %2$sgps2photos_maps_map%3$s', 'gps-2-photos' ),
							' <br />',
							'<code>',
							'</code>'
						);
						?>
					</span><br />

					<p><input type="checkbox" name="plugin_gps2photos_options[map_fullscreen]" value="1" <?php checked( $options['map_fullscreen'], 1 ); ?>>&ensp;<b><?php esc_html_e( 'Enable fullscreen mode', 'gps-2-photos' ); ?></b></p><span class="description"><?php esc_html_e( 'This option shows a button that opens the map in full-screen mode, expanding to cover the entire physical screen instead of just the browser window.', 'gps-2-photos' ); ?></span><br />

					<h4><?php esc_html_e( 'Map Style', 'gps-2-photos' ); ?></h4>
					<input type="radio" name="plugin_gps2photos_options[map]" value="road" <?php checked( $options['map'], 'road', 1 ); ?>> <?php esc_html_e( 'Road', 'gps-2-photos' ); ?><br />
					<input type="radio" name="plugin_gps2photos_options[map]" value="satellite" <?php checked( $options['map'], 'satellite', 1 ); ?>> <?php esc_html_e( 'Satellite', 'gps-2-photos' ); ?><br />
					<input type="radio" name="plugin_gps2photos_options[map]" value="satellite_road_labels" <?php checked( $options['map'], 'satellite_road_labels', 1 ); ?>> <?php esc_html_e( 'Hybrid (satellite_road_labels)', 'gps-2-photos' ); ?><br />
					<input type="radio" name="plugin_gps2photos_options[map]" value="grayscale_light" <?php checked( $options['map'], 'grayscale_light', 1 ); ?>> <?php esc_html_e( 'Grayscale Light', 'gps-2-photos' ); ?><br />
					<input type="radio" name="plugin_gps2photos_options[map]" value="grayscale_dark" <?php checked( $options['map'], 'grayscale_dark', 1 ); ?>> <?php esc_html_e( 'Grayscale Dark', 'gps-2-photos' ); ?><br />
					<input type="radio" name="plugin_gps2photos_options[map]" value="night" <?php checked( $options['map'], 'night', 1 ); ?>> <?php esc_html_e( 'Night', 'gps-2-photos' ); ?><br />
					<input type="radio" name="plugin_gps2photos_options[map]" value="road_shaded_relief" <?php checked( $options['map'], 'road_shaded_relief', 1 ); ?>> <?php esc_html_e( 'Road Shaded Relief', 'gps-2-photos' ); ?><br /><br />
					<h4><?php esc_html_e( 'Which elements should be displayed?', 'gps-2-photos' ); ?></h4>
					<input type="checkbox" name="plugin_gps2photos_options[dashboard]" value="1" <?php checked( $options['dashboard'], 1 ); ?>>&ensp;<?php esc_html_e( 'Dashboard with map navigation controls', 'gps-2-photos' ); ?><br />
					<input type="checkbox" name="plugin_gps2photos_options[locate_me_button]" value="1" <?php checked( $options['locate_me_button'], 1 ); ?>>&ensp;<?php esc_html_e( 'Locate Me button (dependent on Dashboard visibility)', 'gps-2-photos' ); ?><br />
					<input type="checkbox" name="plugin_gps2photos_options[scalebar]" value="1" <?php checked( $options['scalebar'], 1 ); ?>>&ensp;<?php esc_html_e( 'Scalebar', 'gps-2-photos' ); ?><br />
					<input type="checkbox" name="plugin_gps2photos_options[logo]" value="1" <?php checked( $options['logo'], 1 ); ?>>&ensp;<?php esc_html_e( 'Azure logo', 'gps-2-photos' ); ?><br />
					<span style="margin-left:26px;" class="description">
						<?php
						printf(
							/* translators: 1: HTML link opening tag. 2: HTML link closing tag. */
							esc_html__( 'Officially undocumented option. Disabling may likely breach the %1$sAzure Maps Platform API’s Terms of Use%2$s!', 'gps-2-photos' ),
							'<a href="https://www.microsoft.com/maps/product/terms.html" title="Terms of Use" target="_blank">',
							'</a>'
						);
						?>
					</span><br />
				</div>
				<div class="inside">
					<h3>
						<p><?php esc_html_e( 'Pushpins options', 'gps-2-photos' ); ?></p>
					</h3>
					<b>
						<?php esc_html_e( 'Pushpins Color for Images', 'gps-2-photos' ); ?>
					</b>
					<br />
					<div class="gps2photos_margin_top">
						<input type="text" class="color-picker code" data-default-color="#00FF00" name="plugin_gps2photos_options[pin_color]" value="<?php echo esc_attr( $options['pin_color'] ); ?>" />
					</div><br />
					<b>
						<?php esc_html_e( 'Pushpin Secondary Color', 'gps-2-photos' ); ?>
					</b>
					<br />
					<div class="gps2photos_margin_top">
					<input type="text" class="color-picker code" data-default-color="#000000" name="plugin_gps2photos_options[pin_secondary_color]" value="<?php echo esc_attr( $options['pin_secondary_color'] ); ?>" />
					</div><br />
					<b>
						<?php esc_html_e( 'Pushpin Icon Type', 'gps-2-photos' ); ?>
					</b><br />
					<div style="display: inline-block; vertical-align: bottom;">
						<select id="gps2image_pin_icon_type" class="gps2photos_margin_top gps2photos_margin_bottom" name="plugin_gps2photos_options[pin_icon_type]" style="margin-top:17px" onchange="gps2photos_update_image()">
							<option value="marker" <?php echo ( $options['pin_icon_type'] === 'marker' ) ? 'selected' : ''; ?>>marker</option>
							<option value="marker-thick" <?php echo ( $options['pin_icon_type'] === 'marker-thick' ) ? 'selected' : ''; ?>>marker-thick</option>
							<option value="marker-square" <?php echo ( $options['pin_icon_type'] === 'marker-square' ) ? 'selected' : ''; ?>>marker-square</option>
							<option value="marker-ball-pin" <?php echo ( $options['pin_icon_type'] === 'marker-ball-pin' ) ? 'selected' : ''; ?>>marker-ball-pin</option>
							<option value="flag" <?php echo ( $options['pin_icon_type'] === 'flag' ) ? 'selected' : ''; ?>>flag</option>
							<option value="pin" <?php echo ( $options['pin_icon_type'] === 'pin' ) ? 'selected' : ''; ?>>pin</option>
							<option value="pin-round" <?php echo ( $options['pin_icon_type'] === 'pin-round' ) ? 'selected' : ''; ?>>pin-round</option>
						</select><img id="gps2image_pin_icon_image" style="margin-left:20px; margin-bottom:6px; vertical-align: bottom;" src='<?php echo esc_attr( GPS_2_PHOTOS_DIR_URL . '/img/pin-types/' . $options['pin_icon_type'] . '.png' ); ?>' alt="icon type">
					</div>
					<br />
				</div>
			</div>
			<script>
			function gps2photos_update_image() {
				var select = document.getElementById("gps2image_pin_icon_type");
				var image = document.getElementById("gps2image_pin_icon_image");
				var selectedValue = select.value;

				// Update the image source
				image.src = "<?php echo esc_attr( GPS_2_PHOTOS_DIR_URL ); ?>/img/pin-types/" + selectedValue + ".png";
			}
			</script>
			<div id="gps2photos_maps_addon" class="postbox gps2photos_tab_content">
				<div class="inside" style="max-width:1544px;">
					<a href="http://geo2maps.pasart.net" target="_blank">
						<img src="<?php echo esc_url( GPS_2_PHOTOS_DIR_URL . '/img/banner-1544x500.jpg' ); ?>" alt="Geo2Maps Banner" style="width:100%;" />
					</a>
					<h1 style="text-align:center;font-size:3em;">Geo2Maps</h1>
					<p style="text-align:center;font-size:1.2em;">A free WordPress plugin to display geolocated photos from NextGEN Gallery on a map.</p>
					<p style="text-align:center;"><a href="https://wordpress.org/plugins/nextgen-gallery-geo/" target="_blank">Get the free version</a></p>
					<br />
					<h1 style="text-align:center;font-size:3em;">Geo2Maps Plus</h1>
					<p style="text-align:center;font-size:1.2em;">Upgrade to the Plus version for more features:</p>
					<ul style="text-align:center;list-style-position: inside;">
						<li>••• Map with tagged images •••</li>
						<li>••• EXIF Viewer •••</li>
						<li>••• Preview Map •••</li>
						<li>••• Pushpins with thumbnails on hover •••</li>
						<li>••• Option to disable with other maps in Auto Mode •••</li>
						<li>••• Option to block Auto Mode •••</li>
						<li>••• Exclude specific albums or gallery in Worldmap •••</li>
						<li>••• Additional map styles: High Contrast Light, High Contrast Dark •••</li>
						<li>••• Rectangular thumbnails with round corners •••</li>
						<li>••• Albums Thumbnail scale factor •••</li>
						<li>••• Image and border shadow for thumbnails •••</li>
						<li>••• Pointer for thumbnails •••</li>
						<li>••• Pins from image or SVG file •••</li>
						<li>••• Pins clustering •••</li>
						<li>••• Infobox with Lightbox •••</li>
						<li>••• NextGEN Gallery Lightbox Override Mode •••</li>
						<li>••• URL link to a specific web page instead of Infobox or Lightbox •••</li>
						<li>••• CSS override field •••</li>
						<li>••• Round corners for Infobox •••</li>
						<li>••• Multiple Infoboxes •••</li>
						<li>••• Infobox dragging •••</li>
						<li>••• Hide image description and expand when clicked •••</li>
						<li>••• Transitions type and speed for Fancybox •••</li>
						<li>••• Sliding or fixed side caption panel for Fancybox 3 •••</li>
						<li>••• Side caption panel mini map with images location for Fancybox 3 •••</li>
						<li>••• Video support and video options for Fancybox 3 •••</li>
						<li>••• Horizontal thumbnails preview and automatic orientation for Fancybox 3 •••</li>
						<li>••• Show specific buttons, navigation arrows, counter for Fancybox 3 •••</li>
						<li>••• Show Facebook and Twitter buttons for Fancybox 3 •••</li>
						<li>••• Transitions type and speed for Fancybox 3 •••</li>
						<li>••• Translations: German, Polish, Spanish for Fancybox 3 •••</li>
						<li>••• Disable Right-click - simple image protection for images in Fancybox 3 •••</li>
						<li>••• Transitions type and speed for Slimbox 2 •••</li>
					</ul>
					<p style="text-align:center;"><a href="http://geo2maps.pasart.net" target="_blank">Get Geo2Maps Plus</a></p>
				</div>
			</div>
		</form>
	</div>

	<?php
}
?>
