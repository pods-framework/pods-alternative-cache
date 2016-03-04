<?php
/**
 * Class Pods_Alternative_Cache_DB
 */
class Pods_Alternative_Cache_DB extends Pods_Alternative_Cache_Storage {

	/**
	 * Table name for caching
	 */
	const TABLE = 'podscache';

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

		$table = $this->table();

		$tables = array(
			"
			CREATE TABLE `{$table}` (
				`cache_key` VARCHAR(255) NOT NULL,
				`cache_group` VARCHAR(255) NOT NULL,
				`cache_value` LONGTEXT NOT NULL,
				`expiration` INT(10) NOT NULL,
				PRIMARY KEY (`cache_key`),
				UNIQUE INDEX `cache_key_group` (`cache_key`, `cache_group`)
			)
		"
		);

		// Create / alter table handling
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		dbDelta( $tables );

	}

	/**
	 * Deactivate plugin routine
	 *
	 * @param boolean $network_wide Whether the action is network-wide
	 */
	public function deactivate( $network_wide = false ) {

		/**
		 * @var $wpdb wpdb
		 */
		global $wpdb;

		$table = $this->table();

		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );

	}

	/**
	 * Get the table name with prefix
	 *
	 * @return string
	 */
	public function table() {

		/**
		 * @var $wpdb wpdb
		 */
		global $wpdb;

		return $wpdb->prefix . self::TABLE;

	}

	/**
	 * Get the cache key with max char handling
	 *
	 * @param string|boolean $cache_key
	 *
	 * @return string|boolean
	 */
	public function cache_key_limited( $cache_key ) {

		// If string is larger than our column, md5 the portion that goes over
		if ( ! is_bool( $cache_key ) && 255 < strlen( $cache_key ) ) {
			$cache_key = substr( $cache_key, 0, 222 ) . md5( substr( $cache_key, 222 ) );
		}

		return $cache_key;

	}

	/**
	 * Get cached value from DB cache
	 *
	 * @param string|boolean $cache_key
	 * @param string         $group
	 *
	 * @return mixed|null
	 */
	public function get_value( $cache_key, $group = '' ) {

		/**
		 * @var $wpdb wpdb
		 */
		global $wpdb;

		// Enforce column limits
		$cache_key = $this->cache_key_limited( $cache_key );

		$table = $this->table();

		$sql = "
			SELECT `cache_value`, `expiration`
			FROM `{$table}`
			WHERE `cache_key` = %s AND `cache_group` = %s
			LIMIT 1
		";

		$cache = $wpdb->get_row( $wpdb->prepare( $sql, $cache_key, $group ) );

		$cache_value = null;

		if ( null !== $cache ) {
			$cache->expiration = (int) $cache->expiration;

			if ( 0 < $cache->expiration && $cache->expiration < time() ) {
				$this->set_value( $cache_key, '', 0, $group );
			} else {
				$cache_value = maybe_unserialize( $cache_value );
			}
		} elseif ( '' !== $cache ) {
			$cache_value = $cache;
		}

		return $cache_value;

	}

	/**
	 * Set cached value in DB cache
	 *
	 * @param string|boolean $cache_key
	 * @param mixed          $cache_value
	 * @param int            $expires
	 * @param string         $group
	 *
	 * @return bool
	 */
	public function set_value( $cache_key, $cache_value, $expires = 0, $group = '' ) {

		/**
		 * @var $wpdb wpdb
		 */
		global $wpdb;

		// Enforce column limits
		$cache_key = $this->cache_key_limited( $cache_key );

		$table = $this->table();

		if ( '' === $cache_value ) {
			if ( true === $cache_key ) {
				return $this->clear();
			}

			$sql = "
				DELETE FROM `{$table}`
				WHERE `cache_key` = %s AND `cache_group` = %s
			";

			$wpdb->query( $wpdb->prepare( $sql, $cache_key, $group ) );
		} else {
			$cache_value = maybe_serialize( $cache_value );

			$expires_at = 0;

			if ( 0 < (int) $expires ) {
				$expires_at = time() + (int) $expires;
			}

			$sql = "
				REPLACE INTO `{$table}`
				( `cache_key`, `cache_group`, `cache_value`, `expiration` )
				VALUES ( %s, %s, %s, %d, %d )
			";

			$wpdb->query( $wpdb->prepare( $sql, $cache_key, $group, $cache_value, $expires_at ) );
		}

		return true;

	}

	/**
	 * Clear DB cache
	 *
	 * @return bool
	 */
	public function clear() {

		/**
		 * @var $wpdb wpdb
		 */
		global $wpdb;

		$table = $this->table();

		$wpdb->query( "TRUNCATE `{$table}`" );

		return true;

	}

}