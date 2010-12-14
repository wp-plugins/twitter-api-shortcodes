/**
 * Created by IntelliJ IDEA.
 * User: rgeyer
 * Date: Jun 28, 2010
 * Time: 2:51:12 PM
 * To change this template use File | Settings | File Templates.
 */

String.prototype.capitalize = function() {
    return this.charAt(0).toUpperCase() + this.slice(1);
}

jQuery(document).ready(function($) {
  function checkOnlyOneSearch(theRow) {
    $('.check-column input[type="checkbox"]').attr('checked', false);
    $('th input[type="checkbox"]', theRow).attr('checked', true);
  }

  function showDeleteDialog() {
    $('#tas_confirm_delete').dialog('destroy');
    $('#tas_confirm_delete').dialog({
      resizable: false,
      height: 280,
      modal: true,
      buttons: {
        "Yeah, Delete 'em!": function() {
          $('#tas_admin_options_form').append('<input type="hidden" name="submit_val" value="Apply" />');
          $('#tas_admin_options_form').submit();
          $(this).dialog('close');
        },
        "Cancel": function() {
          $(this).dialog('close');
        }
      }
    });
  }

  function showAddEditDialog(action, term, archive) {
    $('#tas_add_edit #terms').val(term);
    $('#tas_add_edit #archive').attr('checked', archive);

    var title = action == 'add' ? 'Add a new search' : 'Edit a search';
    action = action.capitalize()
    

    $('#tas_add_edit').dialog('destroy');
    $('#tas_add_edit').dialog({
      resizable: true,
      modal: true,
      title: title,
      buttons: {
        "Add/Edit" : function() {
          $('#tas_admin_options_form').append('<input type="hidden" name="terms" value="' + $('#terms', this).val() + '" />');
          $('#tas_admin_options_form').append('<input type="hidden" name="archive" value="' + $('#archive', this).attr('checked') + '" />');
          $('#tas_admin_options_form').append('<input type="hidden" name="submit_val" value="' + action.capitalize() + '" />');
          $('#tas_admin_options_form').submit();
        },
        "Cancel": function() {
          $(this).dialog('close');
        }
      }
    });
  }

  $('#why_authenticate').click(function() {
    $('#tas_explain_why_authenticate').dialog({width: 500});
    return false;
  });

  $('#authenticate_with_twitter').click(function() {
    if ($(this).is(':checked')) {
      $('#twitter_auth_pane').show('blind');
    } else {
      $('#twitter_auth_pane').hide('blind');
    }
  });

  $('#tas_message_toggle').click(function() {
    if ($('#tas_admin_panel').is(':hidden')) {
      $('#tas_message_toggle').removeClass('ui-icon-circle-triangle-e');
      $('#tas_message_toggle').addClass('ui-icon-circle-triangle-s');
    } else {
      $('#tas_message_toggle').removeClass('ui-icon-circle-triangle-s');
      $('#tas_message_toggle').addClass('ui-icon-circle-triangle-e');
    }
    $('#tas_admin_panel').toggle('Blind');
    return false;
  });

  $('#authenticate').click(function() {
    $('#tas_admin_options_form').append('<input type="hidden" name="submit_val" value="OAuth" />');
    $('#tas_admin_options_form').submit();
  });

  $('.tas_search_bulk_submit').click(function(event) {
    var isADelete = false;
    $('.tas_search_bulk_select').each(function(i, e) {
      if ($(e).val() == 'delete') {
        isADelete = true;
      }
    });
    if (isADelete) {
      $('#searches_to_delete').empty();
      $('tbody tr:has(th input:checked)').each(function() {
        $('.tas_search_term', this).each(function(i, e) {
          $('#searches_to_delete').append($(e).text() + '<br/>');
        });
      });
      event.preventDefault();
      showDeleteDialog();
    }
  });

  $('#tas_add_search_btn').click(function() {
    showAddEditDialog('add', '', false);
    return false;
  });

  $('.tas_search_delete_btn').click(function() {
    var curRow = $(this).data('row');
    checkOnlyOneSearch(curRow);
    $('#searches_to_delete').empty();
    $('#searches_to_delete').append($('.tas_search_term', curRow).text() + '<br/>');
    $('.tas_search_bulk_select').val('delete').attr('selected', true);
    showDeleteDialog();
    return false;
  });

  $('.tas_search_edit_btn').click(function() {
    var curRow = $(this).data('row');
    checkOnlyOneSearch(curRow);

    showAddEditDialog('edit', $('.tas_search_term', curRow).text(), $('.tas_search_archive', curRow).attr('value') == '1');
    return false;
  });

  $('#tas_search_list tbody tr').each(function() {
    $('.tas_search_edit_btn', this).data('row', this);
    $('.tas_search_delete_btn', this).data('row', this);
  });
});