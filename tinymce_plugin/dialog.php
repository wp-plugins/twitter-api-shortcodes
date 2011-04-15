<?php
// Gotta get the root path to the site, could just use a relative path, but if you're using a symlink to put the plugin
// into the wp-content/plugins directory, it'll resolve wrong.
$scriptFile = $_SERVER['SCRIPT_FILENAME'];
$wp_header = implode('/', array_diff(explode('/', $scriptFile), array_slice(explode('/', $scriptFile), -5)));

define('WP_USE_THEMES', false);
require_once($wp_header.'/wp-blog-header.php');
TasForWp::getInstance();
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
  "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
  <title>Enter a twitter shortcode.</title>
  <script type="text/javascript" src="<?php bloginfo('url'); ?>/wp-includes/js/tinymce/tiny_mce_popup.js">
</script>

<script language="javascript" type="text/javascript">

var tasInsertDialog = {
    init : function () {
    },
    mySubmit : function ($searchOrStatus) {
      var insertVal = '[';
      if($searchOrStatus == 'search') {
        insertVal += 'twitter_search id=' + document.tas_input_form.search_id.value;
        if(document.tas_input_form.list_limit.value != '') {
          insertVal += ' limit=' + document.tas_input_form.list_limit.value;
        }
        if(document.tas_input_form.page_search.checked) {
          insertVal += ' paging=true';
        }
        insertVal += ']';
      } else {
        insertVal += 'twitter_status_by_id id="' + document.tas_input_form.status_id.value +'"]';
      }
      tinyMCEPopup.execCommand('mceInsertContent', false, insertVal);

      // Refocus in window
      if (tinyMCEPopup.isWindow)
        window.focus();

      tinyMCEPopup.editor.focus();
      tinyMCEPopup.close();
    }
}

tinyMCEPopup.onInit.add(tasInsertDialog.init, tasInsertDialog);

</script>
</head>
<body>
<div class="form-wrap">
  <form name="tas_input_form" id="tas_input_form">
    <div class="form-field">
      <label for="status_id">Tweet Id:</label>
      <input type="text" id="status_id" name="status_id" />
    </div>
    <input type="submit" value="Add Status" name="submit" onclick="tasInsertDialog.mySubmit('status');" class="button" style="margin: 5px;"/>
    <hr/>
    <div class="form-field">
      <label for="search_id">Predefined Search:</label>
      <select name="search_id" id="search_id">
    <?php
    foreach($wpdb->get_results("SELECT * FROM `".TasForWp::$SearchTableName."`") as $search) {
      print "    <option value='$search->id'>".rawurldecode($search->search_term)."</option>\n";
    }
    ?>
      </select>
    </div>
    <div class="form-field">
      <label for="list_limit">Max Tweets</label>
      <input type="text" id="list_limit" name="list_limit" />
    </div>
    <div class="form-field">
      <label for="page_search">Allow Paging?</label>
      <input type="checkbox" id="page_search" name="page_search" />
    </div>
    <input type="submit" value="Add Search" name="submit" onclick="tasInsertDialog.mySubmit('search');" class="button" style="margin: 5px;"/>
  </form>
</div>
</body>
</html>