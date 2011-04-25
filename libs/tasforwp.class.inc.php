<?php
class TasForWp
{
  private static $_instance;
  private function __construct() {}

  public static function getInstance() {
    global $wpdb;
    if(!isset(self::$_instance)) {
      self::$_instance = new TasForWp();
      self::$_instance->init($wpdb);
    }
    return self::$_instance;
  }

  private $_wpdb;
  private $smarty;
  public  $tapi;
  public static $StatusByIdTableName;
  public static $StatusSearchTableName;
  public static $SearchTableName;

  public static $install_hook             = "TasForWP::tas_install";
  public static $uninstall_hook           = "TasForWP::tas_uninstall";
  public static $cron_hook                = "TasForWP::tas_cron_action";
  public static $admin_menu_hook          = "TasForWp::tas_admin_menu";
  public static $wp_print_styles_hook     = "TasForWp::tas_wp_print_styles";
  public static $wp_print_scripts_hook    = "TasForWp::tas_wp_print_scripts";
  public static $admin_print_scripts_hook = "TasForWp::tas_admin_print_scripts";
  public static $admin_print_styles_hook  = "TasForWp::tas_admin_print_styles";
  public static $search_ajax_hook         = "TasForWp::tas_search_ajax";

  public static $status_by_id_shortcode   = "TasForWp::twitter_status_by_id_func";
  public static $search_shortcode         = "TasForWp::twitter_search_func";
  public static $options                  = array(
    "tas_last_installed", "tas_db_info", "tas_last_cron", "tas_twitter_auth", "tas_update_avatars"
  );

  public function init($wpdb, $tapi=null)
  {
    $this->_wpdb                      = $wpdb;
    TasForWp::$StatusByIdTableName    = $this->_wpdb->prefix . 'tas_status_by_id';
    TasForWp::$StatusSearchTableName  = $this->_wpdb->prefix . 'tas_status_search';
    TasForWp::$SearchTableName        = $this->_wpdb->prefix . 'tas_search';

    $this->tapi                   = isset($tapi) ? $tapi : new TwitterAPIWrapper();

    $this->smarty                 = new Smarty();
    $this->smarty->template_dir   = ABSPATH.'wp-content/plugins/twitter-api-shortcodes/templates/';
    $this->smarty->compile_dir    = ABSPATH.'wp-content/plugins/twitter-api-shortcodes/templates/templates_c/';
    $this->smarty->config_dir     = ABSPATH.'wp-content/plugins/twitter-api-shortcodes/templates/configs/';
    $this->smarty->cache_dir      = ABSPATH.'wp-content/plugins/twitter-api-shortcodes/templates/cache/';
  }

  public static function tas_admin_print_styles()
  {
    self::getInstance()->tas_admin_print_styles_impl();
  }

  private function tas_admin_print_styles_impl() {
    // Note: I'm gobsmacked that Wordpress includes the jQuery js files, but no theme.  For now, we're including the
    // theme CSS files for the "smoothness" theme, cause it fits the best.  Wierd though!
    if ($_REQUEST['page'] == TAS_ADMIN_OPTIONS_ID) {
      // jQuery Styles
      $jqUrl = WP_PLUGIN_URL . '/twitter-api-shortcodes/css/jqueryui/smoothness/jquery-ui-1.8.4.custom.css';
      $jqFile = WP_PLUGIN_DIR . '/twitter-api-shortcodes/css/jqueryui/smoothness/jquery-ui-1.8.4.custom.css';
      if(file_exists($jqFile)) {
        wp_register_style('tas_admin_jquery_styles', $jqUrl);
        wp_enqueue_style('tas_admin_jquery_styles');
      }

      // Admin Styles
      $admUrl = WP_PLUGIN_URL . '/twitter-api-shortcodes/css/twitter-api-shortcodes-admin.css';
      $admFile = WP_PLUGIN_DIR . '/twitter-api-shortcodes/css/twitter-api-shortcodes-admin.css';
      if(file_exists($admFile)) {
        wp_register_style('tas_admin_styles', $admUrl);
        wp_enqueue_style('tas_admin_styles');
      }
    }
  }

