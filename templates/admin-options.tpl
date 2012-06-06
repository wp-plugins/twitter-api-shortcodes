<div class="tas_admin ui-widget">
  <form action="" method="POST" id="tas_admin_options_form">
    <script type="text/javascript">
    var hasErrors = {$hasErrors};'
    </script>
    <a href="" id="tas_message_toggle" class="ui-icon ui-icon-circle-triangle-e"></a>
    <div id="tas_admin_panel" class="ui-helper-hidden tas_messages">
      <div class="info ui-state-highlight"><p><span class="ui-icon ui-icon-info"></span>Last installation (database update) occurred {$last_installed|date_format:'%c'}</p></div>
      <div class="info ui-state-highlight"><p><span class="ui-icon ui-icon-info"></span>Last cron run occurred {$last_cron|date_format:'%c'}</p></div>
      <div class="info ui-state-highlight"><p><span class="ui-icon ui-icon-info"></span>Current DB version is {$db_version}</p></div>

      <div class="tas_admin_buttons">
        <div class="tas_admin_button"><input type="submit" value="Run Cron Now" name="submit_val" /></div>
        {if $have_twitter_auth_token}
        <div class="tas_admin_button">
        <a href="{$twitter_auth_url}" class="twitter_auth_btn"><img src="{$blog_url}/wp-content/plugins/twitter-api-shortcodes/images/sign-in-with-twitter-d.png" alt="Twitter Authentication Button" /></a>
        Re-authenticate with twitter (probably for debug only)
        </div>
        {/if}
      </div>
    </div>
    <div class="tas_messages">
    {foreach from=$messages item=message}
      {if $message.type == 'error'}
        <div class="{$message.type} ui-state-error"><p><span class="ui-icon ui-icon-alert"></span>{$message.message}</p></div>
      {else}
        <div class="{$message.type} ui-state-highlight"><p><span class="ui-icon ui-icon-info"></span>{$message.message}</p></div>
      {/if}
    {/foreach}
    </div>
    <input type="hidden" id="_wpnonce" name="_wpnonce" value="{$nonce}" /> 
    <div class="tas_settings" id="col-container">
      <div id="col-right">
        <div class="col-wrap">
          <h3>Options</h3>
          <div class="form-wrap">
            <div class="form-field">
              <label for="authenticate_with_twitter">Authenticate With Twitter? <a href="" id="why_authenticate">(?)</a></label>
              <input type="checkbox" name="authenticate_with_twitter" id="authenticate_with_twitter"{if $twitter_auth} checked{/if}/>
            </div>
            <div id="twitter_auth_pane" class="{if !$twitter_auth}ui-helper-hidden{/if}">
              <div class="form-field{if $have_twitter_auth_token} ui-helper-hidden{/if}">
                <a href="{$twitter_auth_url}" class="twitter_auth_btn"><img src="{$blog_url}/wp-content/plugins/twitter-api-shortcodes/images/sign-in-with-twitter-d.png" alt="Twitter Authentication Button" /></a>
              </div>
              <div class="form-field{if !$have_twitter_auth_token} ui-state-disabled{/if}">
                <label for="update_avatars">Update Avatars?</label>
                <input type="checkbox" name="update_avatars" id="update_avatars"{if $update_avatars} checked{/if}{if !$have_twitter_auth_token} disabled{/if} />
                <p>If checked, the avatars for cached tweets will be updated periodically.  If this isn't set, the avatar
                for the author will always be the avatar they had when the tweet was added to your post and archived in the db.</p>
              </div>
              <div class="form-field{if !$have_twitter_auth_token} ui-state-disabled{/if}">
                <label for="use_auth_for_tags">Use Authorization to Fetch Tweets?</label>
                <input type="checkbox" name="use_auth_for_tags" id="use_auth_for_tags"{if $use_auth_for_tags} checked{/if}{if !$have_twitter_auth_token} disabled{/if} />
                <p>If checked, tweets for the [twitter_status_by_id] tag will be
                fetched using your authentication.  This is useful for showing your tweets, even if your timeline is
                private.</p>
              </div>
            </div>
            <input type="submit" name="submit_val" value="Update Options" class="button"/>
          </div>
        </div>
      </div>
      <div id="col-left">
        <div class="col-wrap">
          <h3>Existing searches</h3>
          <div class="tablenav">
            <select name="search-action" class="tas_search_bulk_select">
              <option value="-1">Bulk Search Actions</option>
              <option value="archive">Archive Search</option>
              <option value="dearchive">Disable Archive Search</option>
              <option value="delete">Delete Search</option>
            </select>
            <input type="submit" name="submit_val" value="Apply" class="tas_search_bulk_submit button"/>
          </div>
          <table class="widefat post fixed" cellspacing="0" id="tas_search_list" >
            <thead>
              <tr>
                <th scope="col" id="cb" class="manage-column column-cb check-column"><input type="checkbox" /></th>
                <th>Terms</th>
                <th>Archived Tweets</th>
                <th>Shortcode</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
  {foreach from=$searches item=search}
              <tr>
                <th scope="row" class="check-column"><input type="checkbox" name="search[]" value="{$search->id}" /></th>
                <td class="tas_search_term">{$search->search_term}</td>
                <td class="tas_search_archive" value="{$search->archive}">{if $search->archive}{$search->archivedStatuses}{else}Not Archived{/if}</td>
                <td>[twitter_search id={$search->id}]</td>
                <td><a href="" class="tas_search_edit_btn">Edit</a> <a href="" class="tas_search_delete_btn">Delete</a></td>
              </tr>
  {/foreach}
            </tbody>
            <tfoot>
              <tr>
                <th scope="col" class="manage-column column-cb check-column"><input type="checkbox" /></th>
                <th>Terms</th>
                <th>Archived Tweets</th>
                <th>Shortcode</th>
                <th>Actions</th>
              </tr>
            </tfoot>
          </table>
          <div class="tablenav">
            <select name="search-action2" class="tas_search_bulk_select">
              <option value="-1">Bulk Search Actions</option>
              <option value="archive">Archive Search</option>
              <option value="dearchive">Disable Archive Search</option>
              <option value="delete">Delete Search</option>
            </select>
            <input type="submit" name="submit_val" value="Apply" class="tas_search_bulk_submit button"/>
          </div>
          <button id="tas_add_search_btn" class="button">Add a new search</button>
        </div>
      </div>
    </div>
  </form>
  <!-- My dialogs go down here -->
  <div id="tas_confirm_delete" class="ui-helper-hidden" title="Really Delete?">
    <span class="ui-icon ui-icon-alert" <!--style="float:left; margin:0 7px 20px 0;"-->></span>
    <p>Are you really sure you want to delete the selected searches;</p>
    <div id="searches_to_delete">
    </div>
    <p><b><em>This cannot be un-done!</em></b></p>
  </div>

  <div id="tas_add_edit" class="ui-helper-hidden" title="Add">
    <div class="form-wrap">
      <div class="form-field">
        <label for="terms">Terms</label>
        <input type="text" name="terms" id="terms" value="{$smarty.request.terms}" />
        <p>The search term, check out the official <a href="http://search.twitter.com/operators" target="_blank">search operators</a>
        for all the syntax goodness.</p>
      </div>
      <div class="form-field">
        <label for="archive">Archive</label>
        <input type="checkbox" name="archive" id="archive" value="1" {if $smarty.request.archive}checked{/if} />
        <p>Make sure you intend to do this!  We'll cache every single tweet that is found for the search.  For something
        very common, like say #ff or #followfriday you could easily cripple your database and blog!</p>
      </div>
    </div>
  </div>

  <div id="tas_explain_why_authenticate" class="ui-helper-hidden" title="Why Authenticate with Twitter?" >
    <p>By allowing the TAS plugin to use the Twitter API while authenticated as you, some new features become available to you.</p>
    <h4>Updating Author Avatars</h4>
    <p>When TAS stores a tweet in the database, it will store the avatar of that tweets author at that moment in time.
    If the author later changes their avatar, your blog will still display the old one.</p>
    <p>By authenticating with Twitter (and enabling the "Update Avatars?" option), TAS will create a new list on your Twitter account.
    The authors of all tweets that you cache in your wordpress database will be added to that group, and daily TAS will check
    if those authors have updated their avatar, then update your local record.  Pretty cool, right?</p>
    <h4>Private Tweets</h4>
    <p>If you want to display tweets from your timeline, or from other authors timelines, and those tweets are private they won't be displayed
    unless you're logged in as a user who has permission to view those tweets.  By allowing TAS to call the Twitter API while authenticated as
    you, these tweets can be displayed.</p>
  </div>
</div>