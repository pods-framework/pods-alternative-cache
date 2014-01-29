<?php
/*
Plugin Name: Pods Alternative Cache
Plugin URI: http://pods.io/
Description: Alternative caching engine for Pods for large sites on hosts with hard limits on how much you can cache
Version: 1.0
Author: The Pods Framework Team
Author URI: http://pods.io/
*/

/**
 * Setup default constants
 */
function pods_alternative_cache_init() {
	if ( !defined( 'PODS_ALT_CACHE' ) ) {
		define( 'PODS_ALT_CACHE', true );
	}

	if ( !defined( 'PODS_ALT_CACHE_TYPE' ) ) {
		define( 'PODS_ALT_CACHE_TYPE', 'file' );
	}

	if ( !defined( 'PODS_ALT_FILE_CACHE_DIR' ) ) {
		define( 'PODS_ALT_FILE_CACHE_DIR', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'podscache' );
	}
}
add_action( 'plugins_loaded', 'pods_alternative_cache_init' );

/**
 * Setup DB cache table
 */
function pods_alternative_cache_db_sql() {

	global $wpdb;

	$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}podscache`" );

	$wpdb->query( "
		CREATE TABLE `{$wpdb->prefix}podscache` (
  			`cache_key` varchar(255) NOT NULL,
  			`cache_value` longtext NOT NULL,
  			`expiration` int(10) NOT NULL,
  			PRIMARY KEY (`cache_key`),
  			UNIQUE KEY `cache_key` (`cache_key`)
		)
	" );
}

/**
 * Get cached value from file cache
 *
 * @param string $cache_key
 *
 * @return mixed|null
 */
function pods_alternative_cache_file_get( $cache_key ) {

	$path = PODS_ALT_FILE_CACHE_DIR . DIRECTORY_SEPARATOR . md5( $cache_key ) . '.php';

	if ( !is_readable( $path ) ) {
		return null;
	}

	$fp = @fopen( $path, 'rb' );

	if ( !$fp ) {
		return null;
	}

	$expires_at = @fread( $fp, 4 );

	$data_unserialized = null;

	if ( false !== $expires_at ) {
		list( , $expires_at ) = @unpack( 'L', $expires_at );

		if ( 0 < (int) $expires_at && (int) $expires_at < time() ) {
			@fclose( $fp );

			// Data has expired, delete it
			pods_alternative_cache_file_set( $cache_key, '' );

			return $data_unserialized;
		}
		else {
			$data = '';

			while ( !@feof( $fp ) ) {
				$data .= @fread( $fp, 4096 );
			}

			$data = substr( $data, 14 );

			$data_unserialized = @unserialize( $data );
		}

	}

	@fclose( $fp );

	return $data_unserialized;

}

/**
 * Set cached value in file cache
 *
 * @param string $cache_key
 * @param mixed $cache_value
 * @param int $expires
 *
 * @return bool
 */
function pods_alternative_cache_file_set( $cache_key, $cache_value, $expires = 0 ) {

	$path = PODS_ALT_FILE_CACHE_DIR . DIRECTORY_SEPARATOR . md5( $cache_key ) . '.php';

	if ( !@is_dir( PODS_ALT_FILE_CACHE_DIR ) ) {
		if ( !@mkdir( PODS_ALT_FILE_CACHE_DIR, 0777 ) ) {
			return false;
		}
	}

	if ( '' === $cache_value ) {
		if ( true === $cache_key ) {
			return pods_alternative_cache_file_clear();
		}

		if ( !file_exists( $path ) ) {
			return false;
		}

		return @unlink( $path );
	}
	else {
		$fp = @fopen( $path, 'wb' );

		if ( !$fp ) {
			return false;
		}

		$expires_at = 0;

		if ( 0 < (int) $expires ) {
        		$expires_at = time() + (int) $expires;
		}

		@fputs( $fp, pack( 'L', $expires_at ) );
		@fputs( $fp, '<?php exit; ?>' );
		@fputs( $fp, @serialize( $cache_value ) );
		@fclose( $fp );
	}

	return true;

}

/**
 * Clear file cache
 *
 * @return bool
 */
function pods_alternative_cache_file_clear() {

	// @todo Clear path

	return true;

}

/**
 * Get cached value from DB cache
 *
 * @param string $cache_key
 *
 * @return mixed|null
 */
function pods_alternative_cache_db_get( $cache_key ) {

	global $wpdb;

	$cache = $wpdb->get_row( $wpdb->prepare( "
		SELECT `cache_value`, `expiration`
		FROM `{$wpdb->prefix}podscache`
		WHERE `cache_key` = %s LIMIT 1
	", $cache_key ) );

	$cache_value = null;

	if ( null !== $cache ) {
		$cache->expiration = (int) $cache->expiration;

		if ( 0 < $cache->expiration && $cache->expiration < time() ) {
			pods_alternative_cache_db_set( $cache_key, '' );
		}
		else {
			$cache_value = maybe_unserialize( $cache_value );
		}
	}
	elseif ( '' !== $cache ) {
		$cache_value = $cache;
	}

	return $cache_value;

}

/**
 * Set cached value in DB cache
 *
 * @param string $cache_key
 * @param mixed $cache_value
 * @param int $expires
 *
 * @return bool
 */
function pods_alternative_cache_db_set( $cache_key, $cache_value, $expires = 0 ) {

	global $wpdb;

	if ( '' === $cache_value ) {
		if ( true === $cache_key ) {
			return pods_alternative_cache_db_clear();
		}

		global $wpdb;

		$wpdb->query( $wpdb->prepare( "
			DELETE FROM `{$wpdb->prefix}podscache`
			WHERE `cache_key` = %s
		", $cache_key ) );
		$wpdb->query( "DELETE FROM `{$wpdb->prefix}podscache`" );
	}
	else {
		$cache_value = maybe_serialize( $cache_value );

		$expires_at = 0;

		if ( 0 < (int) $expires ) {
        		$expires_at = time() + (int) $expires;
		}

		$wpdb->query( $wpdb->prepare( "
			REPLACE INTO `{$wpdb->prefix}podscache`
			( `cache_key`, `cache_value`, `expiration` ) VALUES ( %s, %s, %d )
		", $cache_key, $cache_value, $expires_at ) );
	}

	return true;

}

/**
 * Clear DB cache
 *
 * @return bool
 */
function pods_alternative_cache_db_clear() {

	global $wpdb;

	$wpdb->query( "DELETE FROM `{$wpdb->prefix}podscache`" );

	return true;

}
add_action( 'pods_view_clear_transient', 'pods_alternative_cache_clear' );
add_action( 'pods_view_clear_cache', 'pods_alternative_cache_clear' );

global $pods_alternative_cache_last, $pods_alternative_cache_last_key;
$pods_alternative_cache_last = null;
$pods_alternative_cache_last_key = null;

/**
 * Check if there's a cache value to get
 *
 * @param bool $_false
 * @param string $cache_mode
 * @param string $cache_key
 * @param string $original_key
 * @param string $group
 *
 * @return bool
 */
function pods_alternative_cache_get_check( $_false, $cache_mode, $cache_key, $original_key, $group ) {

	global $pods_alternative_cache_last, $pods_alternative_cache_last_key;

	if ( pods_alternative_cache_is_enabled( $cache_mode, $cache_key ) ) {
		if ( 'file' == PODS_ALT_CACHE_TYPE ) {
			$value = pods_alternative_cache_file_get( $cache_key );
		}
		else {
			$value = pods_alternative_cache_db_get( $cache_key );
		}

		if ( null !== $value ) {
			$pods_alternative_cache_last = $value;
			$pods_alternative_cache_last_key = $cache_key;

			return true;
		}
	}

	return $_false;

}
add_filter( 'pods_view_cache_alt_get', 'pods_alternative_cache_get_check', 10, 5 );

/**
 * Return cached value
 *
 * @param mixed $value
 * @param string $cache_mode
 * @param string $cache_key
 * @param string $original_key
 * @param string $group
 *
 * @return mixed|null
 */
function pods_alternative_cache_get_value( $value, $cache_mode, $cache_key, $original_key, $group ) {

	global $pods_alternative_cache_last, $pods_alternative_cache_last_key;

	if ( pods_alternative_cache_is_enabled( $cache_mode, $cache_key ) ) {
		if ( $pods_alternative_cache_last_key === $cache_key ) {
			return $pods_alternative_cache_last;
		}

		if ( 'file' == PODS_ALT_CACHE_TYPE ) {
			return pods_alternative_cache_file_get( $cache_key );
		}

		return pods_alternative_cache_db_get( $cache_key );
	}

	return $value;

}
add_filter( 'pods_view_cache_alt_get_value', 'pods_alternative_cache_get_value', 10, 5 );

/**
 * Set a cached value
 *
 * @param bool $_false
 * @param string $cache_mode
 * @param string $cache_key
 * @param string $original_key
 * @param mixed $value
 * @param int $expires
 * @param string $group
 *
 * @return bool
 */
function pods_alternative_cache_set_check( $_false, $cache_mode, $cache_key, $original_key, $value, $expires, $group ) {

	if ( pods_alternative_cache_is_enabled( $cache_mode, $cache_key, $expires ) ) {
		if ( 'file' == PODS_ALT_CACHE_TYPE ) {
			pods_alternative_cache_file_set( $cache_key, $value, $expires );
		}
		else {
			pods_alternative_cache_set( $cache_key, $value, $expires );
		}

		return true;
	}


	return $_false;

}
add_filter( 'pods_view_cache_alt_set', 'pods_alternative_cache_set_check', 10, 7 );

/**
 * Determine if Alt Cache is enabled and covered for the cache mode
 *
 * @param string $cache_mode
 * @param string $cache_key
 * @param int $expires
 *
 * @return bool
 */
function pods_alternative_cache_is_enabled( $cache_mode, $cache_key, $expires = 0 ) {

	if ( !PODS_ALT_CACHE ) {
		return false;
	}
	elseif ( !in_array( $cache_mode, array( 'transient', 'cache' ) ) ) {
		return false;
	}

	return true;

}