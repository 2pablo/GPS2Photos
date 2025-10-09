/**
* File type: JavaScript Document
* Plugin: GPS 2 Photos Add-on
* Description: Code to loading Azure Maps API Key using Ajax.
* Author: Pawel Block
* Version: 1.0.0
*
* @package    GPS 2 Photo Add-on
* @subpackage JavaScript
* @since      1.0.0
* @author     Pawel Block <pblock@op.pl>
*/

// Function to retrieve the Azure API key using AJAX.
function gps2photos_get_azure_api_key(callback) {
    jQuery.post(
        gps2photos_api_key_ajax.ajaxurl,
        {
            'action': 'gps2photos_get_azure_maps_api_key',
            'nonce': gps2photos_api_key_ajax.get_api_key_nonce
        },
        function(response) {
            if (response && response.success === true) {
                // Pass the API key to the callback function
                callback(response.data);
            } else {
                console.error('Failed to retrieve Azure Maps API key. The server responded but indicated failure. Response:', response);
            }
        }
    ).fail(function(jqXHR, textStatus, errorThrown) {
        console.error('AJAX request to get Azure Maps API key failed. Status: ' + textStatus + '. Error: ' + errorThrown);
    });
}
