<?php
/**
 * ChurchPlugins Plugin Framework
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace ChurchPlugins;

defined( 'ABSPATH' ) or exit;

if ( ! class_exists( 'ChurchPlugins\Helpers' ) ) :

	/**
	 * SkyVerge Helper Class
	 *
	 * The purpose of this class is to centralize common utility functions that
	 * are commonly used in SkyVerge plugins
	 *
	 * @since 2.2.0
	 */
	class Helpers {


		/** encoding used for mb_*() string functions */
		const MB_ENCODING = 'UTF-8';

		protected static $_plugin_dir = false;
		protected static $_plugin_url = false;


		/** String manipulation functions (all multi-byte safe) ***************/

		/**
		 * Returns true if the haystack string starts with needle
		 *
		 * Note: case-sensitive
		 *
		 * @since 2.2.0
		 * @param string $haystack
		 * @param string $needle
		 * @return bool
		 */
		public static function str_starts_with( $haystack, $needle ) {

			if ( self::multibyte_loaded() ) {

				if ( '' === $needle ) {
					return true;
				}

				return 0 === mb_strpos( $haystack, $needle, 0, self::MB_ENCODING );

			} else {

				$needle = self::str_to_ascii( $needle );

				if ( '' === $needle ) {
					return true;
				}

				return 0 === strpos( self::str_to_ascii( $haystack ), self::str_to_ascii( $needle ) );
			}
		}


		/**
		 * Return true if the haystack string ends with needle
		 *
		 * Note: case-sensitive
		 *
		 * @since 2.2.0
		 * @param string $haystack
		 * @param string $needle
		 * @return bool
		 */
		public static function str_ends_with( $haystack, $needle ) {

			if ( '' === $needle ) {
				return true;
			}

			if ( self::multibyte_loaded() ) {

				return mb_substr( $haystack, -mb_strlen( $needle, self::MB_ENCODING ), null, self::MB_ENCODING ) === $needle;

			} else {

				$haystack = self::str_to_ascii( $haystack );
				$needle   = self::str_to_ascii( $needle );

				return substr( $haystack, -strlen( $needle ) ) === $needle;
			}
		}


		/**
		 * Returns true if the needle exists in haystack
		 *
		 * Note: case-sensitive
		 *
		 * @since 2.2.0
		 * @param string $haystack
		 * @param string $needle
		 * @return bool
		 */
		public static function str_exists( $haystack, $needle ) {

			if ( self::multibyte_loaded() ) {

				if ( '' === $needle ) {
					return false;
				}

				return false !== mb_strpos( $haystack, $needle, 0, self::MB_ENCODING );

			} else {

				$needle = self::str_to_ascii( $needle );

				if ( '' === $needle ) {
					return false;
				}

				return false !== strpos( self::str_to_ascii( $haystack ), self::str_to_ascii( $needle ) );
			}
		}


		/**
		 * Truncates a given $string after a given $length if string is longer than
		 * $length. The last characters will be replaced with the $omission string
		 * for a total length not exceeding $length
		 *
		 * @since 2.2.0
		 * @param string $string text to truncate
		 * @param int $length total desired length of string, including omission
		 * @param string $omission omission text, defaults to '...'
		 * @return string
		 */
		public static function str_truncate( $string, $length, $omission = '...' ) {

			if ( self::multibyte_loaded() ) {

				// bail if string doesn't need to be truncated
				if ( mb_strlen( $string, self::MB_ENCODING ) <= $length ) {
					return $string;
				}

				$length -= mb_strlen( $omission, self::MB_ENCODING );

				return mb_substr( $string, 0, $length, self::MB_ENCODING ) . $omission;

			} else {

				$string = self::str_to_ascii( $string );

				// bail if string doesn't need to be truncated
				if ( strlen( $string ) <= $length ) {
					return $string;
				}

				$length -= strlen( $omission );

				return substr( $string, 0, $length ) . $omission;
			}
		}


		/**
		 * Returns a string with all non-ASCII characters removed. This is useful
		 * for any string functions that expect only ASCII chars and can't
		 * safely handle UTF-8. Note this only allows ASCII chars in the range
		 * 33-126 (newlines/carriage returns are stripped)
		 *
		 * @since 2.2.0
		 * @param string $string string to make ASCII
		 * @return string
		 */
		public static function str_to_ascii( $string ) {

			// strip ASCII chars 32 and under
			$string = filter_var( $string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW );

			// strip ASCII chars 127 and higher
			return filter_var( $string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH );
		}


		/**
		 * Return a string with insane UTF-8 characters removed, like invisible
		 * characters, unused code points, and other weirdness. It should
		 * accept the common types of characters defined in Unicode.
		 *
		 * The following are allowed characters:
		 *
		 * p{L} - any kind of letter from any language
		 * p{Mn} - a character intended to be combined with another character without taking up extra space (e.g. accents, umlauts, etc.)
		 * p{Mc} - a character intended to be combined with another character that takes up extra space (vowel signs in many Eastern languages)
		 * p{Nd} - a digit zero through nine in any script except ideographic scripts
		 * p{Zs} - a whitespace character that is invisible, but does take up space
		 * p{P} - any kind of punctuation character
		 * p{Sm} - any mathematical symbol
		 * p{Sc} - any currency sign
		 *
		 * pattern definitions from http://www.regular-expressions.info/unicode.html
		 *
		 * @since 4.0.0
		 * @param string $string
		 * @return mixed
		 */
		public static function str_to_sane_utf8( $string ) {

			$sane_string = preg_replace( '/[^\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Zs}\p{P}\p{Sm}\p{Sc}]/u', '', $string );

			// preg_replace with the /u modifier can return null or false on failure
			return ( is_null( $sane_string ) || false === $sane_string ) ? $string : $sane_string;
		}


		/**
		 * Helper method to check if the multibyte extension is loaded, which
		 * indicates it's safe to use the mb_*() string methods
		 *
		 * @since 2.2.0
		 * @return bool
		 */
		protected static function multibyte_loaded() {

			return extension_loaded( 'mbstring' );
		}


		/** Array functions ***************************************************/


		/**
		 * Insert the given element after the given key in the array
		 *
		 * Sample usage:
		 *
		 * given
		 *
		 * array( 'item_1' => 'foo', 'item_2' => 'bar' )
		 *
		 * array_insert_after( $array, 'item_1', array( 'item_1.5' => 'w00t' ) )
		 *
		 * becomes
		 *
		 * array( 'item_1' => 'foo', 'item_1.5' => 'w00t', 'item_2' => 'bar' )
		 *
		 * @since 2.2.0
		 * @param array $array array to insert the given element into
		 * @param string $insert_key key to insert given element after
		 * @param array $element element to insert into array
		 * @return array
		 */
		public static function array_insert_after( Array $array, $insert_key, Array $element ) {

			$new_array = array();

			foreach ( $array as $key => $value ) {

				$new_array[ $key ] = $value;

				if ( $insert_key == $key ) {

					foreach ( $element as $k => $v ) {
						$new_array[ $k ] = $v;
					}
				}
			}

			return $new_array;
		}


		/**
		 * Convert array into XML by recursively generating child elements
		 *
		 * First instantiate a new XML writer object:
		 *
		 * $xml = new XMLWriter();
		 *
		 * Open in memory (alternatively you can use a local URI for file output)
		 *
		 * $xml->openMemory();
		 *
		 * Then start the document
		 *
		 * $xml->startDocument( '1.0', 'UTF-8' );
		 *
		 * Don't forget to end the document and output the memory
		 *
		 * $xml->endDocument();
		 *
		 * $your_xml_string = $xml->outputMemory();
		 *
		 * @since 2.2.0
		 * @param \XMLWriter $xml_writer XML writer instance
		 * @param string|array $element_key name for element, e.g. <per_page>
		 * @param string|array $element_value value for element, e.g. 100
		 * @return string generated XML
		 */
		public static function array_to_xml( $xml_writer, $element_key, $element_value = array() ) {

			if ( is_array( $element_value ) ) {

				// handle attributes
				if ( '@attributes' === $element_key ) {
					foreach ( $element_value as $attribute_key => $attribute_value ) {

						$xml_writer->startAttribute( $attribute_key );
						$xml_writer->text( $attribute_value );
						$xml_writer->endAttribute();
					}
					return;
				}

				// handle multi-elements (e.g. multiple <Order> elements)
				if ( is_numeric( key( $element_value ) ) ) {

					// recursively generate child elements
					foreach ( $element_value as $child_element_key => $child_element_value ) {

						$xml_writer->startElement( $element_key );

						foreach ( $child_element_value as $sibling_element_key => $sibling_element_value ) {
							self::array_to_xml( $xml_writer, $sibling_element_key, $sibling_element_value );
						}

						$xml_writer->endElement();
					}

				} else {

					// start root element
					$xml_writer->startElement( $element_key );

					// recursively generate child elements
					foreach ( $element_value as $child_element_key => $child_element_value ) {
						self::array_to_xml( $xml_writer, $child_element_key, $child_element_value );
					}

					// end root element
					$xml_writer->endElement();
				}

			} else {

				// handle single elements
				if ( '@value' == $element_key ) {

					$xml_writer->text( $element_value );

				} else {

					// wrap element in CDATA tags if it contains illegal characters
					if ( false !== strpos( $element_value, '<' ) || false !== strpos( $element_value, '>' ) ) {

						$xml_writer->startElement( $element_key );
						$xml_writer->writeCdata( $element_value );
						$xml_writer->endElement();

					} else {

						$xml_writer->writeElement( $element_key, $element_value );
					}

				}

				return;
			}
		}


		/** Number helper functions *******************************************/


		/**
		 * Format a number with 2 decimal points, using a period for the decimal
		 * separator and no thousands separator.
		 *
		 * Commonly used for payment gateways which require amounts in this format.
		 *
		 * @since 3.0.0
		 * @param float $number
		 * @return string
		 */
		public static function number_format( $number ) {

			return number_format( (float) $number, 2, '.', '' );
		}

		/** Misc functions ****************************************************/

		/**
		 * Strip tags and shortcodes for podcast content
		 *
		 * @since  1.0.8
		 *
		 * @param $content
		 *
		 * @return mixed|void
		 * @author Tanner Moushey, 4/10/23
		 */
		public static function podcast_content( $content = '' ) {
			// Allow some HTML per iTunes spec:
			// "You can use rich text formatting and some HTML (<p>, <ol>, <ul>, <a>) in the <content:encoded> tag."
			$content = strip_tags( $content, '<b><strong><i><em><p><ol><ul><a>' );

			$content = strip_shortcodes( $content );

			$content = trim( $content );

			return apply_filters( 'cp_podcast_content', $content );
		}

		/**
		 * Abstract an obnoxiously common WP_Post sanity check
		 *
		 * @param WP_Post $post
		 *
		 * @return boolean
		 * @author costmo
		 */
		public static function is_post( $post = null ) {

			if ( empty( $post ) || ! is_object( $post ) || empty( $post->ID ) ) {
				return false;
			} else {
				return true;
			}

		}

		/**
		 * Slightly less code than a full, proper nonce check.
		 *
		 * The submitted values is assumed to be in the `$_REQUEST` array, and
		 *   the request field must match the action title
		 *
		 * e.g. `Convenience::nonce_is_valid( 'my_nonce' );` to validate
		 * `wp_create_nonce( 'my_nonce );` via `$_REQUEST['my_nonce'];`
		 *
		 * @param string $action
		 *
		 * @return boolean
		 * @author costmo
		 */
		public static function nonce_is_valid( $action = null ) {

			if ( empty( $_REQUEST ) || ! is_array( $_REQUEST ) || empty( $_REQUEST[ $action ] ) || ! wp_verify_nonce( $_REQUEST[ $action ], $action ) ) {
				return false;
			} else {
				return true;
			}

		}

		/**
		 * @param $timestamp
		 * @param $format
		 *
		 * @return mixed|void
		 * @since  1.0.0
		 *
		 * @author Tanner Moushey
		 */
		public static function relative_time( $timestamp, $format = '' ) {
			return apply_filters( 'cp_relative_time', self::calculate_relative_time( $timestamp, $format ), $timestamp, $format );
		}

		/**
		 * Convert timestamp to relative time
		 *
		 * @param $timestamp
		 * @param $format
		 *
		 * @return string
		 * @since  1.0.0
		 *
		 * @author Tanner Moushey
		 */
		public static function calculate_relative_time( $timestamp, $format = '' ) {

			$format = ! empty( $format ) ? $format : get_option( 'date_format' );

			if ( ! ctype_digit( $timestamp ) ) {
				$timestamp = strtotime( $timestamp );
			}

			$diff = time() - $timestamp;
			if ( $diff == 0 ) {
				return 'now';
			} elseif ( $diff > 0 ) {
				$day_diff = floor( $diff / 86400 );
				if ( $day_diff == 0 ) {
					if ( $diff < 60 ) {
						return 'just now';
					}
					if ( $diff < 120 ) {
						return '1 minute ago';
					}
					if ( $diff < 3600 ) {
						return floor( $diff / 60 ) . ' minutes ago';
					}
					if ( $diff < 7200 ) {
						return '1 hour ago';
					}
					if ( $diff < 86400 ) {
						return floor( $diff / 3600 ) . ' hours ago';
					}
				}
				if ( $day_diff == 1 ) {
					return 'Yesterday';
				}
				if ( $day_diff < 7 ) {
					return $day_diff . ' days ago';
				}

				return date( $format, $timestamp );
			} else {
				$diff     = abs( $diff );
				$day_diff = floor( $diff / 86400 );
				if ( $day_diff == 0 ) {
					if ( $diff < 120 ) {
						return 'in a minute';
					}
					if ( $diff < 3600 ) {
						return 'in ' . floor( $diff / 60 ) . ' minutes';
					}
					if ( $diff < 7200 ) {
						return 'in an hour';
					}
					if ( $diff < 86400 ) {
						return 'in ' . floor( $diff / 3600 ) . ' hours';
					}
				}
				if ( $day_diff == 1 ) {
					return 'Tomorrow';
				}
				if ( $day_diff < 4 ) {
					return date( 'l', $timestamp );
				}

				return date( $format, $timestamp );
			}
		}

		/**
		 * @param $icon
		 *
		 * @since  1.0.0
		 *
		 * @author Tanner Moushey
		 */
		public static function get_icon( $icon ) {
			$markup = '';

			switch( $icon ) {
				case 'location' :
					$markup = '<span class="material-icons-outlined">location_on</span>';
					break;
				case 'date' :
					$markup = '<span class="material-icons-outlined">calendar_today</span>';
					break;
				case 'topics' :
					$markup = '<span class="material-icons-outlined">sell</span>';
					break;
				case 'scripture' :
					$markup = '<span class="material-icons-outlined">menu_book</span>';
					break;
				case 'speaker' :
					$markup = '<span class="material-icons-outlined">person</span>';
					break;
				case 'series' :
				case 'type' :
					$markup = '<span class="material-icons-outlined">view_list</span>';
					break;
				case 'filter' :
					$markup = '<span class="material-icons-outlined">tune</span>';
					break;
				case 'copy' :
					$markup = '<span class="material-icons-outlined">content_copy</span>';
					break;
				case 'report' :
					$markup = '<span class="material-icons-outlined">report</span>';
					break;
				case 'child' :
					$markup = '<span class="material-icons-outlined">escalator_warning</span>';
					break;
				case 'accessible' :
					$markup = '<span class="material-icons-outlined">accessible</span>';
					break;
				case 'virtual':
					$markup = '<span class="material-icons-outlined">videocam</span>';
					break;
				case 'launch':
					$markup = '<span class="material-icons-outlined">launch</span>';
					break;
				case 'link':
					$markup = '<span class="material-icons-outlined">attach_file</span>';
					break;
				case 'facebook':
				case 'instagram':
				case 'linkedin':
				case 'pinterest':
				case 'twitter':
				case 'vimeo':
				case 'youtube':
					$markup = '<span class="cp-icon">' . file_get_contents( CHURCHPLUGINS_DIR . 'assets/icons/' . $icon . '.svg' ) . '</span>';
					break;
			}

			return apply_filters( 'cp_get_icon', $markup, $icon );
		}

		/**
		 * Helper function to get array parameters when they might not exist
		 *
		 * @param        $array
		 * @param        $key
		 * @param string | array $default
		 *
		 * @updated 1.0.20 - Added sanitization function for XSS
		 *
		 * @return string | array
		 */
		public static function get_param( $array, $key, $default = '' ) {
			$value = $default;

			if ( isset( $array[ $key ] ) ) {
				$value = $array[ $key ];
			}

			if ( is_array( $value ) ) {
				$value = self::array_map_recursive( '_sanitize_text_fields', $value );
			} else {
				$value = _sanitize_text_fields( $value, true );
			}

			return apply_filters( 'sc_param_get', $value, $array, $key, $default );
		}

		/**
		 * Safely get and trim data from $_POST
		 *
		 * @since 3.0.0
		 * @param string $key array key to get from $_POST array
		 * @param string $default
		 * @return string value from $_POST or blank string if $_POST[ $key ] is not set
		 */
		public static function get_post( $key, $default = '' ) {
			return self::get_param( $_POST, $key, $default );
		}


		/**
		 * Safely get and trim data from $_REQUEST
		 *
		 * @since 3.0.0
		 * @param string $key array key to get from $_REQUEST array
		 * @param string $default
		 * @return string value from $_REQUEST or blank string if $_REQUEST[ $key ] is not set
		 */
		public static function get_request( $key, $default = '' ) {
			return self::get_param( $_REQUEST, $key, $default );
		}

		/**
		 * Gets the current WordPress site name.
		 *
		 * This is helpful for retrieving the actual site name instead of the
		 * network name on multisite installations.
		 *
		 * @since 4.6.0
		 * @return string
		 */
		public static function get_site_name() {

			return ( is_multisite() ) ? get_blog_details()->blogname : get_bloginfo( 'name' );
		}

		/**
		 * Recursively apply a callback to all elements of an array
		 *
		 * @since  1.0.20
		 *
		 * @param $callback
		 * @param $array
		 *
		 * @return array
		 * @author Tanner Moushey, 1/29/24
		 */
		public static function array_map_recursive( $callback, $array ) {
			$new = array();
			foreach ( $array as $key => $value ) {
				$new[ $key ] = ( is_array( $value ) ? self::array_map_recursive( $callback, $value ) : call_user_func( $callback, $value ) );
			}

			return $new;
		}

		/**
		 * Convert a 2-character country code into its 3-character equivalent, or
		 * vice-versa, e.g.
		 *
		 * 1) given USA, returns US
		 * 2) given US, returns USA
		 *
		 * @since 4.2.0
		 * @param string $code ISO-3166-alpha-2 or ISO-3166-alpha-3 country code
		 * @return string country code
		 */
		public static function convert_country_code( $code ) {

			// ISO 3166-alpha-2 => ISO 3166-alpha3
			$countries = array(
				'AF' => 'AFG', 'AL' => 'ALB', 'DZ' => 'DZA', 'AD' => 'AND', 'AO' => 'AGO',
				'AG' => 'ATG', 'AR' => 'ARG', 'AM' => 'ARM', 'AU' => 'AUS', 'AT' => 'AUT',
				'AZ' => 'AZE', 'BS' => 'BHS', 'BH' => 'BHR', 'BD' => 'BGD', 'BB' => 'BRB',
				'BY' => 'BLR', 'BE' => 'BEL', 'BZ' => 'BLZ', 'BJ' => 'BEN', 'BT' => 'BTN',
				'BO' => 'BOL', 'BA' => 'BIH', 'BW' => 'BWA', 'BR' => 'BRA', 'BN' => 'BRN',
				'BG' => 'BGR', 'BF' => 'BFA', 'BI' => 'BDI', 'KH' => 'KHM', 'CM' => 'CMR',
				'CA' => 'CAN', 'CV' => 'CPV', 'CF' => 'CAF', 'TD' => 'TCD', 'CL' => 'CHL',
				'CN' => 'CHN', 'CO' => 'COL', 'KM' => 'COM', 'CD' => 'COD', 'CG' => 'COG',
				'CR' => 'CRI', 'CI' => 'CIV', 'HR' => 'HRV', 'CU' => 'CUB', 'CY' => 'CYP',
				'CZ' => 'CZE', 'DK' => 'DNK', 'DJ' => 'DJI', 'DM' => 'DMA', 'DO' => 'DOM',
				'EC' => 'ECU', 'EG' => 'EGY', 'SV' => 'SLV', 'GQ' => 'GNQ', 'ER' => 'ERI',
				'EE' => 'EST', 'ET' => 'ETH', 'FJ' => 'FJI', 'FI' => 'FIN', 'FR' => 'FRA',
				'GA' => 'GAB', 'GM' => 'GMB', 'GE' => 'GEO', 'DE' => 'DEU', 'GH' => 'GHA',
				'GR' => 'GRC', 'GD' => 'GRD', 'GT' => 'GTM', 'GN' => 'GIN', 'GW' => 'GNB',
				'GY' => 'GUY', 'HT' => 'HTI', 'HN' => 'HND', 'HU' => 'HUN', 'IS' => 'ISL',
				'IN' => 'IND', 'ID' => 'IDN', 'IR' => 'IRN', 'IQ' => 'IRQ', 'IE' => 'IRL',
				'IL' => 'ISR', 'IT' => 'ITA', 'JM' => 'JAM', 'JP' => 'JPN', 'JO' => 'JOR',
				'KZ' => 'KAZ', 'KE' => 'KEN', 'KI' => 'KIR', 'KP' => 'PRK', 'KR' => 'KOR',
				'KW' => 'KWT', 'KG' => 'KGZ', 'LA' => 'LAO', 'LV' => 'LVA', 'LB' => 'LBN',
				'LS' => 'LSO', 'LR' => 'LBR', 'LY' => 'LBY', 'LI' => 'LIE', 'LT' => 'LTU',
				'LU' => 'LUX', 'MK' => 'MKD', 'MG' => 'MDG', 'MW' => 'MWI', 'MY' => 'MYS',
				'MV' => 'MDV', 'ML' => 'MLI', 'MT' => 'MLT', 'MH' => 'MHL', 'MR' => 'MRT',
				'MU' => 'MUS', 'MX' => 'MEX', 'FM' => 'FSM', 'MD' => 'MDA', 'MC' => 'MCO',
				'MN' => 'MNG', 'ME' => 'MNE', 'MA' => 'MAR', 'MZ' => 'MOZ', 'MM' => 'MMR',
				'NA' => 'NAM', 'NR' => 'NRU', 'NP' => 'NPL', 'NL' => 'NLD', 'NZ' => 'NZL',
				'NI' => 'NIC', 'NE' => 'NER', 'NG' => 'NGA', 'NO' => 'NOR', 'OM' => 'OMN',
				'PK' => 'PAK', 'PW' => 'PLW', 'PA' => 'PAN', 'PG' => 'PNG', 'PY' => 'PRY',
				'PE' => 'PER', 'PH' => 'PHL', 'PL' => 'POL', 'PT' => 'PRT', 'QA' => 'QAT',
				'RO' => 'ROU', 'RU' => 'RUS', 'RW' => 'RWA', 'KN' => 'KNA', 'LC' => 'LCA',
				'VC' => 'VCT', 'WS' => 'WSM', 'SM' => 'SMR', 'ST' => 'STP', 'SA' => 'SAU',
				'SN' => 'SEN', 'RS' => 'SRB', 'SC' => 'SYC', 'SL' => 'SLE', 'SG' => 'SGP',
				'SK' => 'SVK', 'SI' => 'SVN', 'SB' => 'SLB', 'SO' => 'SOM', 'ZA' => 'ZAF',
				'ES' => 'ESP', 'LK' => 'LKA', 'SD' => 'SDN', 'SR' => 'SUR', 'SZ' => 'SWZ',
				'SE' => 'SWE', 'CH' => 'CHE', 'SY' => 'SYR', 'TJ' => 'TJK', 'TZ' => 'TZA',
				'TH' => 'THA', 'TL' => 'TLS', 'TG' => 'TGO', 'TO' => 'TON', 'TT' => 'TTO',
				'TN' => 'TUN', 'TR' => 'TUR', 'TM' => 'TKM', 'TV' => 'TUV', 'UG' => 'UGA',
				'UA' => 'UKR', 'AE' => 'ARE', 'GB' => 'GBR', 'US' => 'USA', 'UY' => 'URY',
				'UZ' => 'UZB', 'VU' => 'VUT', 'VA' => 'VAT', 'VE' => 'VEN', 'VN' => 'VNM',
				'YE' => 'YEM', 'ZM' => 'ZMB', 'ZW' => 'ZWE', 'TW' => 'TWN', 'CX' => 'CXR',
				'CC' => 'CCK', 'HM' => 'HMD', 'NF' => 'NFK', 'NC' => 'NCL', 'PF' => 'PYF',
				'YT' => 'MYT', 'GP' => 'GLP', 'PM' => 'SPM', 'WF' => 'WLF', 'TF' => 'ATF',
				'BV' => 'BVT', 'CK' => 'COK', 'NU' => 'NIU', 'TK' => 'TKL', 'GG' => 'GGY',
				'IM' => 'IMN', 'JE' => 'JEY', 'AI' => 'AIA', 'BM' => 'BMU', 'IO' => 'IOT',
				'VG' => 'VGB', 'KY' => 'CYM', 'FK' => 'FLK', 'GI' => 'GIB', 'MS' => 'MSR',
				'PN' => 'PCN', 'SH' => 'SHN', 'GS' => 'SGS', 'TC' => 'TCA', 'MP' => 'MNP',
				'PR' => 'PRI', 'AS' => 'ASM', 'UM' => 'UMI', 'GU' => 'GUM', 'VI' => 'VIR',
				'HK' => 'HKG', 'MO' => 'MAC', 'FO' => 'FRO', 'GL' => 'GRL', 'GF' => 'GUF',
				'MQ' => 'MTQ', 'RE' => 'REU', 'AX' => 'ALA', 'AW' => 'ABW', 'AN' => 'ANT',
				'SJ' => 'SJM', 'AC' => 'ASC', 'TA' => 'TAA', 'AQ' => 'ATA', 'CW' => 'CUW',
			);

			if ( 3 === strlen( $code ) ) {
				$countries = array_flip( $countries );
			}

			return isset( $countries[ $code ] ) ? $countries[ $code ] : $code;
		}


		/**
		 * Triggers a PHP error.
		 *
		 * This wrapper method ensures AJAX isn't broken in the process.
		 *
		 * @since 4.6.0
		 * @param string $message the error message
		 * @param int $type Optional. The error type. Defaults to E_USER_NOTICE
		 */
		public static function trigger_error( $message, $type = E_USER_NOTICE ) {

			if ( is_callable( 'is_ajax' ) && is_ajax() ) {

				switch ( $type ) {

					case E_USER_NOTICE:
						$prefix = 'Notice: ';
						break;

					case E_USER_WARNING:
						$prefix = 'Warning: ';
						break;

					default:
						$prefix = '';
				}

				error_log( $prefix . $message );

			} else {

				trigger_error( $message, $type );
			}
		}

		/**
		 * Get File Extension. Maybe use pathinfo in the future?
		 *
		 * Returns the file extension of a filename.
		 *
		 * @since  1.0.6
		 *
		 * @param $filename
		 *
		 *
		 * @return false|mixed|string
		 * @author Tanner Moushey
		 */
		public static function get_file_extension( $filename ) {
			if ( false === strstr( $filename, '.' ) ) {
				return false;
			}

			$parts          = explode( '.', $filename );
			$file_extension = end( $parts );

			if ( false !== strpos( $file_extension, '?' ) ) {
				$file_extension = substr( $file_extension, 0, strpos( $file_extension, '?' ) );
			}

			return apply_filters( 'cp_get_file_extension', strtolower( $file_extension ) );
		}

		/**
		 * Get the mime type for the provided file
		 *
		 * @since  1.0.13
		 *
		 * @param $filename
		 *
		 * @return mixed|void
		 * @author Tanner Moushey, 5/29/23
		 */
		public static function get_file_type( $filename ) {
			$ext = $extension = self::get_file_extension( $filename );

			// get the base extension
			switch ( $extension ) {
				case 'doc':
				case 'docx':
				case 'dot':
				case 'dotx':
				case 'docm':
				case 'dotm':
					$ext = 'doc';
					break;
				case 'xls':
				case 'xlsx':
					$ext = 'xls';
					break;
				case 'ppt':
				case 'pptx':
					$ext = 'ppt';
					break;
				case 'jpg':
				case 'jpeg':
					$ext = 'jpg';
					break;
				case 'png':
					$ext = 'png';
					break;
				case 'htm':
				case 'html':
					$ext = 'html';
					break;
				default:
					if ( false !== strpos( $filename, 'http' ) ) {
						$ext = 'link';
					}

					break;
			}

			// get the type for the extension
			switch( $ext ) {
				case 'doc':
				case 'xls':
				case 'ppt':
				case 'pdf':
				case 'zip':
				case 'csv':
					$type = 'application';
					break;
				case 'jpg':
				case 'png':
				case 'gif':
				case 'svg':
					$type = 'image';
					break;
				case 'mp3':
				case 'aac':
					$type = 'audio';
					break;
				case 'mp4':
				case 'mov':
					$type = 'video';
					break;
				case 'txt':
				case 'html':
				case 'link':
				case 'css':
					$type = 'text';
					break;
				default:
					$type = 'unknown';
					break;
			}

			return apply_filters( 'cp_get_file_type', "$type/$ext", $filename, $type, $ext );
		}

		/**
		 * Determine if the provided types match the type of the file.
		 *
		 * Will return true if any if the types is a match to the file's mime type.
		 *
		 * @since  1.0.13
		 *
		 * @param $filename
		 * @param $types
		 *
		 * @return false|mixed|void
		 * @author Tanner Moushey, 5/29/23
		 */
		public static function is_file_type( $filename, $types ) {
			$return = false;

			if ( ! $filetype = self::get_file_type( $filename ) ) {
				return false;
			}

			if ( ! is_array( $types ) ) {
				$types = [ $types ];
			}

			$types = array_map( 'strtolower', $types );

			if ( in_array( $filetype, $types ) ) {
				$return = true;
			}

			$parts = explode( '/', $filetype );

			// if any of the parts of the mime type match the provided types, return true
			if ( array_intersect( $parts, $types ) ) {
				$return = true;
			}

			return apply_filters( 'cp_is_file_type', $return, $filename, $filetype );
		}

		/**
		 * Output the posts pagination with the smooth scroll disabled
		 *
		 * @since  1.0.18
		 *
		 * @param $args
		 *
		 * @author Tanner Moushey, 11/17/23
		 */
		public static function safe_posts_pagination( $args ) {
			add_filter( 'paginate_links_output', [ __CLASS__, 'safe_posts_pagination_classes' ] );

			the_posts_pagination( $args );

			remove_filter( 'paginate_links_output', [ __CLASS__, 'safe_posts_pagination_classes' ] );
		}

		/**
		 * Add classes to the pagination links to disable smooth scroll
		 *
		 * Some themes (like Divi) will prevent the page from reloading if an anchor is present in the URL.
		 *
		 * @since  1.0.18
		 *
		 * @param $output
		 *
		 * @return array|string|string[]
		 * @author Tanner Moushey, 11/17/23
		 */
		public static function safe_posts_pagination_classes( $output ) {
			$link_classes = apply_filters( 'cp_safe_posts_pagination_classes', [ 'et_smooth_scroll_disabled' ] );

			$output = str_replace( 'page-numbers"', 'page-numbers ' . implode( ' ', $link_classes ) . '"', $output );

			return $output;
		}

		/**
		 * Get the current plugin directory
		 *
		 * @param $dir
		 *
		 * @since  1.0.23
		 *
		 * @author Tanner Moushey, 3/28/24
		 */
		public static function set_plugin_dir( $dir ) {
			self::$_plugin_dir = $dir;
		}

		/**
		 * Gets the path to the current plugin directory.
		 *
		 * @since 1.0.22
		 * @return string
		 */
		public static function get_plugin_dir() {
			$plugin_dir = self::$_plugin_dir;

			if ( empty( $plugin_dir ) ) {
				$plugin_dir = trailingslashit( dirname( __FILE__, 3 ) );
			}

			return $plugin_dir;
		}

		/**
		 * Get the current plugin URL
		 *
		 * @param $url
		 *
		 * @since  1.0.23
		 *
		 * @author Tanner Moushey, 3/28/24
		 */
		public static function set_plugin_url( $url ) {
			self::$_plugin_url = $url;
		}

		/**
		 * Gets the URL to the current plugin.
		 *
		 * @since 1.0.22
		 * @return string
		 */
		public static function get_plugin_url() {
			$plugin_url = self::$_plugin_url;

			if ( empty( $plugin_url ) ) {
				$plugin_url = trailingslashit( dirname( plugins_url( '', __FILE__ ), 2 ) );
			}

			return $plugin_url;
		}

		/**
		 * Enqueue an asset bundled by wp-scripts.
		 *
		 * @param string $name The asset name.
		 * @param array  $extra_deps Extra dependencies to add to the asset.
		 * @param string $version The version number to use.
		 * @param bool   $is_style Whether the asset is a stylesheet.
		 * @param bool   $in_footer Whether the asset should be enqueued in the footer.
		 * @return array The asset details.
		 * @author Jonathan Roley
		 * @since  1.0.22
		 */
		public static function enqueue_asset( $name, $extra_deps = array(), $version = null, $is_style = false, $in_footer = false ) {
			$asset_dir  = self::get_plugin_dir() . 'build/src/';
			$asset_url  = self::get_plugin_url() . 'build/src/';
			$asset_file = $asset_dir . $name . '.asset.php';

			if ( ! file_exists( $asset_file ) ) {
				return;
			}

			$path_sections = array_filter( explode( '/', self::get_plugin_dir() ) );
			$plugin_folder = array_pop( $path_sections );

			$assets = include( $asset_file );

			if ( ! $version ) {
				$version = $assets['version'];
			}

			$handle = "$plugin_folder-$name";

			if ( $is_style ) {
				$url = $asset_url . $name . '.css';

				wp_enqueue_style( $handle, $url, $extra_deps, $version );

				return array(
					'handle'  => $handle,
					'url'     => $url,
					'version' => $version,
					'deps'    => $assets['dependencies'],
					'type'    => 'style',
				);
			} else {
				$full_deps = array_merge( $assets['dependencies'], $extra_deps );
				$url       = $asset_url . $name . '.js';

				wp_enqueue_script( $handle, $url, $full_deps, $version, $in_footer );

				return array(
					'handle'  => $handle,
					'url'     => $url,
					'version' => $version,
					'deps'    => $full_deps,
					'type'    => 'script',
				);
			}
		}

	}

endif; // Class exists check
