<?php

require_once('functions.php');

function register_activation_hook($path, $callbackName) {}
function register_deactivation_hook($path, $callbackName) {}

require_once('MockPress/mockpress.php');
require_once('tasforwp.class.inc.php');

require_once('PHPUnit/Framework.php');

class TasForWPTests extends PHPUnit_Framework_TestCase {
    public function testTasInstall() {
        $wpdb = $this->getMock('wpdb', array('get_var'));

        $wpdb->expects($this->once())
            ->method('get_var');
        TasForWp::StaticInit($wpdb);
        TasForWp::tas_install();
    }
}