<?php
/*
   Plugin Name: Haystack
   Plugin URI: http://wordpress.org/extend/plugins/haystack/
   Version: 0.1
   Author: <a href="http://mellenger.com/">Mellenger Inc.</a>
   Description: Modern, mobile-friendly web design simplifies navigation but makes it harder to locate specific content. By adding Haystack to your site, your search bar becomes a super-charged, auto-completing web utility that helps people quickly find what they’re looking for.
   Text Domain: haystack
   License: GPLv3
  */

/*
    "WordPress Plugin Template" Copyright (C) 2016 Michael Simpson  (email : michael.d.simpson@gmail.com)

    This following part of this file is part of WordPress Plugin Template for WordPress.

    WordPress Plugin Template is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    WordPress Plugin Template is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Contact Form to Database Extension.
    If not, see http://www.gnu.org/licenses/gpl-3.0.html
*/

const HAYSTACK_PREF  = 'h_';
const HAYSTACK_FRONT = 'http://js.haystack.menu/v1/';
const HAYSTACK_API_SERVER  = 'http://api.haystack.menu/api/';
const HAYSTACK_API_VERSION = 'v1';    
const HAYSTACK_POST_PROCESS = 5;    
const HAYSTACK_AJAX = '/wp-admin/admin-ajax.php';    
const HAYSTACK_ANALYTICS = '/wp-admin/admin-ajax.php?action=haystack_ping';    
const HAYSTACK_AJAX_ADMIN = '/wp-admin/admin-ajax.php?action=haystack_admin';    

$Haystack_minimalRequiredPhpVersion = '5.0';

/**
 * Check the PHP version and give a useful error message if the user's version is less than the required version
 */
function Haystack_noticePhpVersionWrong() {
    global $Haystack_minimalRequiredPhpVersion;
    echo '<div class="updated fade">' .
      __('Error: plugin "Haystack" requires a newer version of PHP to be running.',  'haystack').
            '<br/>' . __('Minimal version of PHP required: ', 'haystack') . '<strong>' . $Haystack_minimalRequiredPhpVersion . '</strong>' .
            '<br/>' . __('Your server\'s PHP version: ', 'haystack') . '<strong>' . phpversion() . '</strong>' .
         '</div>';
}


function Haystack_PhpVersionCheck() {
    global $Haystack_minimalRequiredPhpVersion;
    if (version_compare(phpversion(), $Haystack_minimalRequiredPhpVersion) < 0) {
        add_action('admin_notices', 'Haystack_noticePhpVersionWrong');
        return false;
    }
    return true;
}


/**
 * Initialize internationalization (i18n) for this plugin.
 */
function Haystack_i18n_init() {
    $pluginDir = dirname(plugin_basename(__FILE__));
    load_plugin_textdomain('haystack', false, $pluginDir . '/languages/');
}



/**
 * Run Initialization
 */
add_action('plugins_loaded','Haystack_i18n_init');

/**
 * Run the version check.
 * If it is successful, continue with initialization for this plugin
 */
if (Haystack_PhpVersionCheck()) {
    // Only load and run the init function if we know PHP version can parse it
    include_once('haystack_init.php');
    Haystack_init(__FILE__);
}

function deactivate_Haystack() {
}