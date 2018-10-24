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
		 */
		global $wp_filesystem;

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
	public function deactivate( $network_wide = false ) {

		$this->clear();

		require_once ABSPATH . 'wp-admin/includes/file.php';

		/**
		 * @var $wp_filesystem WP_Filesystem_Base
		 */
		global $wp_filesystem;

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
		 */
		global $wp_filesystem;

		WP_Filesystem();

		if ( ! $wp_filesystem ) {
			if ( defined( 'PODS_ALT_CACHE_DEBUG' ) && PODS_ALT_CACHE_DEBUG && class_exists( 'WP_Papertrail_API' ) ) {
				\WP_Papertrail_API::log( array(
					'msg'        => 'Filesystem not working',
					'$cache_key' => $cache_key,
					'$group'     => $group,
				), str_replace( '::', '\\', __METHOD__ ) );
			} else {
				echo '<!--' . esc_html( __CLASS__ ) . ': Filesystem not working, cannot get value-->' . "\n";
			}

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
			if ( defined( 'PODS_ALT_CACHE_DEBUG' ) && PODS_ALT_CACHE_DEBUG && class_exists( 'WP_Papertrail_API' ) ) {
				\WP_Papertrail_API::log( array(
					'msg'        => 'File not readable',
					'$cache_key' => $cache_key,
					'$group'     => $group,
					'$path'      => $path,
				), str_replace( '::', '\\', __METHOD__ ) );
			} else {
				echo '<!--' . esc_html( __CLASS__ ) . ': Path is not readable (' . esc_html( $path ) . ')-->' . "\n";
			}

			return $this->fallback_get( null, $cache_key, $group );
		}

		if ( defined( 'PODS_ALT_CACHE_DEBUG' ) && PODS_ALT_CACHE_DEBUG ) {
			if ( class_exists( 'WP_Papertrail_API' ) ) {
				\WP_Papertrail_API::log( array(
					'msg'        => 'File read',
					'$cache_key' => $cache_key,
					'$group'     => $group,
					'$path'      => $path,
				), str_replace( '::', '\\', __METHOD__ ) );
			} else {
				echo '<!--' . esc_html( __CLASS__ ) . ': File read (' . esc_html( $path ) . ')-->' . "\n";
			}
		}

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
		 */
		global $wp_filesystem;

		WP_Filesystem();

		if ( ! $wp_filesystem ) {
			if ( defined( 'PODS_ALT_CACHE_DEBUG' ) && PODS_ALT_CACHE_DEBUG && class_exists( 'WP_Papertrail_API' ) ) {
				\WP_Papertrail_API::log( array(
					'msg'        => 'Filesystem not working',
					'$cache_key' => $cache_key,
					'$expires'   => $expires,
					'$group'     => $group,
				), str_replace( '::', '\\', __METHOD__ ) );
			}

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
			if ( defined( 'PODS_ALT_CACHE_DEBUG' ) && PODS_ALT_CACHE_DEBUG && class_exists( 'WP_Papertrail_API' ) ) {
				\WP_Papertrail_API::log( array(
					'msg'        => 'File path not found',
					'$path'      => $path,
					'$cache_key' => $cache_key,
					'$expires'   => $expires,
					'$group'     => $group,
				), str_replace( '::', '\\', __METHOD__ ) );
			}

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
			if ( defined( 'PODS_ALT_CACHE_DEBUG' ) && PODS_ALT_CACHE_DEBUG ) {
				if ( class_exists( 'WP_Papertrail_API' ) ) {
					\WP_Papertrail_API::log( array(
						'msg'        => 'File cannot be written',
						'$cache_key' => $cache_key,
						'$expires'   => $expires,
						'$group'     => $group,
						'$path'      => $path,
					), str_replace( '::', '\\', __METHOD__ ) );
				} else {
					echo '<!--' . esc_html( __CLASS__ ) . ': File cannot be written (' . esc_html( $path ) . ')-->' . "\n";
				}
			}

			return $this->fallback_set( false, $cache_key, $cache_value, $group, $expires );
		}

		if ( defined( 'PODS_ALT_CACHE_DEBUG' ) && PODS_ALT_CACHE_DEBUG ) {
			if ( class_exists( 'WP_Papertrail_API' ) ) {
				\WP_Papertrail_API::log( array(
					'msg'        => 'File written',
					'$cache_key' => $cache_key,
					'$expires'   => $expires,
					'$group'     => $group,
					'$path'      => $path,
				), str_replace( '::', '\\', __METHOD__ ) );
			} else {
				echo '<!--' . esc_html( __CLASS__ ) . ': File written (' . esc_html( $path ) . ')-->' . "\n";
			}
		}

		return true;

	}

	/**
	 * {@inheritdoc}
	 */
	public function clear() {

		self::$values = array();

		require_once ABSPATH . 'wp-admin/includes/file.php';

		/**
		 * @var $wp_filesystem WP_Filesystem_Base
		 */
		global $wp_filesystem;

		WP_Filesystem();

		if ( ! $wp_filesystem ) {
			if ( defined( 'PODS_ALT_CACHE_DEBUG' ) && PODS_ALT_CACHE_DEBUG && class_exists( 'WP_Papertrail_API' ) ) {
				\WP_Papertrail_API::log( array( 'msg' => 'Filesystem not working' ), str_replace( '::', '\\', __METHOD__ ) );
			}

			return $this->fallback_clear( false );
		}

		// Check if directory exists
		if ( ! $wp_filesystem->is_dir( PODS_ALT_FILE_CACHE_DIR ) ) {
			if ( defined( 'PODS_ALT_CACHE_DEBUG' ) && PODS_ALT_CACHE_DEBUG && class_exists( 'WP_Papertrail_API' ) ) {
				\WP_Papertrail_API::log( array(
					'msg'                     => 'Pods cache dir does not exist',
					'PODS_ALT_FILE_CACHE_DIR' => PODS_ALT_FILE_CACHE_DIR,
				), str_replace( '::', '\\', __METHOD__ ) );
			}

			return $this->fallback_clear( false );
		}

		// Delete all files in directory
		$this->delete_files_in_directory( PODS_ALT_FILE_CACHE_DIR );

		if ( defined( 'PODS_ALT_CACHE_DEBUG' ) && PODS_ALT_CACHE_DEBUG && class_exists( 'WP_Papertrail_API' ) ) {
			\WP_Papertrail_API::log( array(
				'msg'                     => 'Files deleted in Pods cache dir',
				'PODS_ALT_FILE_CACHE_DIR' => PODS_ALT_FILE_CACHE_DIR,
			), str_replace( '::', '\\', __METHOD__ ) );
		}

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
		 */
		global $wp_filesystem;

		WP_Filesystem();

		if ( ! $wp_filesystem ) {
			if ( defined( 'PODS_ALT_CACHE_DEBUG' ) && PODS_ALT_CACHE_DEBUG && class_exists( 'WP_Papertrail_API' ) ) {
				\WP_Papertrail_API::log( array( 'msg' => 'Filesystem not working' ), str_replace( '::', '\\', __METHOD__ ) );
			}

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
		 */
		global $wp_filesystem;

		WP_Filesystem();

		if ( ! $wp_filesystem ) {
			if ( defined( 'PODS_ALT_CACHE_DEBUG' ) && PODS_ALT_CACHE_DEBUG && class_exists( 'WP_Papertrail_API' ) ) {
				\WP_Papertrail_API::log( array(
					'msg' => 'Filesystem not working',
				), str_replace( '::', '\\', __METHOD__ ) );
			}

			return;
		}

		if ( ! $wp_filesystem->is_dir( $directory ) ) {
			if ( defined( 'PODS_ALT_CACHE_DEBUG' ) && PODS_ALT_CACHE_DEBUG && class_exists( 'WP_Papertrail_API' ) ) {
				\WP_Papertrail_API::log( array(
					'msg'        => 'Directory does not exist',
					'$directory' => $directory,
				), str_replace( '::', '\\', __METHOD__ ) );
			}

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

				if ( defined( 'PODS_ALT_CACHE_DEBUG' ) && PODS_ALT_CACHE_DEBUG && class_exists( 'WP_Papertrail_API' ) ) {
					\WP_Papertrail_API::log( array(
						'msg'        => 'Directory removed',
						'$directory' => $directory,
					), str_replace( '::', '\\', __METHOD__ ) );
				}
			}
		}

	}

}
