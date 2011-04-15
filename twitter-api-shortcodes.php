<?php
/*
Plugin Name: Twitter API Shortcodes
Version: 0.0.3Alpha
Plugin URI: http://tasforwp.ryangeyer.com/
Description: A plugin to add single tweets or twitter searches to your posts and pages using shortcodes
Author: Ryan J. Geyer
Author URI: http://www.nslms.com
*/
define(TAS_VERSION, '0.0.3Alpha');
define(TAS_DB_VERSION, '0.0.3');
define(TAS_ADMIN_OPTIONS_ID, '83a70cd3-3f32-456d-980d-309169c26ccf');

require_once(WP_PLUGIN_DIR . '/twitter-api-shortcodes/libs/twitter.api.wp.class.inc.php');
require_once(WP_PLUGIN_DIR . '/twitter-api-shortcodes/libs/smarty/Smarty.class.php');
require_once(WP_PLUGIN_DIR . '/twitter-api-shortcodes/libs/functions.php');
require_once(WP_PLUGIN_DIR . '/twitter-api-shortcodes/libs/tasforwp.class.inc.php');
require_once(WP_PLUGIN_DIR . '/twitter-api-shortcodes/libs/twitter.status.class.inc.php');
require_once(WP_PLUGIN_DIR . '/twitter-api-shortcodes/libs/twitter.search.class.inc.php');


/*****************************************************************
 * Callbacks for the Wordpress API                               *
 *****************************************************************/


/*****************************************************************
 * Some utility functions                                        *
 *****************************************************************/


/*****************************************************************
 * Registration of all of the callbacks                          *
 *****************************************************************/

register_activation_hook(WP_PLUGIN_DIR . '/twitter-api-shortcodes/libs/tasforwp.class.inc.php',
  TasForWp::$install_hook);
register_deactivation_hook(WP_PLUGIN_DIR . '/twitter-api-shortcodes/libs/tasforwp.class.inc.php',
  TasForWp::$uninstall_hook);

add_action('tas_cron_action', TasForWp::$cron_hook);
add_action('admin_menu', TasForWp::$admin_menu_hook);
add_action('wp_print_styles', TasForWp::$wp_print_styles_hook);
add_action('admin_print_scripts', TasForWp::$admin_print_scripts_hook);
add_action('admin_print_styles', TasForWp::$admin_print_styles_hook);

add_shortcode('twitter_status_by_id', TasForWp::$status_by_id_shortcode);
add_shortcode('twitter_search', TasForWp::$search_shortcode);

add_action('init', 'TasForWp::tas_add_tinymce_buttons_action');