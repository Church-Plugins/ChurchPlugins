<?php
/**
 * Batch Import Class
 *
 * This is the base class for all batch import methods. Each data import type (customers, payments, etc) extend this class
 *
 * @package     ChurchPlugins
 * @subpackage  Admin/Import
 * @since       1.0.6
 */

namespace ChurchPlugins\Admin\Import;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * BatchImport Class
 *
 * @since 1.0.6
 */
class BatchImport {

	/**
	 * The file being imported
	 *
	 * @since 1.0.6
	 */
	public $file;

	/**
	 * The current row we are looking at
	 *
	 * @since 1.0.6
	 */
	public $row;

	/**
	 * The parsed CSV file being imported
	 *
	 * @since 1.0.6
	 */
	public $csv;

	/**
	 * Total rows in the CSV file
	 *
	 * @since 1.0.6
	 */
	public $total;

	/**
	 * The current step being processed
	 *
	 * @since 1.0.6
	 */
	public $step;

	/**
	 * The number of items to process per step
	 *
	 * @since 1.0.6
	 */
	public $per_step = 20;

	/**
	 * The capability required to import data
	 *
	 * @since 1.0.6
	 */
	public $capability_type = 'manage_options';

	/**
	 * Is the import file empty
	 *
	 * @since 1.0.6
	 */
	public $is_empty = false;

	/**
	 * Map of CSV columns > database fields
	 *
	 * @since 1.0.6
	 */
	public $field_mapping = array();

	/**
	 * Get things started
	 *
	 * @param $_step int The step to process
	 * @since 1.0.6
	 */
	public function __construct( $_file = '', $_step = 1 ) {

		$this->step  = $_step;
		$this->file  = $_file;
		$this->done  = false;
		$this->csv   = $this->get_csv_file( $this->file );
		$this->total = count( $this->csv );
		$this->init();

	}

	/**
	 * Initialize the updater. Runs after import file is loaded but before any processing is done.
	 *
	 * @since 1.0.6
	 * @return void
	 */
	public function init() {}

	/**
	 * Can we import?
	 *
	 * @since 1.0.6
	 * @return bool Whether we can iport or not
	 */
	public function can_import() {
		return (bool) apply_filters( 'cp_import_capability', current_user_can( $this->capability_type ) );
	}

	/**
	 * Parses the CSV from the file and returns the data as an array.
	 *
	 * @since 1.0.6
	 * @param string $file
	 *
	 * @return array
	 */
	public function get_csv_file( $file ) {
		$csv = array_map( 'str_getcsv', file( $this->file ) );
		array_walk(
			$csv,
			function ( &$a ) use ( $csv ) {
				/*
				* Make sure the two arrays have the same lengths.
				* If not, we trim the larger array to match the smaller one.
				*/
				$min     = min( count( $csv[0] ), count( $a ) );
				$headers = array_slice( $csv[0], 0, $min );
				$values  = array_slice( $a, 0, $min );
				$a       = array_combine( $headers, $values );
			}
		);
		array_shift( $csv );

		return $csv;
	}

	/**
	 * Get the CSV columns
	 *
	 * @since 1.0.6
	 * @return array The columns in the CSV
	 */
	public function get_columns() {

		$columns = array();

		if ( isset( $this->csv[0] ) && is_array( $this->csv[0] ) ) {
			$columns = array_keys( $this->csv[0] );
		}

		return $columns;
	}

	/**
	 * Get the first row of the CSV
	 *
	 * This is used for showing an example of what the import will look like
	 *
	 * @since 1.0.6
	 * @return array The first row after the header of the CSV
	 */
	public function get_first_row() {

		if ( ! is_array( $this->csv ) ) {
			return array();
		}

		return array_map( array( $this, 'trim_preview' ), current( $this->csv ) );
	}

	/**
	 * Process a step
	 *
	 * @since 1.0.6
	 * @return bool
	 */
	public function process_step() {

		$more = false;

		if ( ! $this->can_import() ) {
			wp_die( __( 'You do not have permission to import data.', 'church-plugins' ), __( 'Error', 'church-plugins' ), array( 'response' => 403 ) );
		}

		return $more;
	}

	/**
	 * Return the calculated completion percentage
	 *
	 * @since 1.0.6
	 * @return int
	 */
	public function get_percentage_complete() {
		return 100;
	}

	/**
	 * Map CSV columns to import fields
	 *
	 * @since 1.0.6
	 * @return void
	 */
	public function map_fields( $import_fields = array() ) {

		$this->field_mapping = array_map( 'sanitize_text_field', $import_fields );

	}

	/**
	 * Get the mapped value for the provided field
	 *
	 * @param $key
	 * @param $row
	 *
	 * @return false|mixed
	 * @since  1.0.6
	 *
	 * @author Tanner Moushey
	 */
	public function get_field_value( $key, $default = '', $row = [] ) {
		if ( empty( $row ) ) {
			$row = $this->row;
		}

		if ( empty( $this->field_mapping[ $key ] ) ) {
			return $default;
		}

		if ( isset( $row[ $this->field_mapping[ $key ] ] ) ) {
			return $row[ $this->field_mapping[ $key ] ];
		}

		return $default;
	}

	/**
	 * Retrieve the URL to the list table for the import data type
	 *
	 * @since 1.0.6
	 * @return string
	 */
	public function get_list_table_url() {}

	/**
	 * Retrieve the label for the import type. Example: Payments
	 *
	 * @since 1.0.6
	 * @return string
	 */
	public function get_import_type_label() {}

