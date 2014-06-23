<?php
/**
 * Class Pods_Alternative_Cache_File
 */
class Pods_Alternative_Cache_File extends Pods_Alternative_Cache_Storage {

	/**
	 * Setup storage type object
	 */
	public function __construct() {

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
	 * Activate plugin routine
	 *
	 * @param boolean $network_wide Whether the action is network-wide
	 */
	public function activate( $network_wide = false ) {

		$this->clear();

		if ( ! is_dir( PODS_ALT_FILE_CACHE_DIR ) ) {
			if ( ! mkdir( PODS_ALT_FILE_CACHE_DIR, 0775 ) ) {
				return false;
			}
		}

	}

	/**
	 * Deactivate plugin routine
	 *
	 * @param boolean $network_wide Whether the action is network-wide
	 */
	public function deactivate( $network_wide = false ) {

		$this->clear();

		if ( is_dir( PODS_ALT_FILE_CACHE_DIR ) ) {
			rmdir( PODS_ALT_FILE_CACHE_DIR );
		}

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

		$current_blog_id = (string) get_current_blog_id();

		// Force 0000123 format (like W3TC)
		$current_blog_id = str_pad( $current_blog_id, 6, '0', STR_PAD_LEFT );

		$md5 = md5( $cache_key . '/' . $group );

		$md5_path = substr( $md5, 0, 1 ) . DIRECTORY_SEPARATOR . substr( $md5, 1, 3 ) . DIRECTORY_SEPARATOR . substr( $md5, 4, 3 );

		$path = $current_blog_id . DIRECTORY_SEPARATOR . $md5_path;

		$path = $this->get_path_for_file( $path );

		if ( ! $path ) {
			return null;
		}
		else {
			$path .= DIRECTORY_SEPARATOR . substr( $md5, 7 ) . '.php';
		}

		if ( ! is_readable( $path ) ) {
			return null;
		}

		$fp = fopen( $path, 'rb' );

		if ( !$fp ) {
			return null;
		}

		$expires_at = fread( $fp, 4 );

		$data_unserialized = null;

		if ( false !== $expires_at ) {
			list( , $expires_at ) = unpack( 'L', $expires_at );

			if ( 0 < (int) $expires_at && (int) $expires_at < time() ) {
				fclose( $fp );

				// Data has expired, delete it
				$this->set_value( $cache_key, '' );

				return $data_unserialized;
			}
			else {
				$data = '';

				while ( ! feof( $fp ) ) {
					$data .= fread( $fp, 4096 );
				}

				$data = substr( $data, 14 );

				$data_unserialized = @unserialize( $data );
			}

		}

		fclose( $fp );

		return $data_unserialized;

	}

	/**
	 * Set cached value in file cache
	 *
	 * @param string $cache_key
	 * @param mixed $cache_value
	 * @param int $expires
	 * @param string $group
	 *
	 * @return bool
	 */
	public function set_value( $cache_key, $cache_value, $expires = 0, $group = '' ) {

		$current_blog_id = (string) get_current_blog_id();

		// Force 0000123 format (like W3TC)
		$current_blog_id = str_pad( $current_blog_id, 6, '0', STR_PAD_LEFT );

		$md5 = md5( $cache_key . '/' . $group );

		$md5_path = substr( $md5, 0, 1 ) . DIRECTORY_SEPARATOR . substr( $md5, 1, 3 ) . DIRECTORY_SEPARATOR . substr( $md5, 4, 3 );

		$path = $current_blog_id . DIRECTORY_SEPARATOR . $md5_path;

		$path = $this->get_path_for_file( $path, true );

		if ( ! $path ) {
			return false;
		}
		else {
			$path .= DIRECTORY_SEPARATOR . substr( $md5, 7 ) . '.php';
		}

		if ( '' === $cache_value ) {
			if ( true === $cache_key ) {
				return $this->clear();
			}

			if ( ! file_exists( $path ) ) {
				return false;
			}

			return unlink( $path );
		}
		else {
			$expires_at = 0;

			if ( 0 < (int) $expires ) {
				$expires_at = time() + (int) $expires;
			}

			$fp = fopen( $path, 'w' );

			if ( !$fp ) {
				$written = file_put_contents( $path, pack( 'L', $expires_at ) . PHP_EOL . '<?php exit; ?>' . PHP_EOL . @serialize( $cache_value ), LOCK_EX );

				if ( ! $fp && ! $written ) {
					return false;
				}
			}

			fputs( $fp, pack( 'L', $expires_at ) );
			fputs( $fp, '<?php exit; ?>' );
			fputs( $fp, @serialize( $cache_value ) );
			fclose( $fp );
		}

		return true;

	}

	/**
	 * Clear file cache
	 *
	 * @return bool
	 */
	public function clear() {

		// Check if directory exists
		if ( ! is_dir( PODS_ALT_FILE_CACHE_DIR ) ) {
			return false;
		}

		// Delete all files in directory
		$this->delete_files_in_directory( PODS_ALT_FILE_CACHE_DIR );

		return true;

	}

	/**
	 * Get the path to the cache directory for the file, attempt to create if it doesn't exist
	 *
	 * @param string $file File path
	 * @param bool $mkdir Whether to attempt to create the directory
	 *
	 * @return string|bool The path, false if the path couldn't be created
	 */
	public function get_path_for_file( $file, $mkdir = false ) {

		$path = PODS_ALT_FILE_CACHE_DIR . DIRECTORY_SEPARATOR . $file;

		if ( ! is_dir( dirname( $path ) ) ) {
			if ( $mkdir ) {
				$directories = explode( DIRECTORY_SEPARATOR, $file );

				array_unshift( $directories, PODS_ALT_FILE_CACHE_DIR );

				$dir_path = '';

				foreach ( $directories as $directory ) {
					$dir_path .= DIRECTORY_SEPARATOR . $directory;

					if ( ! is_dir( $dir_path ) && ! mkdir( $dir_path, 0775 ) ) {
						$path = false;

						break;
					}
				}
			}
			else {
				$path = false;
			}
		}

		return $path;

	}

	/**
	 * Delete all files in a directory
	 *
	 * @param string $directory
	 */
	public function delete_files_in_directory( $directory = null ) {

		if ( null === $directory ) {
			$directory = PODS_ALT_FILE_CACHE_DIR;
		}

		if ( $dir = opendir( $directory ) ) {
			while ( false !== ( $file = readdir( $dir ) ) ) {
				if ( in_array( $file, array( '.', '..' ) ) ) {
					continue;
				}

				$file_path = $directory . DIRECTORY_SEPARATOR . $file;

				if ( is_file( $file_path ) ) {
					unlink( $file_path );
				}
				elseif ( is_dir( $file_path ) ) {
					$this->delete_files_in_directory( $file_path );
				}
			}

			closedir( $dir );

			if ( PODS_ALT_FILE_CACHE_DIR !== $directory ) {
				rmdir( $directory );
			}
		}

	}

}