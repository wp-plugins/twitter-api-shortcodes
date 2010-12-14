=== Twitter API Shortcodes ===
Contributors: Ryan J. Geyer, Jonathan Daggerheart
Tags: twitter, search, shortcode, api
Requires at least: 2.9
Tested up to: 3.0.3
Stable tag: 0.0.1Alpha

A plugin to add single tweets or twitter searches to your posts and pages using shortcodes

== Description ==
Twitter API Shortcodes (TAS for short) is currently in Alpha, and should not be considered at all stable!  With that said
please provide any feedback so we can make things better!

TAS is 

== Installation ==
Twitter API Shortcodes depends upon the Smarty PHP templating engine.  Since we're good little boys and girls, we won't
include the library with the plugin, so you'll have to go get it yourself! But don't worry, it's not hard simply go to;

http://www.smarty.net/download.php

And grab the latest stable version.  Twitter API Shortcodes was built using version 2.6.26, but it's probably safe to
assume that any 2.6.x version of Smarty will do.

Once you've got the latest version, extract the zip, or tar.gz file, and copy the contents of the "libs" directory to
/wp-content/twitter-api-shortcodes/libs/smarty/

= Update 0.0.3Alpha =
From version 0.0.3Alpha of TAS onward Smarty 3.0.x is supported too, but be sure you're running PHP5 before you try Smarty 3!

== Frequently Asked Questions ==
TODO

== Screenshots ==
1. TinyMCE editor integration makes it easy to add the shortcodes
2. A post with some shortcodes in it
3. The shortcodes rendered in a post 

== Changelog ==
= 0.0.3Alpha =
* The "Why Authenticate" dialog can be reopened after being dismissed
* Updated to support Smarty 3.0.x

= 0.0.2Alpha =
* Changes to metadata files (like this readme) and getting a feel for the Wordpress submission process

= 0.0.1Alpha =
* Initial Alpha release, seeking input from brave users

== Upgrade Notice ==

= 0.0.1Alpha =
Nuthin'

== TODO ==
This is a list of things which currently are not yet working (hey it's an alpha version)
* Avatar updates