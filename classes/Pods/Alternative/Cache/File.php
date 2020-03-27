<?php

/**
 * Class Pods_Alternative_Cache_File
 */
class Pods_Alternative_Cache_File extends Pods_Alternative_Cache_Storage {

	/**
	 * @var boolean Whether compatibility has been run
	 */
	public static $wpe_compatible = false;

	/**
	 * {@inheritdoc}
	 */
	public function __construct() {
		parent::__construct();

		// Set cache directory path
		if ( ! defined( 'PODS_ALT_FILE_CACHE_DIR' ) ) {
			define( 'PODS_ALT_FILE_CACHE_DIR', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'podscache' );
		}

		// Tell Pods 2.4.1+ that we can prime the Pods cache after flushing it
		if ( ! defined( 'PODS_PRELOAD_CONFIG_AFTER_FLUSH' ) ) {
			define( 'PODS_PRELOAD_CONFIG_AFTER_FLUSH', true );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function activate( $network_wide = false ) {
		$this->clear();

		require_once ABSPATH . 'wp-admin/includes/file.php';

		/**
		 * @var $wp_filesystem WP_Filesystem_Base
		 */ global $wp_filesystem;

		WP_Filesystem();

		if ( ! $wp_filesystem ) {
			return false;
		}

		if ( ! $wp_filesystem->is_dir( PODS_ALT_FILE_CACHE_DIR ) ) {
			if ( ! defined( 'FS_CHMOD_DIR' ) || ! $wp_filesystem->mkdir( PODS_ALT_FILE_CACHE_DIR, FS_CHMOD_DIR ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function clear() {
		self::$values = [];

		require_once ABSPATH . 'wp-admin/includes/file.php';

		/**
		 * @var $wp_filesystem WP_Filesystem_Base
		 */ global $wp_filesystem;

		WP_Filesystem();

		if ( ! $wp_filesystem ) {
			pods_alternative_cache_log_message( 'Filesystem not working', __METHOD__, [], 'error' );

			return $this->fallback_clear( false );
		}

		// Check if directory exists
		if ( ! $wp_filesystem->is_dir( PODS_ALT_FILE_CACHE_DIR ) ) {
			pods_alternative_cache_log_message( 'Pods cache dir does not exist', __METHOD__, [
				'PODS_ALT_FILE_CACHE_DIR' => PODS_ALT_FILE_CACHE_DIR,
			], 'error' );

			return $this->fallback_clear( false );
		}

		// Delete all files in directory
		$this->delete_files_in_directory( PODS_ALT_FILE_CACHE_DIR );

		pods_alternative_cache_log_message( 'Files deleted in Pods cache dir', __METHOD__, [
			'PODS_ALT_FILE_CACHE_DIR' => PODS_ALT_FILE_CACHE_DIR,
		] );

		return true;
	}

	/**
	 * Delete all files in a directory
	 *
	 * @param string|null $directory
	 */
	public function delete_files_in_directory( $directory = null ) {
		if ( null === $directory ) {
			$directory = PODS_ALT_FILE_CACHE_DIR;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		/**
		 * @var $wp_filesystem WP_Filesystem_Base
		 */ global $wp_filesystem;

		WP_Filesystem();

		if ( ! $wp_filesystem ) {
			pods_alternative_cache_log_message( 'Filesystem not working', __METHOD__, [
				'$directory' => $directory,
			], 'error' );

			return;
		}

		if ( ! $wp_filesystem->is_dir( $directory ) ) {
			pods_alternative_cache_log_message( 'Directory does not exist', __METHOD__, [
				'$directory' => $directory,
			], 'error' );

			return;
		}

		$file_list = $wp_filesystem->dirlist( $directory, false );

		foreach ( $file_list as $file ) {
			$file_path = $directory . DIRECTORY_SEPARATOR . $file['name'];

			// d = folder, f = file
			if ( 'd' === $file['type'] ) {
				// Delete folder
				$this->delete_files_in_directory( $file_path );
			} else {
				// Delete file
				$wp_filesystem->delete( $file_path );
			}

			if ( PODS_ALT_FILE_CACHE_DIR !== $directory ) {
				$wp_filesystem->rmdir( $directory );

				pods_alternative_cache_log_message( 'Directory removed', __METHOD__, [
					'$directory' => $directory,
				] );
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function deactivate( $network_wide = false ) {
		$this->clear();

		require_once ABSPATH . 'wp-admin/includes/file.php';

		/**
		 * @var $wp_filesystem WP_Filesystem_Base
		 */ global $wp_filesystem;

		WP_Filesystem();

		if ( ! $wp_filesystem ) {
			return false;
		}

		if ( $wp_filesystem->is_dir( PODS_ALT_FILE_CACHE_DIR ) ) {
			$wp_filesystem->rmdir( PODS_ALT_FILE_CACHE_DIR );
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_value( $cache_key, $group = '' ) {
		$value_key = $group . '_' . $cache_key;

		if ( isset( self::$values[ $value_key ] ) ) {
			return self::$values[ $value_key ];
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		/**
		 * @var $wp_filesystem WP_Filesystem_Base
		 */ global $wp_filesystem;

		WP_Filesystem();

		if ( ! $wp_filesystem ) {
			pods_alternative_cache_log_message( 'Filesystem not working, cannot get value', __METHOD__, [
				'$cache_key' => $cache_key,
				'$group'     => $group,
			], 'error' );

			return $this->fallback_get( false, $cache_key, $group );
		}

		$current_blog_id = (string) get_current_blog_id();

		// Force 0000123 format (like W3TC)
		$current_blog_id = str_pad( $current_blog_id, 6, '0', STR_PAD_LEFT );

		$md5 = md5( $cache_key . '/' . $group );

		$md5_path = substr( $md5, 0, 1 ) . DIRECTORY_SEPARATOR . substr( $md5, 1, 3 ) . DIRECTORY_SEPARATOR . substr( $md5, 4, 3 );
		$md5_file = substr( $md5, 7 ) . '.php';

		$path = $current_blog_id . DIRECTORY_SEPARATOR . $md5_path;

		$path = $this->get_path_for_file( $path );

		if ( ! $path ) {
			return $this->fallback_get( null, $cache_key, $group );
		}

		$path .= DIRECTORY_SEPARATOR . $md5_file;

		if ( ! $wp_filesystem->is_readable( $path ) ) {
			pods_alternative_cache_log_message( 'File path is not readable', __METHOD__, [
				'$cache_key' => $cache_key,
				'$group'     => $group,
				'$path'      => $path,
			], 'error' );

			return $this->fallback_get( null, $cache_key, $group );
		}

		pods_alternative_cache_log_message( 'File read', __METHOD__, [
			'$cache_key' => $cache_key,
			'$group'     => $group,
			'$path'      => $path,
		] );

		// @todo Figure out how to use WP_Filesystem to do fread() on limited byte range

		$contents = $wp_filesystem->get_contents( $path );

		$expires_at = substr( $contents, 0, 4 );

		if ( false === $expires_at || empty( $expires_at ) ) {
			return null;
		}

		$expires_at = unpack( 'L', $expires_at );
		$expires_at = (int) $expires_at[1];

		if ( 0 < (int) $expires_at && (int) $expires_at < time() ) {
			// Data has expired, delete it
			$this->set_value( $cache_key, '' );

			return null;
		}

		$data = substr( $contents, 20 );

		$data_unserialized = maybe_unserialize( $data );

		self::$values[ $value_key ] = $data_unserialized;

		return $data_unserialized;
	}

	/**
	 * Get the path to the cache directory for the file, attempt to create if it doesn't exist
	 *
	 * @param string $file  File path
	 * @param bool   $mkdir Whether to attempt to create the directory
	 *
	 * @return string|false The path, false if the path couldn't be created
	 */
	public function get_path_for_file( $file, $mkdir = false ) {
		$path = PODS_ALT_FILE_CACHE_DIR . DIRECTORY_SEPARATOR . trim( $file, DIRECTORY_SEPARATOR );

		$path_dir = $path;

		if ( false !== strpos( $path_dir, '.' ) ) {
			$path_dir = dirname( $path_dir );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		/**
		 * @var $wp_filesystem WP_Filesystem_Base
		 */ global $wp_filesystem;

		WP_Filesystem();

		if ( ! $wp_filesystem ) {
			pods_alternative_cache_log_message( 'Filesystem not working', __METHOD__, [
				'$file'     => $file,
				'$mkdir'    => $mkdir,
				'$path_dir' => $path_dir,
			], 'error' );

			$path = false;
		} elseif ( ! $wp_filesystem->is_dir( $path_dir ) ) {
			if ( $mkdir ) {
				$directories = explode( DIRECTORY_SEPARATOR, $path_dir );

				$dir_path = '';

				foreach ( $directories as $directory ) {
					$dir_path .= DIRECTORY_SEPARATOR . $directory;

					if ( ! $wp_filesystem->is_dir( $dir_path ) && ( ! defined( 'FS_CHMOD_DIR' ) || ! $wp_filesystem->mkdir( $dir_path, FS_CHMOD_DIR ) ) ) {
						$path = false;

						break;
					}
				}
			} else {
				$path = false;
			}
		}

		return $path;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set_value( $cache_key, $cache_value, $expires = 0, $group = '' ) {
		$value_key = $group . '_' . $cache_key;

		// Check if we've already cached this value.
		if ( '' !== $cache_value && isset( self::$values[ $value_key ] ) && self::$values[ $value_key ] === $cache_value ) {
			return true;
		}

		// WPE Compatibility for anonymous file writes
		$this->wpe_compatibility();

		require_once ABSPATH . 'wp-admin/includes/file.php';

		/**
		 * @var $wp_filesystem WP_Filesystem_Base
		 */ global $wp_filesystem;

		WP_Filesystem();

		if ( ! $wp_filesystem ) {
			pods_alternative_cache_log_message( 'Filesystem not working', __METHOD__, [
				'$cache_key' => $cache_key,
				'$expires'   => $expires,
				'$group'     => $group,
			], 'error' );

			return $this->fallback_set( false, $cache_key, $cache_value, $group, $expires );
		}

		$current_blog_id = (string) get_current_blog_id();

		// Force 0000123 format (like W3TC)
		$current_blog_id = str_pad( $current_blog_id, 6, '0', STR_PAD_LEFT );

		$md5 = md5( $cache_key . '/' . $group );

		$md5_path = substr( $md5, 0, 1 ) . DIRECTORY_SEPARATOR . substr( $md5, 1, 3 ) . DIRECTORY_SEPARATOR . substr( $md5, 4, 3 );
		$md5_file = substr( $md5, 7 ) . '.php';

		$path = $current_blog_id . DIRECTORY_SEPARATOR . $md5_path;

		$path = $this->get_path_for_file( $path, true );

		if ( ! $path ) {
			pods_alternative_cache_log_message( 'File path not found', __METHOD__, [
				'$path'      => $path,
				'$cache_key' => $cache_key,
				'$expires'   => $expires,
				'$group'     => $group,
			], 'error' );

			return $this->fallback_set( false, $cache_key, $cache_value, $group, $expires );
		}

		$path .= DIRECTORY_SEPARATOR . $md5_file;

		if ( '' === $cache_value ) {
			if ( true === $cache_key ) {
				return $this->clear();
			}

			if ( isset( self::$values[ $value_key ] ) ) {
				unset( self::$values[ $value_key ] );
			}

			if ( ! $wp_filesystem->is_file( $path ) ) {
				return $this->fallback_set( true, $cache_key, $cache_value, $group, $expires );
			}

			return $wp_filesystem->delete( $path );
		}

		self::$values[ $value_key ] = $cache_value;

		$expires_at = 0;

		if ( 0 < (int) $expires ) {
			$expires_at = time() + (int) $expires;
		}

		$contents = pack( 'L', $expires_at ) . PHP_EOL . '<?php exit; ?>' . PHP_EOL . maybe_serialize( $cache_value );

		$success = $wp_filesystem->put_contents( $path, $contents, FS_CHMOD_FILE );

		if ( ! $success ) {
			pods_alternative_cache_log_message( 'File cannot be written', __METHOD__, [
				'$cache_key' => $cache_key,
				'$expires'   => $expires,
				'$group'     => $group,
				'$path'      => $path,
			], 'error' );

			return $this->fallback_set( false, $cache_key, $cache_value, $group, $expires );
		}

		pods_alternative_cache_log_message( 'File written', __METHOD__, [
			'$cache_key' => $cache_key,
			'$expires'   => $expires,
			'$group'     => $group,
			'$path'      => $path,
		] );

		return true;
	}

	/**
	 * WPEngine support for anonymous file writes
	 */
	public function wpe_compatibility() {
		if ( ! self::$wpe_compatible && defined( 'WPE_APIKEY' ) && ! is_user_logged_in() ) {
			$wpe_cookie = 'wpe-auth';

			$cookie_value = md5( 'wpe_auth_salty_dog|' . WPE_APIKEY );

			if ( empty( $_COOKIE[ $wpe_cookie ] ) || $_COOKIE[ $wpe_cookie ] !== $cookie_value ) {
				$expire = 2 * DAY_IN_SECONDS;
				$expire += time();

				setcookie( $wpe_cookie, $cookie_value, $expire, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			}

			self::$wpe_compatible = true;
		}
	}

}
