jQuery(document).ready(function($) {

  function repopulateSearchDiv(response) {
    obj = $.parseJSON(response);
    homediv = $('#'+obj.info_obj.div_guid);
    homediv.empty();
    homediv.append(obj.statuses);
    if(obj.next_link) {
      homediv.append(obj.next_link);
    }
    if(obj.refresh_link) {
      homediv.append(obj.refresh_link);
    }
    $('.tas_search_next, .tas_search_refresh',homediv).data('info', obj.info_obj);
  }

  // Convert data attribute on links to jQuery data
  $('.tas_search_next, .tas_search_refresh').each(function(idx, val) {
    infoStr = $(val).attr('data');
    infoObj = $.parseJSON(infoStr);
    infoObj.page = 1;
    $(val).data('info', infoObj);
  });

  $('.tas_search_next').live('click', function() {
    infoObj = $(this).data('info');
    infoObj.page = parseInt(infoObj.page) + 1;
    if(isNaN(infoObj.page))
    {
      infoObj.page = null;
    }
    $.post(
      tas_search_script.ajaxurl,
      {
        action: 'tas_search',
        info_str: infoObj
      },
      repopulateSearchDiv
    );
    return false;
  });

  $('.tas_search_refresh').live('click', function() {
    infoObj = $(this).data('info');
    infoObj.max_status_id = null;
    infoObj.page = null;
    $.post(
      tas_search_script.ajaxurl,
      {
        action: 'tas_search',
        info_str: infoObj
      },
      repopulateSearchDiv
    );
    return false;
  });
});