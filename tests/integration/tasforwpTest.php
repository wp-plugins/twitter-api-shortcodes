<?php
require_once('common.inc');
require_once('functions.php');
require_once('tasforwp.class.inc.php');
require_once('PHPUnit/Autoload.php');

class TasForWPTests extends PHPUnit_Framework_TestCase {
    public function testTasInstall() {
      TasForWp::tas_install();
      assert(json_decode(get_option('tas_db_info'))->db_version == TAS_DB_VERSION);
    }
}