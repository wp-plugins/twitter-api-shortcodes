<?php
class TasForWPUnitTests extends WPTestCase {
  function test_tas_install() {
    global $wpdb;
    TasForWp::StaticInit($wpdb);
    TasForWp::tas_install();
    $option_db_version = json_decode(get_option('tas_db_info'))->db_version;
    $this->assertTrue($option_db_version == TAS_DB_VERSION,
      sprintf("TAS_DB_VERSION(%s) != %s", TAS_DB_VERSION, $option_db_version));
    $this->assertTrue(wp_get_schedule(TasForWp::$cron_hook) == "hourly",
      sprintf("TAS Cron not scheduled wp_get_schedule == %s", wp_get_schedule(TasForWp::$cron_hook)));
    $this->assertTrue(time() - get_option('tas_last_installed') < 30,
      sprintf("tas_last_installed option is more than 30 seconds in the past - %s", get_option('tas_last_installed')));
  }

  function test_cron() {
    // TODO: Here it is..
  }

  function test_tas_uninstall() {
    $this->assertTrue(wp_get_schedule(TasForWp::$cron_hook) == "hourly",
      sprintf("TAS Cron not scheduled wp_get_schedule == %s", wp_get_schedule(TasForWp::$cron_hook)));
    TasForWp::tas_uninstall();
    $this->assertEmpty(wp_get_schedule(TasForWp::$cron_hook), "Cron hook still registered");
  }

  public function testHaveTwitterOauth_token() {
    # When neither option is set, the answer is false
    $this->assertFalse(TasForWp::have_twitter_oauth_token(), "Neither option set, but function returned true");

    # When just tas_oauth_gw_key is set the answer is true
    update_option('tas_oauth_gw_key', 'ABC123');
    $this->assertTrue(TasForWp::have_twitter_oauth_token(), "tas_oauth_gw == ABC123, but function returned false");

    # When both are set, the answer is true
    update_option('tas_twitter_oauth_token', 'ABC123');
    $this->assertTrue(TasForWp::have_twitter_oauth_token(), "tas_oauth_gw == ABC123 && tas_twitter_oauth_token == ABC123");
  }
}
