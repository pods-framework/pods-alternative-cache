<?php
/*
Plugin Name: Pods Alternative Cache
Plugin URI: https://pods.io/2014/04/16/introducing-pods-alternative-cache/
Description: Alternative caching engine for Pods for large sites on hosts with hard limits on how much you can store in the object cache
Version: 2.1.0
Author: Pods Framework Team
Author URI: https://pods.io/
*/

define( 'PODS_ALT_CACHE_VERSION', '2.1.0' );
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

	if ( in_array( PODS_ALT_CACHE_TYPE, array( 'file', 'db', 'memcached' ), true ) ) {
		$cache_type = PODS_ALT_CACHE_TYPE;
	}

	$pods_alternative_cache = new Pods_Alternative_Cache( $cache_type );

	register_activation_hook( __FILE__, array( $pods_alternative_cache, 'activate' ) );
	register_deactivation_hook( __FILE__, array( $pods_alternative_cache, 'deactivate' ) );
}

add_action( 'plugins_loaded', 'pods_alternative_cache_init', 5 );

/**
 * Determine if Pods Alt Cache debugging is enabled.
 *
 * @return bool
 */
function pods_alternative_cache_is_debug_enabled() {
	return defined( 'PODS_ALT_CACHE_DEBUG' ) && PODS_ALT_CACHE_DEBUG && ! empty( $_GET['altcache_debug'] );
}

/**
 * Log message to screen or WP_Papertrail_API.
 *
 * @param string $message Log message.
 * @param string $method  (via __METHOD__)
 * @param array  $args    List of arguments to send to the logger.
 * @param string $mode    Message mode.
 */
function pods_alternative_cache_log_message( $message, $method, $args = array(), $mode = 'notice' ) {
	if ( ! defined( 'PODS_ALT_CACHE_DEBUG' ) || ! PODS_ALT_CACHE_DEBUG ) {
		return;
	}

	if ( class_exists( 'WP_Papertrail_API' ) ) {
		$log_message = array(
			'msg' => $message,
		);

		$log_message = array_merge( $log_message, $args );

		WP_Papertrail_API::log( $log_message, str_replace( '::', '\\', $method ) );

		return;
	}

	$start = '<!--';
	$end   = '-->';

	if ( 'error' === $mode ) {
		$start .= 'ERROR: ';
	} elseif ( 'success' === $mode ) {
		$start .= 'SUCCESS: ';
	} else {
		$start .= 'NOTICE: ';
	}

	if ( pods_alternative_cache_is_debug_enabled() ) {
		$start = '<p>';
		$end   = '</p>';

		if ( 'error' === $mode ) {
			$start .= '<span style="color:red;font-weight:bold;">ERROR:</span> ';
		} elseif ( 'success' === $mode ) {
			$start .= '<span style="color:green;font-weight:bold;">SUCCESS:</span> ';
		} else {
			$start .= '<span style="font-weight:bold;">NOTICE:</span> ';
		}
	}

	$debug_args = '';

	if ( $args ) {
		$debug_args = ' <pre style="margin-left:40px;">' . var_export( $args, true ) . '</pre>';
	}

	echo $start . esc_html( '[' . $method . '] ' . $message ) . $debug_args . $end;
}

/**
 * Add support for cache debugging (to confirm writes are working as expected)
 */
