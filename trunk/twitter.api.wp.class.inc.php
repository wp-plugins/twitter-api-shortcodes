<?php
require_once(ABSPATH.'wp-content/plugins/twitter-api-shortcodes/libs/jmathai-twitter-async/EpiCurl.php');
require_once(ABSPATH.'wp-content/plugins/twitter-api-shortcodes/libs/jmathai-twitter-async/EpiOAuth.php');
require_once(ABSPATH.'wp-content/plugins/twitter-api-shortcodes/libs/jmathai-twitter-async/EpiTwitter.php');

// Support for WP 3.x
if(!class_exists('WP_Http')) {
  require_once(ABSPATH.WPINC.'/class-http.php');
}

// TODO: This is too tightly coupled to wordpress and this application.  Should take input params for the oauthgw key
// and probably use a more independent method for http posts 
class TwitterAPIWrapper {
  private static $tas_oauth_gw_uri = 'https://oathgw.appspot.com/'; //'http://localhost:8989/';
  private static $auth_mode; // One of "local_oath", "tas_oauth_gw"

  public static function getAuthUri($nonce = null) {
    // For now we're just going to return the oauth gw URI, but in the future we're going to have
    // to consider situations where people have provided their own app key & secret
    $uri = sprintf('%stas_auth/?blog_url=%s', TwitterAPIWrapper::$tas_oauth_gw_uri, urlencode(get_bloginfo('wpurl')));

    $tas_oauth_gw_key = get_option('tas_oauth_gw_key');

    if (!empty($tas_oauth_gw_key)) {
     $uri .= sprintf('&key=%s', $tas_oauth_gw_key);
    }
    if(isset($nonce)) {
      $uri .= sprintf('&_wpnonce=%s', $nonce);
    }
    return $uri;
  }

  /******************************************************************************************
   * Un authenticated methods
   ******************************************************************************************/

  public static function search($paramAry) {
    $twitterApi = new EpiTwitter();
    $response = $twitterApi->get_search($paramAry);

    return $response;
  }

  /******************************************************************************************
   * Hybrid (authed or unauthed) methods
   ******************************************************************************************/

  public static function getStatuses($id) {
    $twitterApi = new EpiTwitter();
    try {
      $method = "get_statusesShow{$id}";
      $response = $twitterApi->$method();
    } catch (/*EpiTwitterForbidden*/Exception $ex) {
      if (get_option('tas_twitter_auth', false) && get_option('tas_use_auth', false)) {
        // We're just gonna assume this is an auth error.
        $wp_http = new WP_Http;
        $response = $wp_http->request(
          sprintf('%stapi/statuses/show/', TwitterAPIWrapper::$tas_oauth_gw_uri),
          array('method' => 'POST', 'body' => array('id' => $id, 'key' => get_option('tas_oauth_gw_key')))
        );

        $jsonResponse = json_decode($response['body']);
        return $jsonResponse;
      }
    }

    return $response;
  }

  /******************************************************************************************
   * Authenticated methods
   ******************************************************************************************/

  public static function createUserList($name, $description, $mode='private') {
    $wp_http = new WP_Http;
    $response = $wp_http->request(
      sprintf('%stapi/list/create/', TwitterAPIWrapper::$tas_oauth_gw_uri),
      array('method' => 'POST', 'body' => array('name' => $name, 'mode' => 'private', 'description' => $description, 'mode' => $mode, 'key' => get_option('tas_oauth_gw_key')))
    );

    $jsonResponse = json_decode($response['body']);
    print_r($jsonResponse);
    return $jsonResponse;
  }

  public static function addAuthorToList($listId, $authorId) {
    $wp_http = new WP_Http;
    $response = $wp_http->request(
      sprintf('%stapi/list/add/', TwitterAPIWrapper::$tas_oauth_gw_uri),
      array('method' => 'POST', 'body' => array('authorId' => $authorId, 'listId' => $listId, 'key' => get_option('tas_oauth_gw_key')))
    );
  }
}

//// Support for WP 3.x
//if(!class_exists('WP_Http')) {
//  require_once(ABSPATH.WPINC.'/class-http.php');
//}
//
//// TODO: Still need to add error handling into this!
//class TwitterAPIWP {
//  private $wp_http;
//  private $format;
//  public $last_error;
//
//  function __construct($format = 'json') {
//    $this->wp_http = new WP_Http;
//    $this->format = $format;
//  }
//
//  private function _requestFailed($wpErrorOrResponse) {
//    $retVal = (is_wp_error($wpErrorOrResponse)||
//      !$wpErrorOrResponse ||
//      !$wpErrorOrResponse['response'] ||
//      !$wpErrorOrResponse['response']['code'] ||
//      $wpErrorOrResponse['response']['code'] != 200);
//    if(!$retVal) {
//      unset($this->last_error);
//      $this->last_error->response = $wpErrorOrResponse['response'];
//      if(is_wp_error($wpErrorOrResponse)) { $this->last_error->wp_error = $wpErrorOrResponse; }
//    }
//    return $retVal;
//  }
//
//  /**
//   * http://apiwiki.twitter.com/Twitter-REST-API-Method:-statuses%C2%A0show
//   */
//  public function statusesShow($id) {
//    $request = 'http://api.twitter.com/1/statuses/show/'.$id.'.'.$this->format;
//    $response = $this->wp_http->request($request);
//    return $this->_requestFailed($response) ? false : $response['body'];
//  }
//
//  /**
//   * http://apiwiki.twitter.com/Twitter-Search-API-Method:-search
//   * @param  $paramAry
//   * @return bool
//   */
//  public function search($paramAry) {
//    $request = 'http://search.twitter.com/search.json?';
//    foreach($paramAry as $idx => $param) {
//      $request .= $idx.'='.$param.'&';
//    }
//    $response = $this->wp_http->request($request);
//    return $this->_requestFailed($response) ? false : $response['body'];
//  }
//
//  /**
//   * http://apiwiki.twitter.com/Twitter-REST-API-Method:-users%C2%A0show
//   * @param  $paramAry
//   * @return bool
//   */
//  public function usersShow($paramAry) {
//    $request = 'http://api.twitter.com/1/users/show.'.$this->format.'?';
//    foreach($paramAry as $idx => $param) {
//      $request .= $idx.'='.$param.'&';
//    }
//    $response = $this->wp_http->request($request);
//    return $this->_requestFailed($response) ? false : $response['body'];
//  }
//}