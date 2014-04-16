=== Pods Alternative Cache ===
Contributors: sc0ttkclark
Donate link: http://podsfoundation.org/donate/
Tags: pods, cache, wpengine
Requires at least: 3.8
Tested up to: 3.9
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Pods Alternative Cache is a file-based or database-based caching solution for for hosts that have limitations on object caching. Pods Alternative Cache provides optimal performance with Pods sites on hosts with no object caching or low limits. It was developed for and tested against the WPEngine platform to improve performance of cached objects generated from Pods.

== Description ==

Pods Alternative Cache offers file-based, and databse-based caching.

This plugin requires the [Pods Framework](http://wordpress.org/plugins/pods/) version 2.4 or later to run.

= Why an Alternative Cache? =

Hosts like WPEngine have limits set on their object caching engine that are based on what they find optimal for their environment. Sometimes, plugins, themes, and even WordPress core can utilize object cache to the point where it gets too full. When that happens, certain caching engines like APC can remove objects from their cache and that can cause what appears to be random numbers of queries on each page load.

What Pods Alternative Cache does is store all of the Pods objects that need caching, separate from the default object caching engine. Depending on the environment or site, this may still not be optimal. You'll want to test things out and keep an eye on your site's performance to see if it's the right fit for you.

== Installation ==

1. Unpack the entire contents of this plugin zip file into your `wp-content/plugins/` folder locally
1. Upload to your site
1. Navigate to `wp-admin/plugins.php` on your site (your WP Admin plugin page)
1. Activate this plugin

OR you can just install it with WordPress by going to Plugins >> Add New >> and type this plugin's name

== Contributors ==

Check out our GitHub for a list of contributors, or search our GitHub issues to see everyone involved in adding features, fixing bugs, or reporting issues/testing.

[github.com/pods-framework/pods-alternative-cache/graphs/contributors](https://github.com/pods-framework/pods-alternative-cache/graphs/contributors)

== FAQ ==

= Which type of caching is used by default? =

By default, file-based caching is used.

= How can I switch to database-based caching? =

In your wp-config.php add this line:

define( 'PODS_ALT_CACHE_TYPE', 'db' );

== Changelog ==

= 1.0 - April 16, 2014 =
* First official release!
* Found a bug? Have a great feature idea? Get on GitHub and tell us about it and we'll get right on it: [github.com/pods-framework/pods-seo/issues/new](https://github.com/pods-framework/pods-alternative-cache/issues/new)
