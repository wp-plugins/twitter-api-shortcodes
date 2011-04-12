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
 * This takes in a json object of a status which came from either the search api or the status api and makes it
 * all standardized to the format returned by the status api.  Namely the search API doesn't include the user
 * data, and it's "source" property is html encoded.  Since it takes the json object in by reference, if you pass
 * your object in by reference you can ignore the return value.
 * @param  $jsonObj The status json object to normalize
 * @return The normalized json object
 */
function normalizeStatus(&$jsonObj) {
  // See the documentation about the return value for the search API at;
  // http://apiwiki.twitter.com/Twitter-Search-API-Method:-search
  // If the user data isn't available, we'll make another call to go grab it!
  if (!isset($jsonObj->user)) {
    /* Getting the user for each one using another call is a HUGE waste, lets try a better way.
    $twitterApi = new TwitterAPIWP;
    $jsonObj->user = jsonGenderBender($twitterApi->usersShow(array('screen_name' => $jsonObj->from_user)));*/

    $jsonObj->user = new stdClass();
    $jsonObj->user->id = $jsonObj->from_user_id;
    $jsonObj->user->screen_name = $jsonObj->from_user;
    $jsonObj->user->profile_image_url = $jsonObj->profile_image_url;
  }

  // Again, only the search option returns an html encoded source, so we take care of that here.
  $jsonObj->source = htmlspecialchars_decode($jsonObj->source);

  // It's useful to have the created timestamp as an actual timestamp
  $jsonObj->created_at_ts = strtotime($jsonObj->created_at);

  return $jsonObj;
}