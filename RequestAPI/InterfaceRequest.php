<?php
/**
 * ChurchPlugins Plugin Framework
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace ChurchPlugins\RequestAPI;

defined( 'ABSPATH' ) or exit;

if ( ! interface_exists( '\ChurchPlugins\RequestAPI\InterfaceRequest' ) ) :

/**
 * API Request
 */
interface InterfaceRequest {


	/**
	 * Returns the method for this request: one of HEAD, GET, PUT, PATCH, POST, DELETE
	 *
	 * @since 1.0.0
	 * @return string the request method, or null to use the API default
	 */
	public function get_method();


	/**
	 * Returns the request path
	 *
	 * @since 1.0.0
	 * @return string the request path, or '' if none
	 */
	public function get_path();


	/**
	 * Returns the string representation of this request
	 *
	 * @since 1.0.0
	 * @return string the request
	 */
	public function to_string();


	/**
	 * Returns the string representation of this request with any and all
	 * sensitive elements masked or removed
	 *
	 * @since 1.0.0
	 * @return string the request, safe for logging/displaying
	 */
	public function to_string_safe();

}

endif;  // interface exists check