  public static function tas_admin_print_scripts()
  {
    self::getInstance()->tas_admin_print_scripts_impl();
  }

  private function tas_admin_print_scripts_impl() {
    if ($_REQUEST['page'] == TAS_ADMIN_OPTIONS_ID) {
      wp_enqueue_script('jquery');
      wp_enqueue_script('jquery-ui-core');
      wp_enqueue_script('jquery-ui-dialog');

      $url = WP_PLUGIN_URL . '/twitter-api-shortcodes/js/admin.js';
      $file = WP_PLUGIN_DIR . '/twitter-api-shortcodes/js/admin.js';
      if(file_exists($file)) {
        wp_register_script('tas_admin_script', $url);
        wp_enqueue_script('tas_admin_script');
      }
    }
  }

  public static function tas_wp_print_styles()
  {
    self::getInstance()->tas_wp_print_styles_impl();
  }

  private function tas_wp_print_styles_impl() {
    // TODO: We're dynamically loading the template, but not the CSS, need to look for an alternative css here as well,
    // or simply ignore ours if a non standard template is used.
    // Tweet Styles
    $url = WP_PLUGIN_URL . '/twitter-api-shortcodes/css/twitter-api-shortcodes.css';
    $file = WP_PLUGIN_DIR . '/twitter-api-shortcodes/css/twitter-api-shortcodes.css';
    if(file_exists($file)) {
      wp_register_style('tas_styles', $url);
      wp_enqueue_style('tas_styles');
    }
  }

  public static function tas_wp_print_scripts()
  {
    self::getInstance()->tas_wp_print_scripts_impl();
  }

  private function tas_wp_print_scripts_impl() {
    // TODO: We're dynamically loading the template, but not the CSS, need to look for an alternative css here as well,
    // or simply ignore ours if a non standard template is used.
    // Tweet Styles
    $url = WP_PLUGIN_URL . '/twitter-api-shortcodes/js/tas-search.js';
    $file = WP_PLUGIN_DIR . '/twitter-api-shortcodes/js/tas-search.js';
    if(file_exists($file)) {
      wp_register_script('tas_search_script', $url);
      wp_enqueue_script('tas_search_script');

      wp_localize_script('tas_search_script', 'tas_search_script', array('ajaxurl' => admin_url('admin-ajax.php')));
    }
  }

  // TODO: Need to add a schedule to update author avatar URL's on cached statuses.  Also need to add deactivation.
  public static function tas_install()
  {
    self::getInstance()->tas_install_impl();
  }

  private function tas_install_impl()
  {
    update_option('tas_last_installed', time());

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

    if ($this->_wpdb->get_var("show tables like '" . TasForWp::$StatusByIdTableName . "'") != TasForWp::$StatusByIdTableName ||
        $tas_db_info->version_installed != TAS_DB_VERSION) {
      $tas_db_info->tables[TasForWp::$StatusByIdTableName]->dbDelta_result = dbDelta(sprintf($tasStatusByIdSql, TasForWp::$StatusByIdTableName));
    }

    if ($this->_wpdb->get_var("show tables like '" . TasForWp::$StatusSearchTableName . "'") != TasForWp::$StatusSearchTableName ||
        $tas_db_info->version_installed != TAS_DB_VERSION) {
      $tas_db_info->tables[TasForWp::$StatusSearchTableName]->dbDelta_result = dbDelta(sprintf($tasStatusSearchSql, TasForWp::$StatusSearchTableName));
    }

    if ($this->_wpdb->get_var("show tables like '" . TasForWp::$SearchTableName . "'") != TasForWp::$SearchTableName ||
        $tas_db_info->version_installed != TAS_DB_VERSION) {
      $tas_db_info->tables[TasForWp::$SearchTableName]->dbDelta_result = dbDelta(sprintf($tasSearchSql, TasForWp::$SearchTableName));
    }

    $tas_db_info->db_version = TAS_DB_VERSION;

    update_option('tas_db_info', json_encode($tas_db_info));
    wp_schedule_event(time() - 60000, 'hourly', TasForWp::$cron_hook);
  }

  public static function tas_uninstall()
  {
    self::getInstance()->tas_uninstall_impl();
  }

