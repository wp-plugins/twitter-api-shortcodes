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
