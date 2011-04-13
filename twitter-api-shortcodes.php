<?php
/*
Plugin Name: Twitter API Shortcodes
Version: 0.0.3Alpha
Plugin URI: http://tasforwp.ryangeyer.com/
Description: A plugin to add single tweets or twitter searches to your posts and pages using shortcodes
Author: Ryan J. Geyer
Author URI: http://www.nslms.com
*/
require_once(ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/libs/twitter.api.wp.class.inc.php');
require_once(ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/libs/smarty/Smarty.class.php');
require_once(ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/libs/functions.php');
require_once(ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/libs/tasforwp.class.inc.php');

// Some smarty configuration
$smarty = new Smarty();
// TODO: I'm not entirely certain how I feel about having the compile, cache, and config dirs as subdirs of
// the templates dir, but if it works, why complain.. Right?
$smarty->template_dir = ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/templates/';
$smarty->compile_dir = ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/templates/templates_c/';
$smarty->config_dir = ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/templates/configs/';
$smarty->cache_dir = ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/templates/cache/';

/*****************************************************************
 * Callbacks for the Wordpress API                               *
 *****************************************************************/





function twitter_search_func($atts) {
  global $wpdb, $tasSearchName, $tasStatusByIdName, $tasStatusSearchName;
  // Initialize extracted vals from shortcode so that the IDE doesn't complain
  $id = '';
  extract(
    shortcode_atts(
      array(
        'id' => 0
      ),
      $atts
    )
  );

  // Little bit of validation
  if (!$id) {
    return;
  }

  $searchRow = $wpdb->get_row("SELECT * FROM `$tasSearchName` WHERE id = $id");

  $jsonObjs = array();

  // We're only going to look in the DB for the statuses associated with this search
  // if the tag indicates that we should archive the statuses, it's a waste of an SQL
  // call otherwise.
  if ($searchRow->archive) {
    $rows = $wpdb->get_results("SELECT * FROM `$tasStatusByIdName` WHERE id IN (SELECT status_id FROM `$tasStatusSearchName` WHERE search_id = $id)");
    foreach ($rows as $row) {
      array_push($jsonObjs, json_decode($row->status_json));
    }
  } else {
    try {
      $response = TwitterAPIWrapper::search(array('q' => $searchRow->search_term));
      $jsonObjs = $response->results;
    } catch (Exception $e) {
      // TODO: Should elegantly inform the user
    }
  }

  $output .= formatStatusList($jsonObjs);

  return $output;
}

function twitter_status_by_id_func($atts) {
  global $wpdb, $tasStatusByIdName;
  // Initialize extracted vals from shortcode so that the IDE doesn't complain
  $id = '';
  extract(
    shortcode_atts(
      array(
        'id' => 0
      ),
      $atts
    )
  );

  // TODO: Right now we're just bailing out on any validation errors, or on any failures.
  // Probably need to notify the user, or the admin somehow?

  // Validations
  if (!$id) {
    return;
  }

  $existingRecordQuery = sprintf('SELECT * FROM %s WHERE id = %s', $tasStatusByIdName, $id);
  $existingRecord = $wpdb->get_row($existingRecordQuery);

  if (!$existingRecord) {
    $response = TwitterAPIWrapper::getStatuses($id);

    $status = $response;
    cacheStatus($response);
  } else {
    $status = json_decode($existingRecord->status_json);
    $status->user->profile_image_url = $existingRecord->avatar_url;
  }

  return formatStatus($status);
}

function tas_add_tinymce_buttons($plugin_array) {
  $plugin_array['tas'] = get_bloginfo('url') . '/wp-content/plugins/twitter-api-shortcodes/tinymce_plugin/editor_plugin.js';
  return $plugin_array;
}

function tas_add_tinymce_buttons_action() {
  // Don't bother doing this stuff if the current user lacks permissions
  if (!current_user_can('edit_posts') && !current_user_can('edit_pages'))
    return;

  // Add only in Rich Editor mode
  if (get_user_option('rich_editing') == 'true') {
    add_filter("mce_external_plugins", "tas_add_tinymce_buttons");
    add_filter('mce_buttons', 'tas_mce_buttons');
  }
}

function tas_mce_buttons($buttons) {
  array_push($buttons, '|', 'tas');
  return $buttons;
}

/*****************************************************************
 * Some utility functions                                        *
 *****************************************************************/



function formatStatus($jsonObjOrString) {
  global $smarty;
  $jsonObj = jsonGenderBender($jsonObjOrString);

  normalizeStatus(&$jsonObj);

  $smarty->assign('tweet', $jsonObj);

  $tweetTemplate = ABSPATH.'wp-content/plugins/twitter-api-shortcodes/templates/tweet.tpl';
  $templateDir = get_template_directory();
  $curTemplateTweet = $templateDir.'/tweet.tpl';
  if(file_exists($curTemplateTweet)) {
    $tweetTemplate = $curTemplateTweet;
  }

  return $smarty->fetch($tweetTemplate);
}

function formatStatusList($arrayOfJsonStatusObjectsFromApi) {
  $retVal = '<div class="tweet-list">';
  if (is_array($arrayOfJsonStatusObjectsFromApi)) {
    foreach ($arrayOfJsonStatusObjectsFromApi as $status) {
      $retVal .= formatStatus($status);
    }
  }
  $retVal .= '</div>';
  return $retVal;
}

/*****************************************************************
 * Registration of all of the callbacks                          *
 *****************************************************************/

register_activation_hook(ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/libs/tasforwp.class.inc.php',
  TasForWp::$install_hook);
register_deactivation_hook(ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/libs/tasforwp.class.inc.php',
  TasForWp::$uninstall_hook);

add_action('tas_cron_action', TasForWp::$cron_hook);
add_action('admin_menu', TasForWp::$admin_menu_hook);
add_action('wp_head', TasForWp::$wp_head_hook);
add_action('admin_print_scripts', TasForWp::$admin_print_scripts_hook);
add_action('admin_head', TasForWp::$admin_head_hook);

add_shortcode('twitter_status_by_id', 'twitter_status_by_id_func');
add_shortcode('twitter_search', 'twitter_search_func');


add_action('init', 'tas_add_tinymce_buttons_action');