  private function tas_uninstall_impl() {
    wp_clear_scheduled_hook(TasForWp::$cron_hook);
    // TODO: Should we delete our tables?
  }

  public static function tas_cron()
  {
    self::getInstance()->tas_cron_impl();
  }

  private function tas_cron_impl() {
    // TODO: We need to be very conscious of the 150 call limit on the twitter API
    foreach (TwitterSearch::getSearches($this->_wpdb, $this->tapi) as $search) {
      $search->fetchAndCacheLatest();
    }

    // TODO: Implement the avatar updates.
    if(get_option('tas_twitter_auth') && have_twitter_oauth_token() && get_option('tas_update_avatars')) {
      //
    }

    update_option('tas_last_cron', time());
  }

  public static function tas_search_ajax()
  {
    self::getInstance()->tas_search_ajax_impl();
  }

  private function tas_search_ajax_impl()
  {
    // TODO: There's some work to be done here, mostly making the TwitterSearch class better.
    $infoObj = (object)$_REQUEST['info_str'];

    $search = new TwitterSearch($infoObj->id, $this->_wpdb, null);
    $search->page = $infoObj->page == 'null' ? null : $infoObj->page;
    $search->max_status_id = $infoObj->max_status_id == 'null' ? null : $infoObj->max_status_id;
    $search->limit = $infoObj->limit;
    $search->paging = $infoObj->paging;

    $returnObject = new stdClass();
    $returnObject->statuses = '';

    $statusAry = $search->getStatuses();

    foreach($statusAry as $status)
    {
      $returnObject->statuses .= $this->formatStatus($status);
    }

    $infoObj->max_status_id = $statusAry[0]->id_str;
    if(!isset($search->page)) { $infoObj->page = 1; }

    if($search->older_statuses_available()) {
      $returnObject->next_link = $this->searchNextButton();
    }
    if(!$search->archive) {
      $returnObject->refresh_link = $this->searchRefreshButton();
    }
    $returnObject->info_obj = $infoObj;

    print strval(jsonGenderBender($returnObject, 'string'));
    exit;
  }

  public static function tas_admin_options()
  {
    self::getInstance()->tas_admin_options_impl();
  }

  private function tas_admin_options_impl() {
    if (!current_user_can('manage_options')) {
      wp_die(__('You know what you did wrong naughty boy! Go to your room!'));
    }

    // Handle the form post
    if (isset($_REQUEST['submit_val'])) {
      $this->smarty->append('messages', array('type' => 'info', 'message' => print_r($_REQUEST, true)));
      // First things first, let's make a security check.
      if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'tas_admin_nonce')) {
        unset($_REQUEST['submit_val']);
        $this->smarty->append('messages', array('type' => 'error', 'message' => 'You tried to do something that looked like a security no-no.  If you\'re malicious, stoppit! Otherwise, try again.'));
      }

      switch ($_REQUEST['submit_val']) {
        case 'Add':
          $result = $this->add_search($_REQUEST['terms'], $_REQUEST['archive']);
          if (!is_wp_error($result)) {
            unset($_REQUEST);
          }
          break;
        case 'Edit':
          $search_id = $_REQUEST['search'][0];
          $this->_wpdb->query(
            $this->_wpdb->prepare(
              "UPDATE `".TasForWp::$SearchTableName."` SET search_term = %s, archive = %d WHERE id = %d",
              $_REQUEST['terms'], $_REQUEST['archive'] == 'true' ? 1 : 0, $search_id));
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
                $sql = sprintf(
                  "UPDATE `".TasForWp::$SearchTableName."` SET archive = 1 WHERE id IN (%s)", implode(',', $_REQUEST['search']));
                $this->_wpdb->query($sql);
                break;
              case 'dearchive':
                $sql = sprintf(
                  "UPDATE `".TasForWp::$SearchTableName."` SET archive = 0 WHERE id IN (%s)", implode(',', $_REQUEST['search']));
                $this->_wpdb->query($sql);
                break;
              case 'delete':
                $search_ids = implode(',', $_REQUEST['search']);
                @mysql_query("BEGIN", $this->_wpdb->dbh);
                $this->_wpdb->query(sprintf("DELETE FROM `%s` WHERE id IN (SELECT status_id FROM `%s` WHERE search_id in (%s))", TasForWp::$StatusByIdTableName, TasForWp::$StatusSearchTableName, $search_ids));
                $this->_wpdb->query(sprintf("DELETE FROM `%s` WHERE search_id in (%s)", TasForWp::$StatusSearchTableName, $search_ids));
                $this->_wpdb->query(sprintf("DELETE FROM `%s` WHERE id in (%s)", TasForWp::$SearchTableName, $search_ids));

                if (!empty($this->_wpdb->last_error)) {
                  @mysql_query("ROLLBACK", $this->_wpdb->dbh);
                } else {
                  @mysql_query("COMMIT", $this->_wpdb->dbh);
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
          $this->tas_cron_impl();
          break;
        default:
          break;
      }
    }

