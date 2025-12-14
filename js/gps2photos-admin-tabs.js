/**
* File type: JavaScript Document
* Plugin: GPS 2 Photos Add-on
* Description: Code to to enable tabs switching on the admin settings page.
* Author: Pawel Block
* Version: 1.0.0
*
* @package    GPS 2 Photo Add-on
* @subpackage JavaScript
* @since      1.0.0
* @author     Pawel Block <pb@pasart.net>
*/

jQuery(document).ready(function ($) {
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
