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

class TasForWp
{
  public static $_wpdb;
  public static $StatusByIdTableName;
  public static $StatusSearchTableName;
  public static $SearchTableName;

  public static $install_hook   = "TasForWP::tas_install";
  public static $uninstall_hook = "TasForWP::tas_uninstall";
  public static $cron_hook      = "TasForWP::tas_cron_action";
  public static $options        = array(
    "tas_last_installed", "tas_db_info", "tas_last_cron", "tas_twitter_auth", "tas_update_avatars"
  );

  public static function StaticInit($wpdb)
  {
    TasForWp::$_wpdb                  = $wpdb;
    TasForWp::$StatusByIdTableName    = TasForWp::$_wpdb->prefix . 'tas_status_by_id';
    TasForWp::$StatusSearchTableName  = TasForWp::$_wpdb->prefix . 'tas_status_search';
    TasForWp::$SearchTableName        = TasForWp::$_wpdb->prefix . 'tas_search';
  }

  // TODO: Need to add a schedule to update author avatar URL's on cached statuses.  Also need to add deactivation.
  public static function tas_install()
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

    if (TasForWp::$_wpdb->get_var("show tables like '" . TasForWp::$StatusByIdTableName . "'") != TasForWp::$StatusByIdTableName ||
        $tas_db_info->version_installed != TAS_DB_VERSION) {
      $tas_db_info->tables[TasForWp::$StatusByIdTableName]->dbDelta_result = dbDelta(sprintf($tasStatusByIdSql, TasForWp::$StatusByIdTableName));
    }

    if (TasForWp::$_wpdb->get_var("show tables like '" . TasForWp::$StatusSearchTableName . "'") != TasForWp::$StatusSearchTableName ||
        $tas_db_info->version_installed != TAS_DB_VERSION) {
      $tas_db_info->tables[TasForWp::$StatusSearchTableName]->dbDelta_result = dbDelta(sprintf($tasStatusSearchSql, TasForWp::$StatusSearchTableName));
    }

    if (TasForWp::$_wpdb->get_var("show tables like '" . TasForWp::$SearchTableName . "'") != TasForWp::$SearchTableName ||
        $tas_db_info->version_installed != TAS_DB_VERSION) {
      $tas_db_info->tables[TasForWp::$SearchTableName]->dbDelta_result = dbDelta(sprintf($tasSearchSql, TasForWp::$SearchTableName));
    }

    $tas_db_info->db_version = TAS_DB_VERSION;

    update_option('tas_db_info', json_encode($tas_db_info));
    wp_schedule_event(time() - 60000, 'hourly', TasForWp::$cron_hook);
  }

  public static function tas_uninstall() {
    wp_clear_scheduled_hook(TasForWp::$cron_hook);
    // TODO: Should we delete our tables?
  }

  public static function tas_cron() {
    // TODO: We need to be very conscious of the 150 call limit on the twitter API
    foreach (TasForWp::$_wpdb->get_results("SELECT * FROM `". TasForWp::$SearchTableName ."`") as $search) {
      if ($search->archive) {
        $nextPage = null;

        $latestStatusIdCached = TasForWp::$_wpdb->get_var("SELECT max(status_id) FROM `".TasForWp::$StatusByIdTableName."` WHERE search_id = $search->id");

        do {
          $params = array();
          if ($nextPage != null) {
            // Add all of the existing params, plus the page number
            foreach (explode('&', $nextPage) as $keyValuePair) {
              $splodedPair = explode('=', $keyValuePair);
              $params[$splodedPair[0]] = urldecode($splodedPair[1]);
            }
          } else {
            // TODO: Should/can we specify a larger rpp?
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

        TasForWp::$_wpdb->update(TasForWp::$SearchTableName, array('last_successful_cron' => time()), array('id' => $search->id));
      }
    }

    // TODO: Implement the avatar updates.
    if(get_option('tas_twitter_auth') && have_twitter_oauth_token() && get_option('tas_update_avatars')) {
      //
    }

    update_option('tas_last_cron', time());
  }

  // TODO: This should be private, but I have to test it externally... Hrrmn
  public static function have_twitter_oauth_token() {
    $tas_oauth_gw_key = get_option('tas_oauth_gw_key', '');
    $tas_twitter_oauth_token = get_option('tas_twitter_oauth_token', '');

    $have_twitter_auth_token = $tas_oauth_gw_key != '' | $tas_twitter_oauth_token != '';
    return (boolean)$have_twitter_auth_token;
  }
} TasForWp::StaticInit($wpdb); // Simulate a static constructor