    // Grab the stuff to complete the form..
    $this->smarty->assign('twitter_auth', get_option('tas_twitter_auth', false));

    $this->smarty->assign('have_twitter_auth_token', $this->have_twitter_oauth_token());

    $lastInstalled = get_option('tas_last_installed');
    $this->smarty->assign('last_installed', $lastInstalled);

    $updateAvatars = get_option('tas_update_avatars', false);
    $this->smarty->assign('update_avatars', $updateAvatars);

    $use_auth = get_option('tas_use_auth', false);
    $this->smarty->assign('use_auth_for_tags', $use_auth);

    $lastCronRun = get_option('tas_last_cron');
    $this->smarty->assign('last_cron', $lastCronRun);

    $dbVersion = json_decode(get_option('tas_db_info'));
    $this->smarty->assign('db_version', $dbVersion->db_version);

    $nonce = wp_create_nonce('tas_admin_nonce');
    $this->smarty->assign('nonce', $nonce);

    $searchesRows = $this->_wpdb->get_results("SELECT * FROM `".TasForWp::$SearchTableName."`");
    foreach ($searchesRows as $idx => $search) {
      $count = $this->_wpdb->get_var("SELECT count(id) FROM `".TasForWp::$StatusSearchTableName."` WHERE search_id = $search->id");
      $search->archivedStatuses = $count;
      $search->search_term = $search->search_term;
    }
    $this->smarty->assign('searches', $searchesRows);

    $this->smarty->assign('blog_url', get_bloginfo('wpurl'));

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

    $this->smarty->assign('twitter_auth_url', $this->tapi->getAuthUri($nonce));

