<?php

/**
 * Class Pods_Alternative_Cache_Storage
 */
class Pods_Alternative_Cache_Storage {

	/**
	 * @var array Cached values.
	 */
	public static $values = [];

	/**
	 * Setup storage type object
	 */
	public function __construct() {
		// Setup object options

	}

	/**
	 * Activate plugin routine
	 *
	 * @param boolean $network_wide Whether the action is network-wide
	 */
	public function activate( $network_wide = false ) {
		// Activate for storage type

	}

	/**
	 * Deactivate plugin routine
	 *
	 * @param boolean $network_wide Whether the action is network-wide
	 */
	public function deactivate( $network_wide = false ) {
		// Deactivate for storage type

	}

	/**
	 * Return cached value from storage object
	 *
	 * @param string $cache_key
	 * @param string $group
	 *
	 * @return mixed|null
	 */
	public function get_value( $cache_key, $group ) {
		return $this->fallback_set( null, $cache_key, null, $group );
	}

	/**
	 * Set fallback value in object cache.
	 *
	 * @param mixed  $return      Default return value.
	 * @param string $cache_key   Cache key name.
	 * @param mixed  $cache_value Cache data.
	 * @param string $group       Cache group name.
	 * @param int    $expire      Cache expiration.
	 *
	 * @return bool|mixed
	 */
	public function fallback_set( $return, $cache_key, $cache_value, $group, $expires = 0 ) {
		if ( defined( 'PODS_ALT_CACHE_FALLBACK' ) && ! PODS_ALT_CACHE_FALLBACK ) {
			return $return;
		}

		if ( ! isset( $GLOBALS['wp_object_cache'] ) || ! is_object( $GLOBALS['wp_object_cache'] ) ) {
			return $return;
		}

		if ( is_bool( $cache_key ) || empty( $cache_key ) ) {
			return $return;
		}

		$return = wp_cache_set( $cache_key, $cache_value, $group, $expires );

		if ( $return ) {
			$value_key = $group . '_' . $cache_key;

			self::$values[ $value_key ] = $cache_value;
		}

		return $return;
	}

	/**
	 * Set a cached value in storage object
	 *
	 * @param string $cache_key
	 * @param mixed  $value
	 * @param int    $expires
	 * @param string $group
	 *
	 * @return bool
	 */
	public function set_value( $cache_key, $cache_value, $expires, $group ) {
		return $this->fallback_set( false, $cache_key, $cache_value, $group, $expires );
	}

	/**
	 * Clear file cache
	 *
	 * @return bool
	 */
	public function clear() {
		return $this->fallback_clear( false );
	}

	/**
	 * Clear fallback data in object cache.
	 *
	 * @param mixed $return Default return value.
	 *
	 * @return bool|mixed
	 */
	public function fallback_clear( $return ) {
		if ( defined( 'PODS_ALT_CACHE_FALLBACK' ) && ! PODS_ALT_CACHE_FALLBACK ) {
			return $return;
		}

		if ( ! isset( $GLOBALS['wp_object_cache'] ) || ! is_object( $GLOBALS['wp_object_cache'] ) ) {
			return $return;
		}

		self::$values = [];

		return wp_cache_flush();
	}

	/**
	 * Get fallback value from object cache.
	 *
	 * @param mixed  $return    Default return value.
	 * @param string $cache_key Cache key name.
	 * @param string $group     Cache group name.
	 *
	 * @return bool|mixed
	 */
	public function fallback_get( $return, $cache_key, $group ) {
		if ( defined( 'PODS_ALT_CACHE_FALLBACK' ) && ! PODS_ALT_CACHE_FALLBACK ) {
			return $return;
		}

		if ( ! isset( $GLOBALS['wp_object_cache'] ) || ! is_object( $GLOBALS['wp_object_cache'] ) || ! wp_using_ext_object_cache() ) {
			return $return;
		}

		$found = false;

		$fallback = wp_cache_get( $cache_key, $group, false, $found );

		if ( ! $found || false === $fallback ) {
			return $return;
		}

		if ( $fallback !== $return ) {
			$value_key = $group . '_' . $cache_key;

			self::$values[ $value_key ] = $fallback;
		}

		return $fallback;
	}

}
