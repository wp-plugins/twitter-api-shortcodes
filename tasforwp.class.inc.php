<?php
define(TAS_VERSION, '0.0.3Alpha');
define(TAS_DB_VERSION, '0.0.3');
define(TAS_ADMIN_OPTIONS_ID, '83a70cd3-3f32-456d-980d-309169c26ccf');

class TasForWp
{
  public static $_wpdb;
  public static $StatusByIdTableName;
  public static $StatusSearchTableName;
  public static $SearchTableName;

  public static function StaticInit($wpdb)
  {
    TasForWp::$_wpdb                  = $wpdb;
    TasForWp::$StatusByIdTableName    = TasForWp::$_wpdb->prefix . 'tas_status_by_id';
    TasForWp::$StatusSearchTableName  = TasForWp::$_wpdb->prefix . 'tas_status_search';
    TasForWp::$SearchTableName        = TasForWp::$_wpdb->prefix . 'tas_search';
  }

  public static function tas_install()
  {
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
    wp_schedule_event(time() - 60000, 'hourly', 'tas_cron_action');
  }
} TasForWp::StaticInit($wpdb); // Simulate a static constructor