    print $this->smarty->fetch('admin-options.tpl');
  }

  public static function tas_admin_menu() {
    // I don't see any real documentation for this method call, I'm following a tutorial found at;
    // http://codex.wordpress.org/Adding_Administration_Menus
    // This does seem to follow the model of add_menu_page and add_submenu_page as far as parameters though
    add_options_page('Twitter API Shortcode Options', 'Twitter API Shortcodes', 'manage_options', TAS_ADMIN_OPTIONS_ID, 'TasForWp::tas_admin_options');
  }

  public static function twitter_status_by_id_func($atts)
  {
    return self::getInstance()->twitter_status_by_id_func_impl($atts);
  }

  private function twitter_status_by_id_func_impl($atts) {
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

    $status = new TwitterStatus($this->_wpdb, $this->tapi);
    $status->get_by_id($id);

    return $this->formatStatus($status);

    /*$existingRecordQuery = sprintf('SELECT * FROM %s WHERE id = %s', TasForWp::$StatusByIdTableName, $id);
    $existingRecord = $this->_wpdb->get_row($existingRecordQuery);

    if (!$existingRecord) {
      $response = $this->tapi->getStatuses($id);

      $status = $response;
      $this->cacheStatus($response);
    } else {
      $status = json_decode($existingRecord->status_json);
      // TODO: Should I be doing this magic maybe in the formatStatus method?
      $status->user->profile_image_url = $existingRecord->avatar_url;
    }

    return $this->formatStatus($status);*/
  }

  public static function twitter_search_func($atts)
  {
    return self::getInstance()->twitter_search_func_impl($atts);
  }

  private function twitter_search_func_impl($atts) {
    // Initialize extracted vals from shortcode so that the IDE doesn't complain
    $id = '';
    $limit = 0;
    $paging = false;
    extract(
      shortcode_atts(
        array(
          'id'      => 0,
          'limit'   => 0,
          'paging'  => false
        ),
        $atts
      )
    );

    // Little bit of validation
    if (!$id) {
      return;
    }

    $search = new TwitterSearch($id, $this->_wpdb, $this->tapi);
    $search->limit = $limit;
    $search->paging = $paging;
    $output = $this->formatSearch($search);

    return $output;
  }

  public static function tas_add_tinymce_buttons($plugin_array) {
    $plugin_array['tas'] = WP_PLUGIN_URL . '/twitter-api-shortcodes/tinymce_plugin/editor_plugin.js';
    return $plugin_array;
  }

  public static function tas_add_tinymce_buttons_action() {
    // Don't bother doing this stuff if the current user lacks permissions
    if (!current_user_can('edit_posts') && !current_user_can('edit_pages'))
      return;

    // Add only in Rich Editor mode
    if (get_user_option('rich_editing') == 'true') {
      add_filter("mce_external_plugins", "TasForWp::tas_add_tinymce_buttons");
      add_filter('mce_buttons', 'TasForWp::tas_mce_buttons');
    }
  }

  public static function tas_mce_buttons($buttons) {
    array_push($buttons, '|', 'tas');
    return $buttons;
  }

  private function add_search($term, $archive) {
    $archive_bool = is_bool($archive) ? $archive : strtolower($archive) == 'true';
    return $this->_wpdb->insert(TasForWp::$SearchTableName,
      array(
        'search_term' => $term,
        'archive' => $archive_bool
      )
    );
  }

  private function have_twitter_oauth_token() {
    $tas_oauth_gw_key = get_option('tas_oauth_gw_key', '');
    $tas_twitter_oauth_token = get_option('tas_twitter_oauth_token', '');

    $have_twitter_auth_token = $tas_oauth_gw_key != '' | $tas_twitter_oauth_token != '';
    return (boolean)$have_twitter_auth_token;
  }

  private function formatStatus(TwitterStatus $status) {
    $this->smarty->assign('tweet', $status);

    $tweetTemplate = WP_PLUGIN_DIR.'/twitter-api-shortcodes/templates/tweet.tpl';
    $curTemplateTweet = TEMPLATEPATH.'/tweet.tpl';
    if(file_exists($curTemplateTweet)) {
      $tweetTemplate = $curTemplateTweet;
    }

    return $this->smarty->fetch($tweetTemplate);
  }

  public function formatSearch(TwitterSearch $search)
  {
    $thisDivGuid = sprintf("tas_search_%s", uniqid());
    $retVal = sprintf('<div class="tweet-list" id="%s">', $thisDivGuid);
    $statuses = $search->getStatuses();
    $max_status_id = $statuses[0]->id_str;
    if (is_array($statuses)) {
      foreach ($statuses as $status) {
        $retVal .= $this->formatStatus($status);
      }
    }
    if($search->paging) {
      $dataObj = new stdClass();
      $dataObj->id = $search->getId();
      $dataObj->max_status_id = $max_status_id;
      $dataObj->limit = $search->limit;
      $dataObj->div_guid = $thisDivGuid;
      $dataObj->paging = $search->paging;
      $dataJson = jsonGenderBender($dataObj, 'string');
      if($search->older_statuses_available())
      {
        //$retVal .= "<a href='' class='tas_search_next' data='" . $dataJson ."'>Older Tweets</a>";
        $retVal .= $this->searchNextButton($dataJson);
      }
      if(!$search->archive)
      {
        $retVal .= $this->searchRefreshButton($dataJson);
      }
    }
    $retVal .= '</div>';
    return $retVal;
  }

  private function searchNextButton($dataJson=null)
  {
    return sprintf("<input type='button' class='tas_search_next' data='%s' value='Older Tweets'/>", $dataJson);
  }

  private function searchRefreshButton($dataJson=null)
  {
    return sprintf("<input type='button' class='tas_search_refresh' data='%s' value='Refresh Search' />", $dataJson);
  }
} TasForWp::getInstance();