<div class="status">
  <div class="author">
    <a href="http://twitter.com/{$tweet->user->screen_name}" target="_blank">
      <img alt="{$tweet->user->screen_name}" height="48" src="{$tweet->user->profile_image_url}" width="48">
    </a>
  </div>
  <div class="status-body">
    <div class="status-content">
      <strong><a href="http://twitter.com/{$tweet->user->screen_name}" target="_blank">{$tweet->user->screen_name}</a></strong>
      <span class="entry-content">{$tweet->text}</span>
    </div>
    <div class="meta entry-meta">
      On
      <a class="entry-date" rel="bookmark" href="http://twitter.com/{$tweet->user->screen_name}/status/{$tweet->id_str}" target="_blank">
        <span class="published timestamp">{$tweet->created_at}</span>
      </a>
      via {$tweet->source}
    </div>
  </div>
</div>