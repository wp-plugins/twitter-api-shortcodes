<?php
class TasForWPUnitTests extends WPTestCase {
  public static function print_wp_die($message)
  {
    print $message;
  }

  public static function print_wp_die_hook($function)
  {
    return "TasForWPUnitTests::print_wp_die";
  }

  private function create_admin_and_login()
  {
    global $current_user;

    // Allow this user to access the page
    $id = $this->_make_user();
    $current_user = new WP_User($id);
  }

  private function logout()
  {
    global $current_user;
    unset($current_user);
  }

  private function add_search($term, $archive)
  {
    global $wpdb;
    $wpdb->insert(TasForWp::$SearchTableName,
      array(
        'search_term' => $term,
        'archive' => $archive,
        'last_successful_cron' => 0
      )
    );
  }

  function setUp()
  {
    $this->create_admin_and_login();
    $_REQUEST['_wpnonce'] = wp_create_nonce('tas_admin_nonce');
    $_REQUEST['page'] = TAS_ADMIN_OPTIONS_ID;
  }

  function tearDown()
  {
    global $wpdb;
    $this->logout();
    unset($_REQUEST);
    $wpdb->query("TRUNCATE TABLE ".TasForWp::$SearchTableName);
    $wpdb->query("TRUNCATE TABLE ".TasForWp::$StatusSearchTableName);
    $wpdb->query("TRUNCATE TABLE ".TasForWp::$StatusByIdTableName);
  }

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
    $this->add_search("#ff", true);
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
      DIR_TESTDATA.'/twitter-api-shortcodes/ff-search-100-results-page1.json',
      DIR_TESTDATA.'/twitter-api-shortcodes/ff-search-100-results-page2.json'
    );

    $stub->expects($this->any())
      ->method('search')
      ->will($this->onConsecutiveCalls(
               jsonGenderBender(file_get_contents($responseAry[0])),
               jsonGenderBender(file_get_contents($responseAry[1])),
               jsonGenderBender(file_get_contents($responseAry[1]))
             ));

    $instance = TasForWp::getInstance();
    $instance->tapi = $stub;
    $this->add_search("doesn't matter", true);
    $old_search = new TwitterSearch(1, $wpdb, $stub);
    $old_search->fetchAndCacheLatest();
    $status_count = $wpdb->get_var("SELECT count(*) FROM `".TasForWp::$StatusByIdTableName."`");
    $this->assertTrue($status_count == 200);

    TasForWp::tas_cron();
    $status_count = $wpdb->get_var("SELECT count(*) FROM `".TasForWp::$StatusByIdTableName."`");
    $search_status_count = $wpdb->get_var("SELECT count(*) FROM `".TasForWp::$StatusByIdTableName."`");
    $this->assertTrue($status_count == 200);
    $this->assertTrue($search_status_count == $status_count);
  }

  function test_admin_scripts_returns_script_for_admin_page()
  {
    $this->assertFalse(wp_script_is('tas_admin_script'));
    TasForWp::tas_admin_print_scripts();
    $this->assertTrue(wp_script_is('tas_admin_script'));
    wp_dequeue_script('tas_admin_script');
  }

  function test_admin_scripts_empty_unless_admin_page()
  {
    $_REQUEST['page'] = '';
    $this->assertFalse(wp_script_is('tas_admin_script'));
    TasForWp::tas_admin_print_scripts();
    $this->assertFalse(wp_script_is('tas_admin_script'));
  }

  function test_admin_styles_returns_style_for_admin_page()
  {
    $this->assertFalse(wp_style_is('tas_admin_jquery_styles'));
    $this->assertFalse(wp_style_is('tas_admin_styles'));
    TasForWp::tas_admin_print_styles();
    $this->assertTrue(wp_style_is('tas_admin_jquery_styles'));
    $this->assertTrue(wp_style_is('tas_admin_styles'));
    wp_dequeue_style('tas_admin_jquery_styles');
    wp_dequeue_style('tas_admin_styles');
  }

  function test_admin_styles_empty_unless_admin_page()
  {
    $_REQUEST['page'] = '';
    $this->assertFalse(wp_style_is('tas_admin_jquery_styles'));
    $this->assertFalse(wp_style_is('tas_admin_styles'));
    TasForWp::tas_admin_print_styles();
    $this->assertFalse(wp_style_is('tas_admin_jquery_styles'));
    $this->assertFalse(wp_style_is('tas_admin_styles'));
  }

  function test_print_styles()
  {
    $this->assertFalse(wp_style_is('tas_styles'));
    TasForWp::tas_wp_print_styles();
    $this->assertTrue(wp_style_is('tas_styles'));
    wp_dequeue_style('tas_styles');
  }

  function test_status_by_id_shortcode_not_cached_default_template()
  {
    $stub = $this->getMock('TwitterAPIWrapper');

    $responseAry = array(
      DIR_TESTDATA.'/twitter-api-shortcodes/58262063881007105.json'
    );

    $stub->expects($this->once())
      ->method('getStatuses')
      ->with($this->equalTo('58262063881007105'))
      ->will($this->returnValue(
               jsonGenderBender(file_get_contents($responseAry[0]))
             ));

    $instance = TasForWp::getInstance();
    $instance->tapi = $stub;

    $formattedVal = TasForWp::twitter_status_by_id_func(array('id' => '58262063881007105'));
    $constraint = $this->stringContains('58262063881007105');
    $this->assertThat($formattedVal, $constraint);
  }

  function test_status_by_id_shortcode_cached_default_template()
  {
    global $wpdb;
    $stub = $this->getMock('TwitterAPIWrapper');

    $responseAry = array(
      DIR_TESTDATA.'/twitter-api-shortcodes/58262063881007105.json'
    );

    $stub->expects($this->once())
      ->method('getStatuses')
      ->with($this->equalTo('58262063881007105'))
      ->will($this->returnValue(
               jsonGenderBender(file_get_contents($responseAry[0]))
             ));

    $status = new TwitterStatus($wpdb, $stub);
    $status->get_by_id(58262063881007105);
    $status->cacheToDb();

    $stub = $this->getMock('TwitterAPIWrapper');

    $stub->expects($this->never())
      ->method('getStatuses');

    $instance = TasForWp::getInstance();
    $instance->tapi = $stub;

    $formattedVal = TasForWp::twitter_status_by_id_func(array('id' => '58262063881007105'));
    $constraint = $this->stringContains('58262063881007105');
    $this->assertThat($formattedVal, $constraint);
  }

  function test_tas_admin_options_denied_without_manage_options()
  {
    wp_set_current_user(0);
    add_filter('wp_die_handler', array('TasForWPUnitTests','print_wp_die_hook'));
    ob_start();
    TasForWp::tas_admin_options();
    $result = ob_get_contents();
    ob_end_clean();
    $constraint = $this->stringContains('You know what you did wrong naughty boy! Go to your room!');
    $this->assertThat($result, $constraint, $result);
    remove_filter('wp_die_handler', array('TasForWPUnitTests','print_wp_die_hook'));
  }

  function test_tas_admin_options_no_action_without_nonce()
  {
    $_REQUEST['submit_val'] = "Add";
    $_REQUEST['foo'] = "bar";
    $_REQUEST['_wpnonce'] = null;
    ob_start();
    TasForWp::tas_admin_options();
    ob_end_clean();
    $this->assertTrue(!isset($_REQUEST['submit_val']));
    $this->assertTrue($_REQUEST['foo'] == "bar");
  }

  function test_tas_admin_options_set_options()
  {
    $_REQUEST['submit_val'] = "Update Options";
    $_REQUEST['authenticate_with_twitter'] = true;
    $_REQUEST['update_avatars'] = true;
    $_REQUEST['use_auth_for_tags'] = true;

    update_option('tas_twitter_auth', false);
    update_option('tas_update_avatars', false);
    update_option('tas_use_auth', false);

    ob_start();
    TasForWp::tas_admin_options();
    ob_end_clean();
    $this->assertTrue((boolean)get_option('tas_twitter_auth', false));
    $this->assertTrue((boolean)get_option('tas_update_avatars', false));
    $this->assertTrue((boolean)get_option('tas_use_auth', false));
    unset($current_user);
    $this->logout();
  }

  function test_tas_admin_options_bulk_update_archive_for_one_search()
  {
    global $wpdb;
    $this->add_search('#ff', 0);
    $_REQUEST['submit_val'] = 'Apply';
    $_REQUEST['search'] = array(1);
    $_REQUEST['search-action'] = 'archive';

    ob_start();
    TasForWp::tas_admin_options();
    ob_end_clean();

    $search = new TwitterSearch(1, $wpdb);
    $this->assertEquals("1", $search->archive);
  }

  function test_tas_admin_options_bulk_update_dearchive_for_one_search()
  {
    global $wpdb;
    $this->add_search('#ff', 1);
    $_REQUEST['submit_val'] = 'Apply';
    $_REQUEST['search'] = array(1);
    $_REQUEST['search-action'] = 'dearchive';

    ob_start();
    TasForWp::tas_admin_options();
    ob_end_clean();

    $search = new TwitterSearch(1, $wpdb);
    $this->assertEquals("0", $search->archive);
  }

  function test_tas_admin_options_bulk_update_delete_for_one_search()
  {
    global $wpdb;
    $this->add_search('#ff', 1);
    $_REQUEST['submit_val'] = 'Apply';
    $_REQUEST['search'] = array(1);
    $_REQUEST['search-action'] = 'delete';

    ob_start();
    TasForWp::tas_admin_options();
    ob_end_clean();

    $this->assertTrue(count(TwitterSearch::getSearches($wpdb,null)) == 0);
  }

  function test_tas_admin_options_bulk_update_archive_for_many_searches()
  {
    global $wpdb;
    $this->add_search('#ff', 0);
    $this->add_search('#two', 0);
    $_REQUEST['submit_val'] = 'Apply';
    $_REQUEST['search'] = array(1, 2);
    $_REQUEST['search-action'] = 'archive';

    ob_start();
    TasForWp::tas_admin_options();
    ob_end_clean();

    $search1 = new TwitterSearch(1, $wpdb);
    $search2 = new TwitterSearch(2, $wpdb);
    $this->assertEquals("1", $search1->archive);
    $this->assertEquals("1", $search2->archive);
  }

  function test_tas_admin_options_bulk_update_dearchive_for_many_searches()
  {
    global $wpdb;
    $this->add_search('#ff', 1);
    $this->add_search('#two', 1);
    $_REQUEST['submit_val'] = 'Apply';
    $_REQUEST['search'] = array(1, 2);
    $_REQUEST['search-action'] = 'dearchive';

    ob_start();
    TasForWp::tas_admin_options();
    ob_end_clean();

    $search1 = new TwitterSearch(1, $wpdb);
    $search2 = new TwitterSearch(2, $wpdb);
    $this->assertEquals("0", $search1->archive);
    $this->assertEquals("0", $search2->archive);
  }

  function test_tas_admin_options_bulk_update_delete_for_many_searches()
  {
    global $wpdb;
    $this->add_search('#ff', 1);
    $this->add_search('#two', 1);
    $_REQUEST['submit_val'] = 'Apply';
    $_REQUEST['search'] = array(1,2);
    $_REQUEST['search-action'] = 'delete';

    ob_start();
    TasForWp::tas_admin_options();
    ob_end_clean();

    $this->assertTrue(count(TwitterSearch::getSearches($wpdb,null)) == 0);
  }

  function test_tas_admin_options_add_search()
  {
    global $wpdb;

    $terms = "#ff";
    $archive = false;

    $_REQUEST['submit_val'] = "Add";
    $_REQUEST['terms'] = $terms;
    $_REQUEST['archive'] = $archive;

    ob_start();
    TasForWp::tas_admin_options();
    ob_end_clean();

    $rows = $wpdb->get_results("SELECT * FROM " . TasForWp::$SearchTableName);
    $this->assertTrue(count($rows) == 1, "Expected one row in the search table, but got ". count($rows));
    $this->assertTrue($rows[0]->search_term == $terms);
    $this->assertTrue($rows[0]->archive == $archive);
  }

  function test_tas_admin_options_edit_search()
  {
    global $wpdb;

    $this->add_search("#ff", true);

    $_REQUEST['search'] = array(1);
    $_REQUEST['submit_val'] = "Edit";
    $_REQUEST['terms'] = $terms = '#different';
    $_REQUEST['archive'] = $archive = false;

    ob_start();
    TasForWp::tas_admin_options();
    ob_end_clean();
    $rows = $wpdb->get_results("SELECT * FROM " . TasForWp::$SearchTableName);
    $this->assertTrue(count($rows) == 1, "Expected one row in the search table, but got ". count($rows));
    $this->assertTrue($rows[0]->search_term == $terms);
    $this->assertTrue($rows[0]->archive == $archive);
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