function pods_alternative_cache_test_anon() {
	if ( ! pods_alternative_cache_is_debug_enabled() ) {
		return;
	}

	if ( ! empty( $_GET['altcache_debug_clear'] ) ) {
		pods_api()->cache_flush_pods();

		pods_alternative_cache_log_message( 'Flushed cache', __FUNCTION__ );
	}

	$rand = (int) time();

	$persist_check = '';

	if ( ! empty( $_GET['altcache_debug_check'] ) ) {
		$persist_check = sanitize_text_field( $_GET['altcache_debug_check'] );
	}

	$cache_key         = 'pods-alt-cache-test';
	$cache_group       = 'pods-alt-cache';
	$cache_persist_key = 'pods-alt-cache-persist';

	$stats = array(
		'rand'           => $rand,
		'non-persistent' => array(
			'pods-alt-cache'           => array(
				'before_set' => null,
				'set'        => null,
				'after_set'  => null,
				'pass'       => false,
			),
			'pods-alt-cache-transient' => array(
				'before_set' => null,
				'set'        => null,
				'after_set'  => null,
				'pass'       => false,
			),
			'pods-alt-cache-option'    => array(
				'before_set' => null,
				'set'        => null,
				'after_set'  => null,
				'pass'       => false,
			),
			'wp-object-cache'          => array(
				'before_set' => null,
				'set'        => null,
				'after_set'  => null,
				'pass'       => false,
			),
			'wp-transient-cache'       => array(
				'before_set' => null,
				'set'        => null,
				'after_set'  => null,
				'pass'       => false,
			),
		),
		'persistent'     => array(
			'pods-alt-cache'           => array(
				'before_set' => null,
				'set'        => null,
				'after_set'  => null,
				'pass'       => false,
			),
			'pods-alt-cache-transient' => array(
				'before_set' => null,
				'set'        => null,
				'after_set'  => null,
				'pass'       => false,
			),
			'pods-alt-cache-option'    => array(
				'before_set' => null,
				'set'        => null,
				'after_set'  => null,
				'pass'       => false,
			),
			'wp-object-cache'          => array(
				'before_set' => null,
				'set'        => null,
				'after_set'  => null,
				'pass'       => false,
			),
			'wp-transient-cache'       => array(
				'before_set' => null,
				'set'        => null,
				'after_set'  => null,
				'pass'       => false,
			),
		),
	);

	foreach ( $stats as $persist_type => $persist_stats ) {
		if ( 'rand' === $persist_type ) {
			continue;
		}

		$expiration = 300;

		if ( 'persistent' === $persist_type ) {
			$expiration = 0;
		}

		foreach ( $persist_stats as $cache_type => $stat ) {
			$cache_name = ucwords( str_replace( '-', ' ', $cache_type ) ) . ' (' . ucwords( str_replace( '-', ' ', $persist_type ) ) . ')';

			$before_set = '';
			$set        = '';
			$after_set  = '';

			$key   = $cache_key;
			$value = $stats['rand'];

			if ( 'persistent' === $persist_type ) {
				$key   = $cache_persist_key;
				$value = $persist_check;
			}

			if ( is_numeric( $value ) ) {
				$value = (int) $value;
			}

			if ( 'pods-alt-cache' === $cache_type ) {
				$before_set = pods_cache_get( $key, $cache_group );

				if ( $value ) {
					$set = pods_cache_set( $key, $value, $cache_group, $expiration );
				}
			} elseif ( 'pods-alt-cache-transient' === $cache_type ) {
				$before_set = pods_transient_get( $key );

				if ( $value ) {
					$set = pods_transient_set( $key, $value, $expiration );
				}
			} elseif ( 'pods-alt-cache-option' === $cache_type ) {
				$before_set = pods_option_cache_get( $key, $cache_group );

				if ( $value ) {
					$set = pods_option_cache_set( $key, $value, $expiration, $cache_group );
				}
			} elseif ( 'wp-object-cache' === $cache_type ) {
				$before_set = wp_cache_get( $key, $cache_group );

				if ( $value ) {
					$set = wp_cache_set( $key, $value, $cache_group, $expiration );
				}
			} elseif ( 'wp-transient-cache' === $cache_type ) {
				$before_set = get_transient( $key );

				if ( $value ) {
					$set = set_transient( $key, $value, $expiration );
				}
			}

			if ( is_numeric( $before_set ) ) {
				$before_set = (int) $before_set;
			}

			$stats[ $persist_type ][ $cache_type ]['before_set'] = $before_set;
			$stats[ $persist_type ][ $cache_type ]['set']        = $set;

			sleep( 1 );

			if ( 'pods-alt-cache' === $cache_type ) {
				$after_set = pods_cache_get( $key, $cache_group );
			} elseif ( 'pods-alt-cache-transient' === $cache_type ) {
				$after_set = pods_transient_get( $key );
			} elseif ( 'pods-alt-cache-option' === $cache_type ) {
				$after_set = pods_option_cache_get( $key, $cache_group );
			} elseif ( 'wp-object-cache' === $cache_type ) {
				$after_set = wp_cache_get( $key, $cache_group );
			} elseif ( 'wp-transient-cache' === $cache_type ) {
				$after_set = get_transient( $key );
			}

			if ( is_numeric( $after_set ) ) {
				$after_set = (int) $after_set;
			}

			$stats[ $persist_type ][ $cache_type ]['after_set'] = $after_set;

			if ( $value ) {
				if ( $value === $after_set ) {
					$stats[ $persist_type ][ $cache_type ]['pass'] = true;

					pods_alternative_cache_log_message( $cache_name . ' worked!', __FUNCTION__, array(), 'success' );
				} else {
					$stats[ $persist_type ][ $cache_type ]['pass'] = false;

					pods_alternative_cache_log_message( $cache_name . ' failed!', __FUNCTION__, array(), 'error' );
				}
			} else {
				pods_alternative_cache_log_message( $cache_name . ' persist check', __FUNCTION__ );
			}
		}
	}

	echo '<pre>';
	var_dump( $stats );
	echo '</pre>';

	die();
}

add_action( 'init', 'pods_alternative_cache_test_anon' );
