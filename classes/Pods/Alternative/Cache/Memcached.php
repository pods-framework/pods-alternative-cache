<?php
/**
 * Class Pods_Alternative_Cache_File
 */
class Pods_Alternative_Cache_Memcached extends Pods_Alternative_Cache_Storage {

	/**
	 * @var Memcached
	 */
	private $_memcache;

	/**
	 * Namespace of keys
	 *
	 * @var string
	 */
	private $_namespace = 'pods_';

	/**
	 * Setup storage type object
	 */
	public function __construct() {

		// Connect to the server
		$this->_connect();

	}

	/**
	 * Activate plugin routine
	 *
	 * @param boolean $network_wide Whether the action is network-wide
	 */
	public function activate( $network_wide = false ) {

		$this->clear();

	}

	/**
	 * Deactivate plugin routine
	 *
	 * @param boolean $network_wide Whether the action is network-wide
	 */
	public function deactivate( $network_wide = false ) {

		$this->clear();

	}

	/**
	 * Get cached value from file cache
	 *
	 * @param string $cache_key
	 * @param string $group
	 *
	 * @return mixed|null
	 */
	public function get_value( $cache_key, $group = '' ) {

		if ( ! $this->_memcache ) {
			return null;
		}

		// Get the cache key
		$cache_key = $this->_get_cache_key( $cache_key, $group );

		// Get the value of the cache
		$data = $this->_memcache->get( $cache_key );

		return $data;

	}

	/**
	 * Set cached value in file cache
	 *
	 * @param string|boolean $cache_key
	 * @param mixed          $cache_value
	 * @param int            $expires
	 * @param string         $group
	 *
	 * @return bool
	 */
	public function set_value( $cache_key, $cache_value, $expires = 0, $group = '' ) {

		if ( ! $this->_memcache ) {
			return false;
		}

		// Get the cache key
		$cache_key = $this->_get_cache_key( $cache_key, $group );

		// Set the experie time
		$expires_at = 0;

		if ( 0 < (int) $expires ) {
			$expires_at = time() + (int) $expires;
		}

		// Return true or false based on the output of adding the cache
		return $this->_memcache->set( $cache_key, $cache_value, $expires_at );

	}

	/**
	 * Clear all items in memcache
	 *
	 * @return bool
	 */
	public function clear() {

		if ( ! $this->_memcache ) {
			return;
		}

		// Get all memcached keys
		$keys = $this->_memcache->getAllKeys();

		// Loop over the keys
		foreach ( $keys as $index => $key ) {
			// If the namespace exists
			if ( false !== strpos( $key, $this->_namespace ) ) {
				// Then delete the item
				$this->_memcache->delete( $key );

			}
		}

	}

	/**
	 * Get the cache key
	 *
	 * @param string|boolean $cache_key
	 * @param string         $group
	 *
	 * @return string
	 */
	private function _get_cache_key( $cache_key, $group = '' ) {

		$current_blog_id = (string) get_current_blog_id();
		$current_blog_id = str_pad( $current_blog_id, 6, '0', STR_PAD_LEFT );

		$cache_key       = $this->_namespace . md5( $cache_key . '_' . $group );

		return $cache_key;

	}

	/**
	 * Connect to the Memcached server
	 */
	private function _connect() {

		$this->_memcache = new Memcached;

		// Get the server and port that defined, use the default configurations if it don't exist

		$port = 11211;

		if ( defined( 'PODS_ALT_CACHE_MEMCACHED_PORT' ) && PODS_ALT_CACHE_MEMCACHED_PORT && is_integer(PODS_ALT_CACHE_MEMCACHED_PORT) ) {
			$port = PODS_ALT_CACHE_MEMCACHED_PORT;
		}

		$server = 'localhost';

		if ( defined( 'PODS_ALT_CACHE_MEMCACHED_SERVER' ) && PODS_ALT_CACHE_MEMCACHED_SERVER ) {
			$server = PODS_ALT_CACHE_MEMCACHED_SERVER;
		}

		$this->_memcache->addServer( $server, $port ) or $this->_memcache = null;

	}

}
