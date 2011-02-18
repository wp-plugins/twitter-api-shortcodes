<?php
/*
Plugin Name: Twitter API Shortcodes
Version: 0.0.3Alpha
Plugin URI: http://tasforwp.ryangeyer.com/
Description: A plugin to add single tweets or twitter searches to your posts and pages using shortcodes
Author: Ryan J. Geyer
Author URI: http://www.nslms.com
*/

require_once(ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/twitter.api.wp.class.inc.php');
require_once(ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/libs/smarty/Smarty.class.php');
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
require_once(ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/functions.php');

define(TAS_VERSION, '0.0.3Alpha');
define(TAS_DB_VERSION, '0.0.3');
define(TAS_ADMIN_OPTIONS_ID, '83a70cd3-3f32-456d-980d-309169c26ccf');

// Some smarty configuration
$smarty = new Smarty();
// TODO: I'm not entirely certain how I feel about having the compile, cache, and config dirs as subdirs of
// the templates dir, but if it works, why complain.. Right?
$smarty->template_dir = ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/templates/';
$smarty->compile_dir = ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/templates/templates_c/';
$smarty->config_dir = ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/templates/configs/';
$smarty->cache_dir = ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/templates/cache/';

// This is duplicated in twitter_api_shortcodes_install by necessity, if you change something here, change it there also
$tasStatusByIdName = $wpdb->prefix . 'tas_status_by_id';
$tasStatusSearchName = $wpdb->prefix . 'tas_status_search';
$tasSearchName = $wpdb->prefix . 'tas_search';

/*****************************************************************
 * Callbacks for the Wordpress API                               *
 *****************************************************************/

// TODO: Need to add a schedule to update author avatar URL's on cached statuses.  Also need to add deactivation.
function tas_install() {
  global $wpdb;

  // This is duplicated above on the global level by necessity, if you change something here, change it there also
  $tasStatusByIdName = $wpdb->prefix . 'tas_status_by_id';
  $tasStatusSearchName = $wpdb->prefix . 'tas_status_search';
  $tasSearchName = $wpdb->prefix . 'tas_search';

  update_option('tas_last_installed', date('c'));

  $tas_db_info = json_decode(get_option('tas_db_info'));

  $tasStatusByIdSql = <<<EOF
CREATE TABLE `%s` (
`id` BIGINT UNSIGNED NOT NULL PRIMARY KEY ,
`author_id` BIGINT UNSIGNED NOT NULL,
`avatar_url` varchar(256) NOT NULL,
`status_json` TEXT NOT NULL,
KEY `author_id` (`author_id`)
);
EOF;

  $tasStatusSearchSql = <<<EOF
CREATE TABLE `%s` (
`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`status_id` BIGINT UNSIGNED NOT NULL ,
`search_id` BIGINT UNSIGNED NOT NULL ,
INDEX (  `status_id` ,  `search_id` )
);
EOF;

  $tasSearchSql = <<<EOF
CREATE TABLE `%s` (
`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
`search_term` VARCHAR( 512 ) NOT NULL,
`archive` tinyint(1) NOT NULL,
`last_successful_cron` BIGINT UNSIGNED NOT NULL DEFAULT 0
);
EOF;

  if ($wpdb->get_var("show tables like '$tasStatusByIdName'") != $tasStatusByIdName ||
    $tas_db_info->version_installed != TAS_DB_VERSION) {
    $tas_db_info->tables[$tasStatusByIdName]->dbDelta_result = dbDelta(sprintf($tasStatusByIdSql, $tasStatusByIdName));
  }

  if ($wpdb->get_var("show tables like '$tasStatusSearchName'") != $tasStatusSearchName ||
    $tas_db_info->version_installed != TAS_DB_VERSION) {
    $tas_db_info->tables[$tasStatusSearchName]->dbDelta_result = dbDelta(sprintf($tasStatusSearchSql, $tasStatusSearchName));
  }

  if ($wpdb->get_var("show tables like '$tasSearchName'") != $tasSearchName ||
    $tas_db_info->version_installed != TAS_DB_VERSION) {
    $tas_db_info->tables[$tasSearchName]->dbDelta_result = dbDelta(sprintf($tasSearchSql, $tasSearchName));
  }

  $tas_db_info->db_version = TAS_DB_VERSION;

  update_option('tas_db_info', json_encode($tas_db_info));
  wp_schedule_event(time() - 60000, 'hourly', 'tas_cron_action');
}

function tas_uninstall() {
  wp_clear_scheduled_hook('tas_cron_action');
  // TODO: Should we delete our tables?
}

function tas_cron() {
  global $smarty, $wpdb, $tasSearchName, $tasStatusSearchName;

  // Just for now let's disable cron on nslms.com
  //return;

  // TODO: We need to be very conscious of the 150 call limit on the twitter API  
  foreach ($wpdb->get_results("SELECT * FROM `$tasSearchName`") as $search) {
    if ($search->archive) {
      $nextPage = null;

      $latestStatusIdCached = $wpdb->get_var("SELECT max(status_id) FROM `$tasStatusSearchName` WHERE search_id = $search->id");

      do {
        $params = array();
        if ($nextPage != null) {
          // Add all of the existing params, plus the page number 
          foreach (explode('&', $nextPage) as $keyValuePair) {
            $splodedPair = explode('=', $keyValuePair);
            $params[$splodedPair[0]] = urldecode($splodedPair[1]);
          }
        } else {
          $params = array('q' => $search->search_term, 'rpp' => 100);
        }
        $response = TwitterAPIWrapper::search($params);

        foreach ($response->results as $status) {
          if (strval($status->id) != $latestStatusIdCached) {
            cacheStatus($status, $search->id);
          } else {
            $nextPage = null;
            break 2;
          }
        }

        $nextPage = str_replace('?', '', $response->next_page);
      } while ($nextPage != null);

      $wpdb->update($tasSearchName, array('last_successful_cron' => time()), array('id' => $search->id));
    }
  }

  // TODO: Implement the avatar updates.
  if(get_option('tas_twitter_auth') && have_twitter_oauth_token() && get_option('tas_update_avatars')) {
    //
  }

  update_option('tas_last_cron', time());
}

function tas_admin_menu() {
  // I don't see any real documentation for this method call, I'm following a tutorial found at;
  // http://codex.wordpress.org/Adding_Administration_Menus
  // This does seem to follow the model of add_menu_page and add_submenu_page as far as parameters though
  add_options_page('Twitter API Shortcode Options', 'Twitter API Shortcodes', 'manage_options', TAS_ADMIN_OPTIONS_ID, 'tas_admin_options');
}

function tas_admin_options() {
  global $smarty, $wpdb, $tasSearchName, $tasStatusSearchName, $tasStatusByIdName;
  if (!current_user_can('manage_options')) {
    wp_die(__('You know what you did wrong naughty boy! Go to your room!'));
  }

  // Handle the form post
  if (isset($_REQUEST['submit_val'])) {
    $smarty->append('messages', array('type' => 'info', 'message' => print_r($_REQUEST, true)));
    // First things first, let's make a security check.
    if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'tas_admin_nonce')) {
      unset($_REQUEST['submit_val']);
      $smarty->append('messages', array('type' => 'error', 'message' => 'You tried to do something that looked like a security no-no.  If you\'re malicious, stoppit! Otherwise, try again.'));
    }

    switch ($_REQUEST['submit_val']) {
      case 'Add':
        $result = $wpdb->insert($tasSearchName,
          array(
            'search_term' => $_REQUEST['terms'],
            'archive' => ($_REQUEST['archive'] == "true")
          )
        );
        if (!is_wp_error($result)) {
          unset($_REQUEST);
        }
        break;
      case 'Edit':
        $search_id = $_REQUEST['search'][0];
        $wpdb->query($wpdb->prepare("UPDATE `$tasSearchName` SET search_term = %s, archive = %d WHERE id = %d", $_REQUEST['terms'], $_REQUEST['archive'] == 'true' ? 1 : 0, $search_id));
        break;
      case "Update Options":
        update_option('tas_twitter_auth', $_REQUEST['authenticate_with_twitter'] == 'on');
        update_option('tas_update_avatars', $_REQUEST['update_avatars'] == 'on');
        update_option('tas_use_auth', $_REQUEST['use_auth_for_tags'] == 'on');
        break;
      case "Apply":
        if (isset($_REQUEST['search']) && is_array($_REQUEST['search'])) {
          $action = $_REQUEST['search-action'] == -1 ? $_REQUEST['search-action2'] : $_REQUEST['search-action'];
          switch ($action) {
            case 'archive':
              $sql = $wpdb->prepare("UPDATE `$tasSearchName` SET archive = 1 WHERE id IN (%s)", implode(',', $_REQUEST['search']));
              $wpdb->query($sql);
              break;
            case 'dearchive':
              $sql = $wpdb->prepare("UPDATE `$tasSearchName` SET archive = 0 WHERE id IN (%s)", implode(',', $_REQUEST['search']));
              $wpdb->query($sql);
              break;
            case 'delete':
              $search_ids = implode(',', $_REQUEST['search']);
              @mysql_query("BEGIN", $wpdb->dbh);
              $wpdb->query(sprintf("DELETE FROM `$tasStatusByIdName` WHERE id IN (SELECT status_id FROM `$tasStatusSearchName` WHERE search_id in (%s))", $search_ids));
              $wpdb->query(sprintf("DELETE FROM `$tasStatusSearchName` WHERE search_id in (%s)", $search_ids));
              $wpdb->query(sprintf("DELETE FROM `$tasSearchName` WHERE id in (%s)", $search_ids));

              if (!empty($wpdb->last_error)) {
                @mysql_query("ROLLBACK", $wpdb->dbh);
              } else {
                @mysql_query("COMMIT", $wpdb->dbh);
              }
              break;
          }
        }
        break;
      case "OAuthGw":
        if (isset($_REQUEST['key'])) {
          update_option('tas_twitter_auth', true);
          update_option('tas_oauth_gw_key', $_REQUEST['key']);
          /*// TODO: Check to see if the group exists, if not create it!
          $createListResponse = TwitterAPIWrapper::createUserList('tasforwp author list',
            sprintf('A list of authors of tweets cached by @tasforwp at %s', get_bloginfo('wpurl')));
          update_option('tas_twitter_author_list_id', $createListResponse->id);

          $addKhanToListResponse = TwitterAPIWrapper::addAuthorToList($createListResponse->id, 28218649);*/          
        }
        break;
      case 'Run Cron Now':
        tas_cron();
        break;
      default:
        break;
    }
  }

  // Grab the stuff to complete the form..
  $smarty->assign('twitter_auth', get_option('tas_twitter_auth', false));

  $smarty->assign('have_twitter_auth_token', have_twitter_oauth_token());

  $lastInstalled = get_option('tas_last_installed');
  $smarty->assign('last_installed', $lastInstalled);

  $updateAvatars = get_option('tas_update_avatars', false);
  $smarty->assign('update_avatars', $updateAvatars);

  $use_auth = get_option('tas_use_auth', false);
  $smarty->assign('use_auth_for_tags', $use_auth);

  $lastCronRun = get_option('tas_last_cron');
  $smarty->assign('last_cron', $lastCronRun);

  $dbVersion = json_decode(get_option('tas_db_info'));
  $smarty->assign('db_version', $dbVersion->db_version);

  $nonce = wp_create_nonce('tas_admin_nonce');
  $smarty->assign('nonce', $nonce);

  $searchesRows = $wpdb->get_results("SELECT * FROM `$tasSearchName`");
  foreach ($searchesRows as $idx => $search) {
    $count = $wpdb->get_var("SELECT count(id) FROM `$tasStatusSearchName` WHERE search_id = $search->id");
    $search->archivedStatuses = $count;
    $search->search_term = $search->search_term;
  }
  $smarty->assign('searches', $searchesRows);

  $smarty->assign('blog_url', get_bloginfo('wpurl'));

  /*try {
    $twitterApi = new EpiTwitter(TWITTER_CONSUMER_KEY, TWITTER_CONSUMER_SECRET);
    $smarty->assign('twitter_auth_url',
      $twitterApi->getAuthorizeUrl(null,
        array('oauth_callback' =>
        sprintf("%swp-admin/options-general.php?page=%s", get_bloginfo('wpurl'), TAS_ADMIN_OPTIONS_ID)
        )
      )
    );
  } catch (Exception $e) {
    print_r($e);
  }*/

  $smarty->assign('twitter_auth_url', TwitterAPIWrapper::getAuthUri($nonce));

  print $smarty->fetch('admin-options.tpl');
}

function tas_wp_head() {
  // TODO: We're dynamically loading the template, but not the CSS, need to look for an alternative css here as well,
  // or simply ignore ours if a non standard template is used.
  $format = <<<EOF
<style type="text/css" media="screen">
  @import url("%s/wp-content/plugins/twitter-api-shortcodes/css/twitter-api-shortcodes.css");  
</style>
EOF;
  printf($format, get_bloginfo('wpurl'));
}

function tas_admin_print_scripts() {
  wp_enqueue_script('jquery');
  wp_enqueue_script('jquery-ui-core');
  wp_enqueue_script('jquery-ui-dialog');
}

function tas_admin_head() {
  // Note: I'm gobsmacked that Wordpress includes the jQuery js files, but no theme.  For now, we're including the
  // theme CSS files for the "smoothness" theme, cause it fits the best.  Wierd though!
  if ($_REQUEST['page'] == TAS_ADMIN_OPTIONS_ID) {
    $format = <<<EOF
<script type="text/javascript" src="%s/wp-content/plugins/twitter-api-shortcodes/js/admin.js"></script>
<style type="text/css" media="screen">
  @import url("%s/wp-content/plugins/twitter-api-shortcodes/css/jqueryui/smoothness/jquery-ui-1.8.4.custom.css");
  @import url("%s/wp-content/plugins/twitter-api-shortcodes/css/twitter-api-shortcodes-admin.css");
</style>
EOF;

    printf($format, get_bloginfo('wpurl'), get_bloginfo('wpurl'), get_bloginfo('wpurl'));
  }
}

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

/**
 * This takes in a json object of a status which came from either the search api or the status api and makes it
 * all standardized to the format returned by the status api.  Namely the search API doesn't include the user
 * data, and it's "source" property is html encoded.  Since it takes the json object in by reference, if you pass
 * your object in by reference you can ignore the return value.
 * @param  $jsonObj The status json object to normalize
 * @return The normalized json object
 */
function normalizeStatus(&$jsonObj) {
  // See the documentation about the return value for the search API at;
  // http://apiwiki.twitter.com/Twitter-Search-API-Method:-search
  // If the user data isn't available, we'll make another call to go grab it!
  if (!isset($jsonObj->user)) {
    /* Getting the user for each one using another call is a HUGE waste, lets try a better way.
    $twitterApi = new TwitterAPIWP;
    $jsonObj->user = jsonGenderBender($twitterApi->usersShow(array('screen_name' => $jsonObj->from_user)));*/

    $jsonObj->user = new stdClass();
    $jsonObj->user->id = $jsonObj->from_user_id;
    $jsonObj->user->screen_name = $jsonObj->from_user;
    $jsonObj->user->profile_image_url = $jsonObj->profile_image_url;
  }

  // Again, only the search option returns an html encoded source, so we take care of that here.
  $jsonObj->source = htmlspecialchars_decode($jsonObj->source);

  return $jsonObj;
}

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

function cacheStatus($statusJsonObjOrStr, $searchId = 0) {
  global $smarty, $wpdb, $tasStatusByIdName, $tasStatusSearchName;
  // Bail if we're not getting anything
  if(!$statusJsonObjOrStr) { return; }

  $statusObj = jsonGenderBender($statusJsonObjOrStr);
  $statusStr = jsonGenderBender($statusJsonObjOrStr, 'string');

  normalizeStatus(&$statusObj);

  // TODO: Assume that we are always getting new ones, or check here?
  $wpdb->insert($tasStatusByIdName,
    array(
      'id' => strval($statusObj->id_str),
      'author_id' => $statusObj->user->id,
      'avatar_url' => $statusObj->user->profile_image_url,
      'status_json' => $statusStr
    )
  );

  if ($searchId > 0) {
    $wpdb->insert($tasStatusSearchName,
      array(
        'status_id' => $statusObj->id,
        'search_id' => $searchId
      )
    );
  }
}

function have_twitter_oauth_token() {
  $tas_oauth_gw_key = get_option('tas_oauth_gw_key', false);
  $tas_twitter_oauth_token = get_option('tas_twitter_oauth_token', false);

  $have_twitter_auth_token = $tas_oauth_gw_key != '' | $tas_twitter_oauth_token != '';
  return $have_twitter_auth_token;
}

/*****************************************************************
 * Registration of all of the callbacks                          *
 *****************************************************************/

register_activation_hook(ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/twitter-api-shortcodes.php',
  'tas_install');
register_deactivation_hook(ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/twitter-api-shortcodes.php',
  'tas_uninstall');
add_shortcode('twitter_status_by_id', 'twitter_status_by_id_func');
add_shortcode('twitter_search', 'twitter_search_func');
add_action('wp_head', 'tas_wp_head');
add_action('admin_print_scripts', 'tas_admin_print_scripts');
add_action('admin_head', 'tas_admin_head');
add_action('admin_menu', 'tas_admin_menu');
add_action('tas_cron_action', 'tas_cron');
add_action('init', 'tas_add_tinymce_buttons_action');