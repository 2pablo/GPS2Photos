=== GPS 2 Photos ===
Contributors: pablo2
Tags: gps, exif, photo, image, map, location, coordinates, geo, geotag
Donate link: https://www.paypal.com/PawelBlock
Requires at least: 5.0
Tested up to: 6.8.2
Requires PHP: 7.2.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

View and edit EXIF GPS coordinates for your photos by selecting a location on a map or typing in the coordinates.

== Description ==

GPS 2 Photos enhances your WordPress Media Library and popular gallery plugins like NextGEN Gallery, Envira Gallery, FooGallery, and Modula, by allowing you to easily view, add, and edit GPS coordinates for your images. It displays a field with GPS coordinates for each JPEG image, showing its location based on EXIF data. If an image doesn't have GPS information, you can add it by simply clicking on the map, searching for a location, or typing it in manually.

The plugin is using Microsoft Azure Maps and requires free Azure Maps API Key to function.
To amend EXIF GPS coordinates is using the PHP Exif Library (PEL) by Martin Geisler. (Copyright (C) 2004–2006 Martin Geisler. Licensed under the GNU GPL.)

= Features =

*   **WordPress Media Library & NextGEN Gallery Integration:** Works seamlessly inside the standard Media Library and also adds an "Add/Amend GPS" link to images in the NextGEN Gallery management screen.
*   **Gallery Support:** Integrates with popular gallery plugins, including NextGEN Gallery, Envira Gallery, FooGallery, and Modula.
*   **View GPS Data:** See a map with a pin for any image that has GPS coordinates in its EXIF data.
*   **Edit & Add GPS Data:** Easily add or change an image's location by dragging the pin or clicking anywhere on the map.
*   **Interactive Map Modal:** A clean and simple map interface with a location search bar opens in a modal window.
*   **Backup & Restore:** The plugin automatically backs up original GPS data, allowing you to restore it with a single click.
*   **Azure Maps Integration:** Utilizes the powerful and reliable Azure Maps for displaying map tiles.

== Installation ==

1.  Install GPS 2 Photos from the plugins repository or download and extract the zip file into the `wp-content/plugins/` directory of your WordPress installation.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Acquire a free Azure Maps API Key from Microsoft Azure at https://azure.microsoft.com. You can find a link on the plugin's settings page.
4.  In your Azure account, add your domain to the CORS allowed origins (e.g., `https://www.yourdomain.com`).
5.  Go to the admin panel (`Settings -> GPS 2 Photos`) and paste the API Key into the corresponding field.
6.  Configure any other options as you require.

== Frequently Asked Questions ==

**How can I get an Azure Maps API Key?**

Go to https://azure.microsoft.com and sign up for a free account. You will need a Microsoft account. Follow the instructions to create a new Azure Maps account and get the subscription key. A link to a detailed guide is available on the plugin's settings page.

**Important:** Remember to add your website domain to the CORS Allowed Origins field in your Azure Maps account settings (e.g., `https://www.yourdomain.com`).

Azure Maps offers a generous free tier. For example, you get thousands of free map tile transactions per month, which is more than enough for most websites.

**How do I use GPS 2 Photos?**

*   **WordPress Media Library:** Navigate to your Media Library (either list or grid view). In list view, you'll see a button in the "GPS Location" column. In grid view, you'll find the button in the "Attachment Details" panel.
*   **NextGEN Gallery:** Navigate to a gallery via the "Manage Galleries" page. You will see an "Add/Amend GPS" link in the actions for each image.
*   **Envira, FooGallery, Modula:** On the gallery edit screen, you will find a button or link to add/amend GPS coordinates for each image.

**How to use the search bar when multiple locations are available?**

When you search for a location, the map may display several pins if multiple matches are found. You can click on any of these result pins to see more details in a popup. When you click on a pin, it will change color to indicate it's selected, and its coordinates will automatically populate the Latitude and Longitude fields. You can then save these coordinates to your image.

Clicking the button or link will open a modal window with a map. You can then view, edit, or add GPS coordinates.

**Can I use this plugin if my photos have no GPS data?**

Yes! That's one of its main features. You can easily add GPS coordinates to any image by opening the map modal and clicking on the desired location. The latitude and longitude will be filled in automatically, and you can then save them to the image.

**What happens to my original GPS data?**

If you enable the "Backup Existing Coordinates" option in the plugin settings, editing an image that already has GPS data will save the original coordinates into the image's EXIF "User Comment" field. This prevents the original data from being lost. A "Restore Original Coordinates" button will then appear in the map modal, allowing you to revert to the backed-up coordinates at any time.

== Screenshots ==

1.  The "View/Edit Map" button in the Media Library (List View).
2.  The "View/Edit Map" button in the Attachment Details screen (Grid View).
3.  The map modal showing an image's location.
4.  Editing coordinates by dragging the marker.
5.  The "Restore Coordinates" button for images with backed-up GPS data.

== Changelog ==

= 1.0.0 - 2024-07-26 =

*   Initial release.