<?php
/**
 * Class Pods_Alternative_Cache_Storage
 */
class Pods_Alternative_Cache_Storage {

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

		return null;

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
	public function set_value( $cache_key, $value, $expires, $group ) {

		return false;

	}

}