	/**
	 * Convert a string containing delimiters to an array
	 *
	 * @since 1.0.6
	 * @param $str Input string to convert to an array
	 * @return array
	 */
	public function str_to_array( $str = '' ) {

		$array = array();

		if( is_array( $str ) ) {
			return array_map( 'trim', $str );
		}

		// Look for standard delimiters
		if( false !== strpos( $str, '|' ) ) {

			$delimiter = '|';

		} elseif( false !== strpos( $str, ',' ) ) {

			$delimiter = ',';

		} elseif( false !== strpos( $str, ';' ) ) {

			$delimiter = ';';

		} elseif( false !== strpos( $str, '/' ) && ! filter_var( str_replace( ' ', '%20', $str ), FILTER_VALIDATE_URL ) && '/' !== substr( $str, 0, 1 ) ) {

			$delimiter = '/';

		}

		if( ! empty( $delimiter ) ) {

			$array = (array) explode( $delimiter, $str );

		} else {

			$array[] = $str;
		}

		return array_map( 'trim', $array );

	}

	/**
	 * Convert a files string containing delimiters to an array.
	 *
	 * This is identical to str_to_array() except it ignores all / characters.
	 *
	 * @since 1.0.6
	 * @param $str Input string to convert to an array
	 * @return array
	 */
	public function convert_file_string_to_array( $str = '' ) {

		$array = array();

		if( is_array( $str ) ) {
			return array_map( 'trim', $str );
		}

		// Look for standard delimiters
		if( false !== strpos( $str, '|' ) ) {

			$delimiter = '|';

		} elseif( false !== strpos( $str, ',' ) ) {

			$delimiter = ',';

		} elseif( false !== strpos( $str, ';' ) ) {

			$delimiter = ';';

		}

		if( ! empty( $delimiter ) ) {

			$array = (array) explode( $delimiter, $str );

		} else {

			$array[] = $str;
		}

		return array_map( 'trim', $array );

	}

	/**
	 * Trims a column value for preview
	 *
	 * @since 1.0.6
	 * @param $str Input string to trim down
	 * @return string
	 */
	public function trim_preview( $str = '' ) {

		if( ! is_numeric( $str ) ) {

			$long = strlen( $str ) >= 30;
			$str  = substr( $str, 0, 30 );
			$str  = $long ? $str . '...' : $str;

		}

		return $str;

	}

	/**
	 * Set up and store the Featured Image
	 *
	 * @param $post_id
	 * @param $image
	 * @param $post_author
	 *
	 * @return bool|int
	 * @since  1.0.6
	 *
	 * @author Tanner Moushey
	 */
	public function set_image( $post_id = 0, $image = '', $post_author = 0 ) {

		$is_url   = false !== filter_var( $image, FILTER_VALIDATE_URL );
		$is_local = $is_url && false !== strpos( site_url(), $image );
		$ext      = $this->get_file_extension( $image );

		if ( $is_url && $is_local ) {

			// Image given by URL, see if we have an attachment already
			$attachment_id = attachment_url_to_postid( $image );

		} elseif ( $is_url ) {

			if ( ! function_exists( 'media_sideload_image' ) ) {

				require_once( ABSPATH . 'wp-admin/includes/file.php' );

			}

			// Image given by external URL
			$url = media_sideload_image( $image, $post_id, '', 'src' );

			if ( ! is_wp_error( $url ) ) {

				$attachment_id = attachment_url_to_postid( $url );

			}


		} elseif ( false === strpos( $image, '/' ) && $this->get_file_extension( $image ) ) {

			// Image given by name only

			$upload_dir = wp_upload_dir();

			if ( file_exists( trailingslashit( $upload_dir['path'] ) . $image ) ) {

				// Look in current upload directory first
				$file = trailingslashit( $upload_dir['path'] ) . $image;

			} else {

				// Now look through year/month sub folders of upload directory for files with our image's same extension
				$files = glob( $upload_dir['basedir'] . '/*/*/*' . $ext );
				foreach ( $files as $file ) {

					if ( basename( $file ) == $image ) {

						// Found our file
						break;

					}

					// Make sure $file is unset so our empty check below does not return a false positive
					unset( $file );

				}

			}

			if ( ! empty( $file ) ) {

				// We found the file, let's see if it already exists in the media library

				$guid          = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file );
				$attachment_id = attachment_url_to_postid( $guid );


				if ( empty( $attachment_id ) ) {

					// Doesn't exist in the media library, let's add it

					$filetype = wp_check_filetype( basename( $file ), null );

					// Prepare an array of post data for the attachment.
					$attachment = array(
						'guid'           => $guid,
						'post_mime_type' => $filetype['type'],
						'post_title'     => preg_replace( '/\.[^.]+$/', '', $image ),
						'post_content'   => '',
						'post_status'    => 'inherit',
						'post_author'    => $post_author
					);

					// Insert the attachment.
					$attachment_id = wp_insert_attachment( $attachment, $file, $post_id );

					// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
					require_once( ABSPATH . 'wp-admin/includes/image.php' );

					// Generate the metadata for the attachment, and update the database record.
					$attach_data = wp_generate_attachment_metadata( $attachment_id, $file );
					wp_update_attachment_metadata( $attachment_id, $attach_data );

				}

			}

		}

		if ( ! empty( $attachment_id ) ) {

			return set_post_thumbnail( $post_id, $attachment_id );

		}

		return false;

	}

	/**
	 * Get File Extension
	 *
	 * Returns the file extension of a filename.
	 *
	 * @param $str
	 *
	 *
	 * @return false|mixed|string
	 * @since  1.0.6
	 *
	 * @author Tanner Moushey
	 */
	public function get_file_extension( $str ) {
		$parts          = explode( '.', $str );
		$file_extension = end( $parts );

		if ( false !== strpos( $file_extension, '?' ) ) {
			$file_extension = substr( $file_extension, 0, strpos( $file_extension, '?' ) );
		}

		return $file_extension;
	}
}
