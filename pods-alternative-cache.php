<?php
/*
Plugin Name: Pods Alternative Cache
Plugin URI: http://pods.io/2014/04/16/introducing-pods-alternative-cache/
Description: Alternative caching engine for Pods for large sites on hosts with hard limits on how much you can cache
Version: 2.0.2
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

		$persist_check = '';

		if ( ! empty( $_GET['altcache_debug_check'] ) ) {
			$persist_check = $_GET['altcache_debug_check'];
		}

		$cache_key   = 'pods-alt-cache-test';
		$cache_group = 'pods-alt-cache';

		$cache_persist_key = 'pods-alt-cache-persist';

		$stats = array(
			'rand'           => $rand,
			'non-persistent' => array(
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
			),
			'persistent'     => array(
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
			),
		);

		foreach ( $stats as $persist_type => $persist_stats ) {
			if ( 'rand' == $persist_type ) {
				continue;
			}

			foreach ( $persist_stats as $cache_type => $stat ) {
				$cache_name = ucwords( str_replace( '-', ' ', $cache_type ) );
				$cache_name .= ' (' . ucwords( str_replace( '-', ' ', $persist_type ) ) . ')';

				$before_set = '';
				$set        = '';
				$after_set  = '';

				$key   = $cache_key;
				$value = $stats['rand'];

				if ( 'persistent' == $persist_type ) {
					$key   = $cache_persist_key;
					$value = $persist_check;
				}

				if ( 'pods-alt-cache' == $cache_type ) {
					$before_set = (int) pods_cache_get( $key, $cache_group );

					if ( $value ) {
						$set = pods_cache_set( $key, (int) $value, $cache_group, 300 );
					}
				} elseif ( 'wp-object-cache' == $cache_type ) {
					$before_set = (int) wp_cache_get( $key, $cache_group );

					if ( $value ) {
						$set = wp_cache_set( $key, (int) $value, $cache_group, 300 );
					}
				} elseif ( 'wp-transient-cache' == $cache_type ) {
					$before_set = (int) get_transient( $key );

					if ( $value ) {
						$set = set_transient( $key, (int) $value, 300 );
					}
				}

				$stats[ $persist_type ][ $cache_type ]['before_set'] = $before_set;
				$stats[ $persist_type ][ $cache_type ]['set']        = $set;

				sleep( 1 );

				if ( 'pods-alt-cache' == $cache_type ) {
					$after_set = (int) pods_cache_get( $key, $cache_group );
				} elseif ( 'wp-object-cache' == $cache_type ) {
					$after_set = (int) wp_cache_get( $key, $cache_group );
				} elseif ( 'wp-transient-cache' == $cache_type ) {
					$after_set = (int) get_transient( $key );
				}

				$stats[ $persist_type ][ $cache_type ]['after_set'] = $after_set;

				if ( $value ) {
					if ( $value == $stats[ $persist_type ][ $cache_type ]['after_set'] ) {
						$stats[ $persist_type ][ $cache_type ]['pass'] = true;

						var_dump( $cache_name . ' worked!' );
					} else {
						$stats[ $persist_type ][ $cache_type ]['pass'] = false;

						var_dump( $cache_name . ' failed!' );
					}
				} else {
					var_dump( $cache_name . ' persist check' );
				}
			}
		}

		var_dump( $stats );

		echo '</pre>';

		die();
	}

}

add_action( 'init', 'pods_alternative_cache_test_anon' );