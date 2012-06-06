<?php
class TwitterStatus {
  private $_wpdb;
  private $tapi;

  public $jsonObj;
  public $jsonStr;

  public function __get($name)
  {
    if(method_exists($this, ($method = 'get_'.$name)))
    {
      return $this->$method();
    } else return $this->jsonObj->$name;
  }

  public function __set($name, $value)
  {
    $this->jsonObj->$name = $value;
  }

  private function get_created_at_ts()
  {
    return strtotime($this->jsonObj->created_at);
  }

  private function get_created_at_wp()
  {
    return date(get_option('date_format'), $this->created_at_ts) . ' ' . date(get_option('time_format'), $this->created_at_ts);
  }

  public function __construct($wpdb, $tapi=null)
  {
    $this->_wpdb  = $wpdb;
    $this->tapi   = isset($tapi) ? $tapi : new TwitterAPIWrapper();
  }

  public function load_json($jsonObjOrStr)
  {
    $this->jsonObj = jsonGenderBender($jsonObjOrStr);
    $this->normalizeStatus();
    $this->jsonStr = jsonGenderBender($this->jsonObj, 'string');    
  }

  public function get_by_id($status_id)
  {
    $status = $this->_wpdb->get_row(sprintf("SELECT * FROM `%s` WHERE id = %s", TasForWp::$StatusByIdTableName, $status_id));
    if(!$status) {
      $response = $this->tapi->getStatuses($status_id);

      $this->load_json($response);
      $this->process_entities();
      $this->cacheToDb();
    } else {
      $this->load_json($status->status_json);
    }
  }
  
  public function process_entities() {  	
  	if(isset($this->jsonObj->entities) && isset($this->jsonObj->entities->urls)) {
  		if(!is_array($this->jsonObj->images)) {
  			$this->jsonObj->images = array();
  		}  		
	  	foreach($this->jsonObj->entities->urls as $url) {
		  	$this->jsonObj->images[] = urlToImgUrl($url->expanded_url);
	  	}
  	}
  	$this->jsonStr = jsonGenderBender($this->jsonObj, 'string');
  }

  public function cacheToDb()
  {
    // TODO: Assume that we are always getting new ones, or check here?
    $this->_wpdb->insert(TasForWp::$StatusByIdTableName,
      array(
        'id' => strval($this->id_str),
        'author_id' => $this->user->id,
        'avatar_url' => $this->user->profile_image_url,
        'status_json' => $this->jsonStr
      )
    );
  }
  
  private function is_assoc_array($array) {
  	for (reset($array); is_int(key($array)); next($array));
  	$onlyIntKeys = is_null(key($array));
  	return !$onlyIntKeys;
  }
  
  /**
   * Thank you http://www.richardcastera.com/blog/php-convert-array-to-object-with-stdclass
   * @param array $array An associative array (hash) to be converted into an object
   * @return stdClass An object that has the same properties as the hash that was passed in.
   */
  private function convertArrayToObj($array) {
  	if(!is_array($array)) {
  		return $array;
  	}
  	
  	$object = new stdClass();
  	if(is_array($array) && count($array) > 0) {
  		foreach($array as $key => $val) {
  			$key = strtolower(trim($key));
  			if(!empty($key) && !empty($val)) {
  				if(is_array($val) && !$this->is_assoc_array($val)) {
  					$object->{$key} = $val;
  				} else {
  					$object->{$key} = $this->convertArrayToObj($val);
  				}
  			}
  		}
  		return $object;
  	} else {
  		return false;
  	}
  }

  /**
   * This takes in a json object of a status which came from either the search api or the status api and makes it
   * all standardized to the format returned by the status api.  Namely the search API doesn't include the user
   * data, and it's "source" property is html encoded.  Since it takes the json object in by reference, if you pass
   * your object in by reference you can ignore the return value.
   * @return stdClass The normalized json object
   */
  private function normalizeStatus() {
  	// Search data that comes in from the client is in the form of an array, not an object.
  	if(is_array($this->jsonObj)) {
  		$this->jsonObj = $this->convertArrayToObj($this->jsonObj);
  	}
  	
  	$this->jsonObj->data = new stdClass();
  	$this->jsonObj->data->entities = $this->jsonObj->entities;
  	if(isset($this->jsonObj->images)) {
  		$this->jsonObj->data->images = $this->jsonObj->images;
  	}
  	
    // See the documentation about the return value for the search API at;
    // http://apiwiki.twitter.com/Twitter-Search-API-Method:-search
    // If the user data isn't available, we'll make another call to go grab it!
    if (!isset($this->jsonObj->user)) {
      /* Getting the user for each one using another call is a HUGE waste, lets try a better way.
      $twitterApi = new TwitterAPIWP;
      $jsonObj->user = jsonGenderBender($twitterApi->usersShow(array('screen_name' => $jsonObj->from_user)));*/

      $this->jsonObj->user = new stdClass();
      $this->jsonObj->user->id = $this->jsonObj->from_user_id;
      $this->jsonObj->user->screen_name = $this->jsonObj->from_user;
      $this->jsonObj->user->profile_image_url = $this->jsonObj->profile_image_url;
    }

    // Again, only the search option returns an html encoded source, so we take care of that here.
    $this->jsonObj->source = htmlspecialchars_decode($this->jsonObj->source);
  }
}