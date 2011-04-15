<?php
class FunctionsTest extends WPTestCase {
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

  /*public function testStatusNormalizer() {
    $searchJson = file_get_contents(DIR_TESTDATA.'/twitter-api-shortcodes/one-search-result.json');

    $getJson = <<<EOF

EOF;

    $normSearchObj = jsonGenderBender($searchJson);
    $this->assertNull($normSearchObj->user);
    $this->assertNull($normSearchObj->created_at_ts);
    $this->assertTrue($normSearchObj->source == htmlspecialchars($normSearchObj->source,ENT_COMPAT,"ISO-8859-1",false));
    normalizeStatus($normSearchObj);
    $this->assertNotNull($normSearchObj->user);
    $this->assertNotNull($normSearchObj->created_at_ts);
    $this->assertTrue($normSearchObj->source != htmlspecialchars($normSearchObj->source,ENT_COMPAT,"ISO-8859-1",false));
  }*/
}
