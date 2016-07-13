<?php
/**
 * Class Pods_Alternative_Cache
 */
class Pods_Alternative_Cache {

	/**
	 * Storage object in use
	 *
	 * @var Pods_Alternative_Cache_Storage|Pods_Alternative_Cache_File|Pods_Alternative_Cache_DB
	 */
	public $storage;

	/**
	 * Storage types available and their associated classes
	 *
	 * @var array
	 */
	public static $storage_types = array(
		'file'      => 'Pods_Alternative_Cache_File',
		'db'        => 'Pods_Alternative_Cache_DB',
		'memcached' => 'Pods_Alternative_Cache_Memcached',
	);

	public $last = '';
	public $last_key = '';

	/**
	 * Setup cache object based on storage type
	 *
	 * @param string $storage Storage type
	 */
	public function __construct( $storage = 'file' ) {

		// Filter storage types to allow for additional ones to be added
		self::$storage_types = apply_filters( 'pods_alternative_cache_storage_types', self::$storage_types, $storage );

		$this->storage = $this->load_storage_type( $storage );

		add_filter( 'pods_view_cache_alt_get', array( $this, 'has_value' ), 10, 5 );
		add_filter( 'pods_view_cache_alt_get_value', array( $this, 'get_value' ), 10, 5 );
		add_filter( 'pods_view_cache_alt_set', array( $this, 'set_check' ), 10, 7 );

	}

	/**
	 * Load and return the Storage object for the Storage type
	 *
	 * @param string $storage Storage type
	 *
	 * @return Pods_Alternative_Cache_Storage|Pods_Alternative_Cache_File|Pods_Alternative_Cache_DB
	 */
	public function load_storage_type( $storage ) {

		// If storage type not set, default to file storage
		if ( ! isset( self::$storage_types[ $storage ] ) ) {
			$storage = 'file';
		}

		$class = self::$storage_types[ $storage ];

		// If class does not exist, attempt to include
		if ( ! class_exists( $class ) ) {
			$storage_type_path = PODS_ALT_CACHE_DIR . 'classes/' . str_replace( '_', '/', $class ) . '.php';

			// Support our default storage types, otherwise, allow for third-party autoloaded classes
			if ( file_exists( $storage_type_path ) ) {
				include_once $storage_type_path;
			}
		}

		return new $class();

	}

	/**
	 * Activate plugin routine
	 *
	 * @param boolean $network_wide Whether the action is network-wide
	 */
	public function activate( $network_wide = false ) {

		wp_cache_flush();

		foreach ( self::$storage_types as $storage => $class ) {// If storage type not set, default to file storage
			if ( $class === get_class( $this->storage ) ) {
				continue;
			}

			$storage_obj = $this->load_storage_type( $storage );

			$storage_obj->deactivate( $network_wide );
		}

		$this->storage->activate( $network_wide );

	}

	/**
	 * Deactivate plugin routine
	 *
	 * @param boolean $network_wide Whether the action is network-wide
	 */
	public function deactivate( $network_wide = false ) {

		wp_cache_flush();

		$this->storage->deactivate( $network_wide );

	}

	/**
	 * Check if there's a cache value to get
	 *
	 * @param bool   $_false
	 * @param string $cache_mode
	 * @param string $cache_key
	 * @param string $original_key
	 * @param string $group
	 *
	 * @return bool
	 */
	public function has_value( $_false, $cache_mode, $cache_key, $original_key, $group ) {

		if ( ! $_false && $this->is_enabled( $cache_mode, $cache_key ) ) {
			if ( current_user_can( 'manage_options' ) && isset( $_GET['pods_debug_cache'] ) && ( '1' === $_GET['pods_debug_cache'] || $cache_mode === $_GET['pods_debug_cache'] ) ) {
				$_false = true;
			} else {
				$value = $this->storage->get_value( $cache_key, $group );

				if ( null !== $value ) {
					$this->last     = $value;
					$this->last_key = $cache_key;

					$_false = true;
				}
			}
		}

		return $_false;

	}

	/**
	 * Return cached value
	 *
	 * @param mixed  $value
	 * @param string $cache_mode
	 * @param string $cache_key
	 * @param string $original_key
	 * @param string $group
	 *
	 * @return mixed|null
	 */
	public function get_value( $value, $cache_mode, $cache_key, $original_key, $group ) {

		if ( $this->is_enabled( $cache_mode, $cache_key ) ) {
			if ( current_user_can( 'manage_options' ) && isset( $_GET['pods_debug_cache'] ) && ( '1' === $_GET['pods_debug_cache'] || $cache_mode === $_GET['pods_debug_cache'] ) ) {
				$value = null;
			} elseif ( $this->last_key === $cache_key ) {
				$value = $this->last;
			} else {
				$value = $this->storage->get_value( $cache_key, $group );
			}
		}

		return $value;

	}

	/**
	 * Set a cached value
	 *
	 * @param bool   $_false
	 * @param string $cache_mode
	 * @param string $cache_key
	 * @param string $original_key
	 * @param mixed  $value
	 * @param int    $expires
	 * @param string $group
	 *
	 * @return bool
	 */
	public function set_check( $_false, $cache_mode, $cache_key, $original_key, $value, $expires, $group ) {

		if ( ! $_false && $this->is_enabled( $cache_mode, $cache_key ) ) {
			$_false = $this->storage->set_value( $cache_key, $value, $expires, $group );
		}

		return $_false;

	}

	/**
	 * Determine if Alt Cache is enabled and covered for the cache mode
	 *
	 * @param string $cache_mode
	 * @param string $cache_key
	 *
	 * @return bool
	 */
	public function is_enabled( $cache_mode, $cache_key ) {

		$supported_modes = array(
			'transient',
			'cache',
		);

		$supported_modes = apply_filters( 'pods_alternative_cache_supported_modes', $supported_modes, $cache_mode, $cache_key );

		$is_enabled = true;

		if ( ! PODS_ALT_CACHE ) {
			$is_enabled = false;
		} elseif ( ! in_array( $cache_mode, $supported_modes ) ) {
			$is_enabled = false;
		}

		return $is_enabled;

	}

}