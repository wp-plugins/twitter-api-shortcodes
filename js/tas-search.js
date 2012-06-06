jQuery(document).ready(function($) {
  
  function dataAttrToData(idx,val) {    
    if($(val).data('data')) {
      return true;
    }
    dataStr = $(val).attr('data');
    if(!dataStr) { return true; }
    dataObj = $.parseJSON(dataStr);
    $(val).data('data', dataObj);
  }
  
  function convertAllData() {
    $('.status').each(dataAttrToData);
    $('.tweet-list').each(dataAttrToData);
  }

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
    fetchImages();
  }
  
  function fetchImages() {
    // Fetch images for each status with image entities
    $('.status').each(function(idx, val) {
      if($('.images *', val).length > 0) {
        console.log('returning cause theres images');
        return;
      }
      
      dataObj = $(val).data('data');
      console.log(dataObj);
  	  if(dataObj.images && dataObj.images.length > 0) {
  	    // There are already images in the response, no need to try fetching.
  	    console.log("Img already sussed");
      } else if(dataObj.entities) {
    	  for(url_idx in dataObj.entities.urls) {
    	    url = dataObj.entities.urls[url_idx].expanded_url;    	    
    	    $.post(
            tas_search_script.ajaxurl,
            {
              action: 'tas_url_to_image',
              url: url
            },
            function(response) {
              try {
                console.log(response);
                obj = $.parseJSON(response);
                if(obj.url && obj.image) {
                  $('.status:eq('+idx+') .images').append(obj.markup);
                }
              } catch(parse_exc) {
                console.log(parse_exc);
                console.log(response);
              }
            }
          );
    	  }
      }
    });
  }
  
  function refreshAllSearches() {
    $('.tweet-list').each(function(idx, searchDiv) {
      // TODO: Scope data to this .tweet-list
      // TODO: Refactor the contents of this click event into a function, then put it in
      // a settimeout to auto refresh.
      //searchDiv = $('.tweet-list');
      searchData = $(searchDiv).data('data');
      refreshUrl = 'https://search.twitter.com/search.json'+searchData.refresh_url+'&callback=?';
      console.log('Gonna ask for more search results using: '+refreshUrl);
      
      $.getJSON(refreshUrl,
        function(data) {
          if(data.results.length == 0) { return false; }
          console.log(data);        
          $.post(
            tas_search_script.ajaxurl,
            {
              action: 'tas_search_to_markup',
              response: data,
              search_id: searchData.id,
              search_div: $(searchDiv).attr('id'),
              limit: searchData.limit
            },
            function(response) {
              console.log(response);
              console.log($.parseJSON(response));
              responseJson = $.parseJSON(response);
              existing_statuses = $('.status', searchDiv);
              // Delete them first
              for(markup_idx in responseJson.markups) {              
                idx = (existing_statuses.length - markup_idx) - 1;
                lastStatus = existing_statuses[idx];
                $(lastStatus).fadeOut('slow', function() {$(this).remove();});
              }
              for(markup_idx in responseJson.markups) {
                searchDiv = $('#'+responseJson.search_div); 
                searchDiv.prepend(responseJson.markups[markup_idx]).hide().fadeIn('slow');
              }
              convertAllData();
              fetchImages();
            }
          );
          searchData.refresh_url = data.refresh_url;
          $(searchDiv).data('data', searchData);
        }
      );
      return false;
    });
  }
  
  function refreshLoop() {
    refreshAllSearches();
    setTimeout(refreshLoop, 15000);    
  }
  
  convertAllData();
  fetchImages();
  
  setTimeout(refreshLoop, 15000);

  // Convert data attribute on links to jQuery data
  $('.tas_search_next, .tas_search_refresh').each(function(idx, val) {
    infoStr = $(val).attr('data');
    infoObj = $.parseJSON(infoStr);
    //infoObj = $(val).data('data');
    infoObj.page = 1;
    $(val).data('info', infoObj);
    console.log(infoObj);
  });

  $('.tas_search_next').live('click', function() {
    console.log("There are "+$('.tweet-list .status').length+" tweets showing in the search");
    //$('.tweet-list .status:last').fadeOut('slow', function() {$('.tweet-list .status:last').remove();});
    /*infoObj = $(this).data('info');
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
    return false;*/
  });

  $('.tas_search_refresh').live('click', function() {refreshAllSearches();});
  
  /*$('.tweet-list').each(function(idx, val) {
    refreshUrlJson = $(val).attr('data');
    refreshUrl = $.parseJSON(refreshUrlJson);
    
    $.get('https://search.twitter.com/search.json'+refreshUrl,
      function(data) {
        console.log($.parseJSON(data));
      }
    );
  });*/
});