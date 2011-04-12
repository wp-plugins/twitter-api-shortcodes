<?php
require_once(ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/libs/twitter.api.wp.class.inc.php');
require_once(ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/libs/smarty/Smarty.class.php');
//require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
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

// This is duplicated in twitter_api_shortcodes_install by necessity, if you change something here, change it there also
$tasStatusByIdName = $wpdb->prefix . 'tas_status_by_id';
$tasStatusSearchName = $wpdb->prefix . 'tas_status_search';
$tasSearchName = $wpdb->prefix . 'tas_search';

/*****************************************************************
 * Callbacks for the Wordpress API                               *
 *****************************************************************/

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

/*****************************************************************
 * Registration of all of the callbacks                          *
 *****************************************************************/

register_activation_hook(ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/libs/tasforwp.class.inc.php',
  TasForWp::$install_hook);
register_deactivation_hook(ABSPATH . 'wp-content/plugins/twitter-api-shortcodes/libs/tasforwp.class.inc.php',
  TasForWp::$uninstall_hook);
/*add_shortcode('twitter_status_by_id', 'twitter_status_by_id_func');
add_shortcode('twitter_search', 'twitter_search_func');
add_action('wp_head', 'tas_wp_head');
add_action('admin_print_scripts', 'tas_admin_print_scripts');
add_action('admin_head', 'tas_admin_head');
add_action('admin_menu', 'tas_admin_menu');
add_action('tas_cron_action', 'tas_cron');
add_action('init', 'tas_add_tinymce_buttons_action');*/