=== Pods Alternative Cache ===
Contributors: sc0ttkclark
Donate link: https://pods.io/friends-of-pods/
Tags: pods, cache, wpengine
Requires at least: 3.8
Tested up to: 4.8
Stable tag: 2.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Pods Alternative Cache is a file-based or database-based caching solution for hosts that have limitations on object caching.

== Description ==

Pods Alternative Cache provides optimal performance with Pods sites on hosts with no object caching or low limits. It was developed for and tested against the WPEngine platform to improve performance of cached objects generated from Pods, but it works on numerous other hosting providers.

Pods Alternative Cache is a great addition to a site already utilizing Object Caching, it further separates and allows Pods to utilize more consistent persistent caching without affecting other plugins and WordPress caching objects. Especially when utilizing larger configurations, this plugin improves performance by ensuring other necessary objects are not removed by the server to make room for Pods cached objects.

This plugin requires the [Pods Framework](http://wordpress.org/plugins/pods/) version 2.4 or later to run.

For more information on how to use this plugin, see [http://pods.io/2014/04/16/introducing-pods-alternative-cache/](http://pods.io/2014/04/16/introducing-pods-alternative-cache/).

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

= Why an Alternative Cache? =

Hosts like WPEngine have limits set on their object caching engine that are based on what they find optimal for their environment. Sometimes, plugins, themes, and even WordPress core can utilize object cache to the point where it gets too full. When that happens, certain caching engines like APC can remove objects from their cache and that can cause what appears to be random numbers of queries on each page load.

What Pods Alternative Cache does is store all of the Pods objects that need caching, separate from the default object caching engine. Depending on the environment or site, this may still not be optimal. You'll want to test things out and keep an eye on your site's performance to see if it's the right fit for you.

= What options are available? =

In your wp-config.php, or prior to the `plugins_loaded` action, you can define other constants to change how the plugin works.

Change the storage type (be sure to deactivate/activate between storage type switches):

`define( 'PODS_ALT_CACHE_TYPE', 'db' ); // Default is 'file', you can choose 'memcached' too`

Change the path to the File cache folder:

`define( 'PODS_ALT_FILE_CACHE_DIR', 'path/to/folder' ); // Default is 'wp-content/podscache'`

Set MemCached Server host or IP address

`define( 'PODS_ALT_CACHE_MEMCACHED_SERVER', '127.0.0.1' ); // Default is 'localhost'`

Set MemCached Server PORT number

`define( 'PODS_ALT_CACHE_MEMCACHED_PORT', 11211 ); // Default is 11211`

Disable Pods Alternative Cache:

`define( 'PODS_ALT_CACHE', false ); // Default is true`

== Changelog ==

= 2.0.2 - June 2nd, 2017 =
* Revamped branding assets
* Fixed php notice
* Fixed usage of memcached port to be an integer

= 2.0.1 - July 13th, 2016 =
* Fixed cache file/folder deleting bug that wouldn't let Pods clear / preload caches properly
* Typo fix (props @szepeviktor)

= 2.0 - June 23rd, 2016 =
* Added support for a Memcache caching (props @shaer)
* Added support for WP_Filesystem usage instead of using PHP directly
* Added additional WPEngine compatibility
* Refactored into a better OO pattern so the code is easier to use and extend
* Added ability to create custom storage types through the `pods_alternative_cache_storage_types` filter, `return $storage_types;` where you've set `$storage_types[ 'your_type' ] = 'Your_Class';`
* File storage now uses md5-based folder structure to avoid issues on sites with a large amount of cached objects to avoid having folders with too many files in them which could cause issues with certain hosts
* Found a bug? Have a great feature idea? Get on GitHub and tell us about it and we'll get right on it: [github.com/pods-framework/pods-alternative-cache/issues/new](https://github.com/pods-framework/pods-alternative-cache/issues/new)

= 1.0 - April 16th, 2014 =
* First official release!
