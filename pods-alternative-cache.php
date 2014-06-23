<?php
/*
Plugin Name: Pods Alternative Cache
Plugin URI: http://pods.io/2014/04/16/introducing-pods-alternative-cache/
Description: Alternative caching engine for Pods for large sites on hosts with hard limits on how much you can cache
Version: 1.1
Author: The Pods Framework Team
Author URI: http://pods.io/
*/

define( 'PODS_ALT_CACHE_DIR', plugin_dir_path( __FILE__ ) );

/**
 * @global $pods_alternative_cache Pods_Alternative_Cache
 */
global $pods_alternative_cache;


/**
 * Setup default constants, add hooks
 */
function pods_alternative_cache_init() {

	/**
	 * @var $pods_alternative_cache Pods_Alternative_Cache
	 */
	global $pods_alternative_cache;

	if ( !defined( 'PODS_ALT_CACHE' ) ) {
		define( 'PODS_ALT_CACHE', true );
	}

	if ( !defined( 'PODS_ALT_CACHE_TYPE' ) ) {
		define( 'PODS_ALT_CACHE_TYPE', 'file' );
	}

	include_once PODS_ALT_CACHE_DIR . 'classes/Pods/Alternative/Cache.php';
	include_once PODS_ALT_CACHE_DIR . 'classes/Pods/Alternative/Cache/Storage.php';

	if ( 'db' == PODS_ALT_CACHE_TYPE ) {
		$pods_alternative_cache = new Pods_Alternative_Cache( 'db' );
	}
	else {
		$pods_alternative_cache = new Pods_Alternative_Cache( 'file' );
	}

	register_activation_hook( __FILE__, array( $pods_alternative_cache, 'activate' ) );
	register_deactivation_hook( __FILE__, array( $pods_alternative_cache, 'deactivate' ) );

}
add_action( 'plugins_loaded', 'pods_alternative_cache_init' );