<?php
class FunctionsTest extends WPTestCase {
//class FunctionsTest extends PHPUnit_Framework_TestCase {
  protected $jsonStr = <<<EOF
{"menu": {
  "id": "file",
  "value": "File",
  "popup": {
    "menuitem": [
      {"value": "New", "onclick": "CreateNewDoc()"},
      {"value": "Open", "onclick": "OpenDoc()"},
      {"value": "Close", "onclick": "CloseDoc()"}
    ]
  }
}}
EOF;

  protected $jsonObj;

  public function setUp() {
    $this->jsonObj = json_decode($this->jsonStr);
  }

  public function testJsonGenderBenderReturnsObjectWhenProvidedString() {
    $obj = jsonGenderBender($this->jsonStr);
    $this->assertTrue($obj->menu->id == "file");
  }

  public function testJsonGenderBenderReturnsStringWhenProvidedObject() {
    $str = jsonGenderBender($this->jsonObj, 'string');
    $this->assertTrue($str == json_encode($this->jsonObj));
  }

  public function testJsonGenderBenderReturnsObjectWhenProvidedObject() {
    $obj = jsonGenderBender($this->jsonObj);
    $this->assertTrue($obj == $this->jsonObj);
  }
}
