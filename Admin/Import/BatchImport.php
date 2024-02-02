<?php
/**
 * Batch Import Class
 *
 * This is the base class for all batch import methods. Each data import type (customers, payments, etc) extend this class
 *
 * @since       1.0.6
 * @subpackage  Admin/Import
 * @package     ChurchPlugins
 */

namespace ChurchPlugins\Admin\Import;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use ChurchPlugins\Helpers;

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
	 * Whether we are done importing or not
	 *
	 * @since 1.0.6
	 */
	public $done;

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
	 * @since 1.0.6
	 *
	 * @param $_step int The step to process
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
	public function init() {
	}

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
	 * @updated 1.0.15 to use fgetcsv instead of str_getcsv
	 *
	 * @param string $file
	 *
	 * @return array
	 */
	public function get_csv_file( $file ) {
		$csv_file = fopen( $this->file, 'r' );
		$csv = [];

		while( $line = fgetcsv( $csv_file ) ) {
			$csv[] = $line;
		};

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
	 * @since  1.0.6
	 * @updated 1.0.15 to include cleaning field value
	 *
	 * @param $row
	 *
	 * @param $key
	 *
	 * @return false|mixed
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
			return $this->clean_field_value( $row[ $this->field_mapping[ $key ] ] );
		}

		return $default;
	}

	/**
	 * Clean out the field value in case the CSV was encoded with escapes that will not be load friendly.
	 *
	 * @param $encoded_field_value
	 *
	 * @return string
	 * @since 1.0.15
	 *
	 * @author Wayne Anderson, Tanner Moushey
	 */
	public function clean_field_value( $encoded_field_value = '' ) {

		if ( strlen( $encoded_field_value ) < 3 ) {
			// Short or default-value empty string doesn't have the length for us to test and likely doesn't need decoded.
			return $encoded_field_value;
		}

		// If this value starts or ends with a quotation mark, or an encoded/escaped quotation mark, remove them.
		$decoded_field_value = ltrim( $encoded_field_value, '\"');
		$decoded_field_value = ltrim( $decoded_field_value, '"');
		$decoded_field_value = rtrim( $decoded_field_value, '\"');
		$decoded_field_value = rtrim( $decoded_field_value, '"');

		// Eliminate escaped single quotation marks.
		$decoded_field_value = str_replace( "\'", "", $decoded_field_value );

		// Eliminate quotation marks within the text.
		$decoded_field_value = str_replace( "\"", "", $decoded_field_value );

		// Replace escaped newlines with real new lines.
		$decoded_field_value = str_replace( "\\\\n", "\n", $decoded_field_value );

		return $decoded_field_value;
	}

	/**
	 * Retrieve the URL to the list table for the import data type
	 *
	 * @since 1.0.6
	 * @return string
	 */
	public function get_list_table_url() {
	}

	/**
	 * Retrieve the label for the import type. Example: Payments
	 *
	 * @since 1.0.6
	 * @return string
	 */
	public function get_import_type_label() {
	}

	/**
	 * Convert a string containing delimiters to an array
	 *
	 * @since 1.0.6
	 *
	 * @param $str Input string to convert to an array
	 *
	 * @return array
	 */
	public function str_to_array( $str = '' ) {

		$array = array();

		if ( is_array( $str ) ) {
			return array_map( 'trim', $str );
		}

		// Look for standard delimiters
		if ( false !== strpos( $str, '|' ) ) {

			$delimiter = '|';

		} elseif ( false !== strpos( $str, ',' ) ) {

			$delimiter = ',';

		} elseif ( false !== strpos( $str, ';' ) ) {

			$delimiter = ';';

		} elseif ( false !== strpos( $str, '/' ) && ! filter_var( str_replace( ' ', '%20', $str ), FILTER_VALIDATE_URL ) && '/' !== substr( $str, 0, 1 ) ) {

			$delimiter = '/';

		}

		if ( ! empty( $delimiter ) ) {

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
	 *
	 * @param $str Input string to convert to an array
	 *
	 * @return array
	 */
	public function convert_file_string_to_array( $str = '' ) {

		$array = array();

		if ( is_array( $str ) ) {
			return array_map( 'trim', $str );
		}

		// Look for standard delimiters
		if ( false !== strpos( $str, '|' ) ) {

			$delimiter = '|';

		} elseif ( false !== strpos( $str, ',' ) ) {

			$delimiter = ',';

		} elseif ( false !== strpos( $str, ';' ) ) {

			$delimiter = ';';

		}

		if ( ! empty( $delimiter ) ) {

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
	 *
	 * @param $str Input string to trim down
	 *
	 * @return string
	 */
	public function trim_preview( $str = '' ) {

		if ( ! is_numeric( $str ) ) {

			$long = strlen( $str ) >= 30;
			$str  = substr( $str, 0, 30 );
			$str  = $long ? $str . '...' : $str;

		}

		return $str;

	}

	/**
	 * Set up and store the Featured Image
	 *
	 * @since  1.0.6
	 *
	 * @param $image
	 * @param $post_author
	 *
	 * @param $post_id
	 *
	 * @return bool|int
	 * @author Tanner Moushey
	 */
	public function set_image( $post_id = 0, $image = '', $post_author = 0 ) {
		$upload_dir = wp_upload_dir();

		$image    = $this->maybe_find_local_file( $image );
		$ext      = Helpers::get_file_extension( $image );
		$is_url   = false !== filter_var( $image, FILTER_VALIDATE_URL );
		$is_local = $is_url && false !== strpos( $image, site_url() );

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

		} elseif ( false === strpos( $image, '/' ) && $ext && $file = $this->get_media_by_filename( $image ) ) {

			// We found the file, let's see if it already exists in the media library
			$guid          = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file );
			$attachment_id = attachment_url_to_postid( $guid );

			// generate the attachment record
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

		if ( ! empty( $attachment_id ) ) {
			return set_post_thumbnail( $post_id, $attachment_id );
		}

		return false;

	}

	/**
	 * Look for the provided file in the uploads directory
	 *
	 * @since  1.0.13
	 *
	 * @param $filename
	 *
	 * @return false|mixed|string
	 * @author Tanner Moushey, 5/26/23
	 */
	public function get_media_by_filename( $filename ) {
		$ext        = Helpers::get_file_extension( $filename );
		$upload_dir = wp_upload_dir();

		if ( file_exists( trailingslashit( $upload_dir['path'] ) . $filename ) ) {

			// Look in current upload directory first
			return trailingslashit( $upload_dir['path'] ) . $filename;

		} else {

			// Now look through year/month sub folders of upload directory for files with our image's same extension
			$files = glob( $upload_dir['basedir'] . '/*/*/*' . $ext );
			foreach ( $files as $file ) {
				// Found our file
				if ( basename( $file ) == $filename ) {
					return $file;
				}
			}

		}

		return false;
	}

	/**
	 * Check to see if the provided file exists locally. Return the local file if found, or the original file if not.
	 *
	 * @since  1.0.13
	 *
	 * @param $file
	 *
	 * @return array|mixed|string|string[]
	 * @author Tanner Moushey, 5/26/23
	 */
	public function maybe_find_local_file( $file ) {
		$upload_dir = wp_upload_dir();

		if ( ! Helpers::get_file_extension( $file ) ) {
			return $file;
		}

		$filename = explode( '/', $file );
		$filename = array_pop( $filename );

		if ( $found_file = $this->get_media_by_filename( $filename ) ) {
			$found_file = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $found_file );

			if ( false !== strpos( site_url(), 'https' ) ) {
				$file = str_replace( 'http://', 'https://', $found_file );
			} else {
				$file = str_replace( 'https://', 'http://', $found_file );
			}
		}

		return $file;
	}

	/**
	 * Downloads and adds file to media library from url
	 *
	 * @since  1.0.12
	 *
	 * @param $media_url
	 *
	 * @param $post_id
	 *
	 * @return string The sideloaded media URL on success, the original media_url of fail
	 * @author Jonathan Roley
	 */
	public function sideload_media_and_get_url( $post_id = 0, $media_url = '' ) {

		$media_url = $this->maybe_find_local_file( $media_url );
		$is_url    = false !== filter_var( $media_url, FILTER_VALIDATE_URL );
		$is_local  = $is_url && false !== strpos( $media_url, site_url() );

		if ( $is_url && $is_local ) {

			// Image given by URL, see if we have an attachment already
			$attachment_id = attachment_url_to_postid( $media_url );

		} elseif ( $is_url ) {

			if ( ! function_exists( 'media_handle_sideload' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
			}

			$tmp = download_url( $media_url );

			if ( is_wp_error( $tmp ) ) {
				return $media_url;
			}

			$file_array = array(
				'name'     => basename( $media_url ),
				'tmp_name' => $tmp
			);

			$media_id = media_handle_sideload( $file_array, $post_id );
			@unlink( $file_array['tmp_name'] );

			if ( is_wp_error( $media_id ) ) {
				return $media_url;
			}

			$attachment_id = $media_id;

		}

		if ( ! empty( $attachment_id ) ) {
			return wp_get_attachment_url( $attachment_id );
		}

		return $media_url;
	}

}
