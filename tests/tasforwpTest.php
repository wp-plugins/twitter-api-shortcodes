<?php
class TasForWPUnitTests extends WPTestCase {
  function test_tas_install() {
    TasForWp::tas_install();
    $option_db_version = json_decode(get_option('tas_db_info'))->db_version;
    $this->assertTrue($option_db_version == TAS_DB_VERSION,
      sprintf("TAS_DB_VERSION(%s) != %s", TAS_DB_VERSION, $option_db_version));
    $this->assertTrue(wp_get_schedule(TasForWp::$cron_hook) == "hourly",
      sprintf("TAS Cron not scheduled wp_get_schedule == %s", wp_get_schedule(TasForWp::$cron_hook)));
    $this->assertTrue(time() - get_option('tas_last_installed') < 30,
      sprintf("tas_last_installed option is more than 30 seconds in the past - %s", get_option('tas_last_installed')));
  }

  function test_cron_sets_option() {
    TasForWp::tas_cron();
    $this->assertTrue(get_option('tas_last_cron') - time() < 5);
  }

  function test_cron_gets_statuses_for_one_search() {
    global $wpdb;

    $stub = $this->getMock('TwitterAPIWrapper');

    $responseAry = array(
      DIR_TESTDATA.'/twitter-api-shortcodes/ff-search-100-results-page1.json',
      DIR_TESTDATA.'/twitter-api-shortcodes/ff-search-100-results-page2.json'
    );

    $stub->expects($this->any())
      ->method('search')
      ->will($this->onConsecutiveCalls(
               jsonGenderBender(file_get_contents($responseAry[0])),
               jsonGenderBender(file_get_contents($responseAry[1]))
             ));

    $instance = TasForWp::getInstance();
    $instance->tapi = $stub;

    $this->assertTrue($wpdb->get_var("SELECT count(*) FROM `".TasForWp::$SearchTableName."`") == 0);
    $this->assertTrue($wpdb->get_var("SELECT count(*) FROM `".TasForWp::$StatusByIdTableName."`") == 0);
    $this->assertTrue($wpdb->get_var("SELECT count(*) FROM `".TasForWp::$StatusSearchTableName."`") == 0);
    $wpdb->insert(TasForWp::$SearchTableName,
      array(
        'search_term' => "#ff",
        'archive' => true
      )
    );
    $this->assertTrue($wpdb->get_var("SELECT count(*) FROM `".TasForWp::$SearchTableName."`") == 1);
    TasForWp::tas_cron();
    $status_count = $wpdb->get_var("SELECT count(*) FROM `".TasForWp::$StatusByIdTableName."`");
    $search_status_count = $wpdb->get_var("SELECT count(*) FROM `".TasForWp::$StatusByIdTableName."`");
    $this->assertTrue($status_count == 200);
    $this->assertTrue($search_status_count == $status_count);
  }

  function test_cron_nothing_new_in_search()
  {
    global $wpdb;

    $stub = $this->getMock('TwitterAPIWrapper');

    $responseAry = array(
      DIR_TESTDATA.'/twitter-api-shortcodes/ff-search-100-results-page2.json'
    );

    $stub->expects($this->once())
      ->method('search')
      ->will($this->returnValue(
               jsonGenderBender(file_get_contents($responseAry[0]))
             ));

    $instance = TasForWp::getInstance();
    $instance->tapi = $stub;

    TasForWp::tas_cron();
    $status_count = $wpdb->get_var("SELECT count(*) FROM `".TasForWp::$StatusByIdTableName."`");
    $search_status_count = $wpdb->get_var("SELECT count(*) FROM `".TasForWp::$StatusByIdTableName."`");
    $this->assertTrue($status_count == 200);
    $this->assertTrue($search_status_count == $status_count);

    $wpdb->query("DELETE FROM ".TasForWp::$SearchTableName);
    $wpdb->query("DELETE FROM ".TasForWp::$StatusByIdTableName);
    $wpdb->query("DELETE FROM ".TasForWp::$StatusSearchTableName);
  }

  function test_tas_uninstall() {
    $this->assertTrue(wp_get_schedule(TasForWp::$cron_hook) == "hourly",
      sprintf("TAS Cron not scheduled wp_get_schedule == %s", wp_get_schedule(TasForWp::$cron_hook)));
    TasForWp::tas_uninstall();
    $this->assertEmpty(wp_get_schedule(TasForWp::$cron_hook), "Cron hook still registered");
  }

  /*function testHaveTwitterOauth_token() {
    # When neither option is set, the answer is false
    $this->assertFalse(TasForWp::have_twitter_oauth_token(), "Neither option set, but function returned true");

    # When just tas_oauth_gw_key is set the answer is true
    update_option('tas_oauth_gw_key', 'ABC123');
    $this->assertTrue(TasForWp::have_twitter_oauth_token(), "tas_oauth_gw == ABC123, but function returned false");

    # When both are set, the answer is true
    update_option('tas_twitter_oauth_token', 'ABC123');
    $this->assertTrue(TasForWp::have_twitter_oauth_token(), "tas_oauth_gw == ABC123 && tas_twitter_oauth_token == ABC123");
  }*/
}
