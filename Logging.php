<?php
/**
 * Class for logging events and errors
 *
 * @package     ChurchPlugins
 * @subpackage  Logging
 * @copyright   Copyright (c) 2024, ChurchPlugins
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.1.0
 */

namespace ChurchPlugins;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Logging Class
 *
 * A general use class for logging events and errors.
 *
 * @since 1.1.0
 * @since 3.0 - Updated to work with the new tables and classes as part of the migration to custom tables.
 */
abstract class Logging {

	/**
	 * Whether the debug log file is writable or not.
	 *
	 * @var bool
	 */
	public $is_writable = true;

	/**
	 * Filename of the debug log.
	 *
	 * @var string
	 */
	private $filename = '';

	/**
	 * File path to the debug log.
	 *
	 * @var string
	 */
	private $file = '';

	public $id = '';

	/**
	 * Set up the Logging Class
	 *
	 * @since 1.1.0
	 */
	public function __construct( $id = 'churchplugins' ) {
		$this->id = $id;

		add_action( 'plugins_loaded', array( $this, 'setup_log_file' ), 8 );
	}

	public abstract function is_debug_mode();

	/** File System ***********************************************************/

	/**
	 * Sets up the log file if it is writable
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function setup_log_file() {
		$this->init_fs();

		$upload_dir     = wp_upload_dir();
		$this->filename = wp_hash( home_url( '/' ) ) . '-' . $this->id . '.log';
		$this->file     = trailingslashit( $upload_dir['basedir'] ) . $this->filename;

		if ( ! $this->get_fs()->is_writable( $upload_dir['basedir'] ) ) {
			$this->is_writable = false;
		}
	}

	/**
	 * Initialize the WordPress file system
	 *
	 * @since 1.1.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem
	 */
	private function init_fs() {
		global $wp_filesystem;

		if ( ! empty( $wp_filesystem ) ) {
			return;
		}

		// Include the file-system
		require_once ABSPATH . 'wp-admin/includes/file.php';

		// Initialize the file system
		WP_Filesystem();
	}

	/**
	 * Get the WordPress file-system
	 *
	 * @since 1.1.0
	 *
	 * @return WP_Filesystem_Base
	 */
	private function get_fs() {
		return ! empty( $GLOBALS['wp_filesystem'] )
			? $GLOBALS['wp_filesystem']
			: false;
	}

	/**
	 * Log message to file.
	 *
	 * @access public
	 * @since 1.1.0
	 *
	 * @param string $message Message to insert in the log.
	 */
	public function log_to_file( $message = '' ) {
		$message = date( 'Y-n-d H:i:s' ) . ' - ' . $message . "\r\n";
		$this->write_to_log( $message );
	}

	/**
	 * Return the location of the log file that Logging will use.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function get_log_file_path() {
		return $this->file;
	}

	/**
	 * Retrieve the log data.
	 *
	 * @access public
	 * @since 1.1.0
	 *
	 * @return string Log data.
	 */
	public function get_file_contents() {
		return $this->get_file();
	}

	/**
	 * Retrieve the file data is written to
	 *
	 * @access protected
	 * @since 1.1.0
	 *
	 * @return string File data.
	 */
	protected function get_file() {
		$file = '';

		if ( $this->get_fs()->exists( $this->file ) ) {
			if ( ! $this->get_fs()->is_writable( $this->file ) ) {
				$this->is_writable = false;
			}

			$file = $this->get_fs()->get_contents( $this->file );
		} else {
			$this->get_fs()->put_contents( $this->file, '' );
			$this->get_fs()->chmod( $this->file, 0664 );
		}

		return $file;
	}

	/**
	 * Write the log message.
	 *
	 * @access protected
	 * @since 1.1.0
	 */
	protected function write_to_log( $message = '' ) {
		file_put_contents( $this->file, $message, FILE_APPEND );
	}

	/**
	 * Delete the log file or removes all contents in the log file if we cannot delete it.
	 *
	 * @access public
	 * @since 1.1.0
	 *
	 * @return bool True if the log was cleared, false otherwise.
	 */
	public function clear_log_file() {
		$this->get_fs()->delete( $this->file );

		if ( $this->get_fs()->exists( $this->file ) ) {

			// It's still there, so maybe server doesn't have delete rights
			$this->get_fs()->chmod( $this->file, 0664 );
			$this->get_fs()->delete( $this->file );

			// See if it's still there...
			if ( $this->get_fs()->exists( $this->file ) ) {
				$this->get_fs()->put_contents( $this->file, '' );
			}
		}

		$this->file = '';
		return true;
	}

	/** functions ************************/

	/**
	 * Log a message to the log file
	 *
	 * @param $message
	 * @param $force
	 *
	 * @since  1.1.0
	 *
	 * @author Tanner Moushey, 6/9/24
	 */
	public function log( $message = '', $force = false ) {
		if ( $this->is_debug_mode() || $force ) {

			if ( function_exists( 'mb_convert_encoding' ) ) {
				$message = mb_convert_encoding( $message, 'UTF-8' );
			}

			$this->log_to_file( $message );
		}
	}

	/**
	 * Log an exception
	 *
	 * @param $exception
	 *
	 * @since  1.1.0
	 *
	 * @author Tanner Moushey, 6/9/24
	 */
	public function log_exception( $exception ) {

		$label = get_class( $exception );

		if ( $exception->getCode() ) {

			$message = sprintf( '%1$s: %2$s - %3$s',
				$label,
				$exception->getCode(),
				$exception->getMessage()
			);

		} else {

			$message = sprintf( '%1$s: %2$s',
				$label,
				$exception->getMessage()
			);

		}

		self::log( $message );
	}

}