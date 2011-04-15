<?php
$scriptFile = $_SERVER['SCRIPT_FILENAME'];
$wp_header = implode('/', array_diff(explode('/', $scriptFile), array_slice(explode('/', $scriptFile), -5)));

define('WP_USE_THEMES', false);
require_once($wp_header.'/wp-blog-header.php');

if($_REQUEST['search_id'])
{
  $search = new TwitterSearch($_REQUEST['search_id'], $wpdb, null);
  $search->limit = 5;
  print_r($search->getStatuses());
}