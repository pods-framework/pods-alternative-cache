<?php
/*
Plugin Name: Pods Alternative Cache
Plugin URI: http://pods.io/2014/04/16/introducing-pods-alternative-cache/
Description: Alternative caching engine for Pods for large sites on hosts with hard limits on how much you can cache
Version: 2.0.1
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

	if ( ! defined( 'PODS_ALT_CACHE' ) ) {
		define( 'PODS_ALT_CACHE', true );
	}

	if ( ! defined( 'PODS_ALT_CACHE_TYPE' ) ) {
		define( 'PODS_ALT_CACHE_TYPE', 'file' ); // file | db | memcache
	}

	include_once PODS_ALT_CACHE_DIR . 'classes/Pods/Alternative/Cache.php';
	include_once PODS_ALT_CACHE_DIR . 'classes/Pods/Alternative/Cache/Storage.php';

	$cache_type = 'file';

	if ( in_array( PODS_ALT_CACHE_TYPE, array( 'file', 'db', 'memcached' ) ) ) {
		$cache_type = PODS_ALT_CACHE_TYPE;
	}

	$pods_alternative_cache = new Pods_Alternative_Cache( $cache_type );

	register_activation_hook( __FILE__, array( $pods_alternative_cache, 'activate' ) );
	register_deactivation_hook( __FILE__, array( $pods_alternative_cache, 'deactivate' ) );

}
add_action( 'plugins_loaded', 'pods_alternative_cache_init' );

/**
 * Add support for cache debugging (to confirm writes are working as expected)
 */
function pods_alternative_cache_test_anon() {

	if ( defined( 'PODS_ALT_CACHE_DEBUG' ) && PODS_ALT_CACHE_DEBUG && ! empty( $_GET['altcache_debug'] ) ) {
		echo '<pre>';

		$rand = (int) time();

		$cache_key = 'pods-alt-cache-test';
		$cache_group = 'pods-alt-cache';

		$stats = array(
			'rand'               => $rand,
			'pods-alt-cache'     => array(
				'before_set' => null,
				'set'        => null,
				'after_set'  => null,
				'pass'       => false,
			),
			'wp-object-cache'    => array(
				'before_set' => null,
				'set'        => null,
				'after_set'  => null,
				'pass'       => false,
			),
			'wp-transient-cache' => array(
				'before_set' => null,
				'set'        => null,
				'after_set'  => null,
				'pass'       => false,
			),
		);

		/**
		 * Test Pods Alt Cache
		 */

		$cache_type = 'pods-alt-cache';
		$cache_name = 'Pods Alt Cache';

		$stats[ $cache_type ]['before_set'] = (int) pods_cache_get( $cache_key, $cache_group );

		$stats[ $cache_type ]['set'] = pods_cache_set( $cache_key, $stats['rand'], $cache_group, 300 );

		sleep( 1 );

		$stats[ $cache_type ]['after_set'] = (int) pods_cache_get( $cache_key, $cache_group );

		if ( $stats['rand'] == $stats[ $cache_type ]['after_set'] ) {
			$stats[ $cache_type ]['pass'] = true;

			var_dump( $cache_name . ' worked!' );
		} else {
			$stats[ $cache_type ]['pass'] = false;

			var_dump( $cache_name . ' failed!' );
		}

		/**
		 * Test WP Object Cache
		 */

		$cache_type = 'wp-object-cache';
		$cache_name = 'WP Object Cache';

		$stats[ $cache_type ]['before_set'] = (int) wp_cache_get( $cache_key, $cache_group );

		$stats[ $cache_type ]['set'] = wp_cache_set( $cache_key, $stats['rand'], $cache_group, 300 );

		sleep( 1 );

		$stats[ $cache_type ]['after_set'] = (int) wp_cache_get( $cache_key, $cache_group );

		if ( $stats['rand'] == $stats[ $cache_type ]['after_set'] ) {
			$stats[ $cache_type ]['pass'] = true;

			var_dump( $cache_name . ' worked!' );
		} else {
			$stats[ $cache_type ]['pass'] = false;

			var_dump( $cache_name . ' failed!' );
		}

		/**
		 * Test WP Transient Cache
		 */

		$cache_type = 'wp-transient-cache';
		$cache_name = 'WP Transient Cache';

		$stats[ $cache_type ]['before_set'] = (int) get_transient( $cache_key );

		$stats[ $cache_type ]['set'] = set_transient( $cache_key, $stats['rand'], 300 );

		sleep( 1 );

		$stats[ $cache_type ]['after_set'] = (int) get_transient( $cache_key );

		if ( $stats['rand'] == $stats[ $cache_type ]['after_set'] ) {
			$stats[ $cache_type ]['pass'] = true;

			var_dump( $cache_name . ' worked!' );
		} else {
			$stats[ $cache_type ]['pass'] = false;

			var_dump( $cache_name . ' failed!' );
		}

		var_dump( $stats );

		echo '</pre>';

		die();
	}

}
add_action( 'init', 'pods_alternative_cache_test_anon' );