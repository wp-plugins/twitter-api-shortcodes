<?php
function jsonGenderBender($jsonStrOrObj, $output = 'json') {
  $inputIs = is_string($jsonStrOrObj) ? 'string' : 'json';
  if ($inputIs == strtolower($output)) {
    return $jsonStrOrObj;
  }

  $retVal = '';
  // Since we're returning, the break statements are redundant, but whatever.  :-)
  switch (strtolower($output)) {
    case 'json' :
      $retVal = json_decode($jsonStrOrObj);
      break;
    case 'string' :
      $retVal = json_encode($jsonStrOrObj);
      break;
  }
  return $retVal;
}

/**
 * 
 * @param string $url
 * @return stdClass An object with a 'url' and 'image' property.  'url' is the full source url for the image, and 'image' is the direct uri of the photo, suitable for use in an <img/> element
 */
function urlToImgUrl($url) {
	$image = new stdClass();
	$wp_http = new WP_Http;
	$response = $wp_http->request(
			$url,
			array('method' => 'GET')
	);
	
	if(is_a($response, 'WP_Error')) {
		$image->error = print_r($response, true);
		return $image;
	}
	
	$pattern = '/<meta (?:property|name)=[\'"]og:image[\'"].*content=[\'"](.*)[\'"]/';
	$matches = array();
	if(preg_match($pattern, $response['body'], $matches) && count($matches) == 2) {
		$image->image = $matches[1];
	}
	if(!$image->image) {
		$pattern = '/<meta content=[\'"](.*)[\'"].*(?:property|name)=[\'"]og:image[\'"]/';
		if(preg_match($pattern, $response['body'], $matches) && count($matches) == 2) {
			$image->image = $matches[1];
		}
	}
	 
	$pattern = '/<meta (?:property|name)=[\'"]og:url[\'"].*content=[\'"](.*)[\'"]/';
	$matches = array();
	if(preg_match($pattern, $response['body'], $matches) && count($matches) == 2) {
		$image->url = $matches[1];
	}
	if(!$image->url) {
		$pattern = '/<meta content=[\'"](.*)[\'"].*(?:property|name)=[\'"]og:url[\'"]/';
		if(preg_match($pattern, $response['body'], $matches) && count($matches) == 2) {
			$image->url = $matches[1];
		}
	}
	
	return $image;
}

/**
 * Takes the desired Smarty Template name as input, and determines if there is a matching template in the
 * currently active themes directory.  If not it returns the default TAS template
 * 
 * @param string $template_name The name of the desired Smarty template. I.E. tweet.tpl or tweet-image.tpl
 * @return string The full path to 
 */
function defaultOrThemeSmartyTemplate($template_name) {
	$tweetTemplate = WP_PLUGIN_DIR.'/twitter-api-shortcodes/templates/'.$template_name;
	$curTemplateTweet = TEMPLATEPATH.'/'.$template_name;
	if(file_exists($curTemplateTweet)) {
		$tweetTemplate = $curTemplateTweet;
	}
	
	return $tweetTemplate;
}

function writeLog($message) {
	if(get_option('tas_log', false)) {
		$logfile = WP_PLUGIN_DIR.'/twitter-api-shortcodes/log.log';
		$fh = fopen($logfile, 'a');
		fwrite($fh, '['.date('r').'] '.$message.PHP_EOL);
		fclose($fh);
	}
}