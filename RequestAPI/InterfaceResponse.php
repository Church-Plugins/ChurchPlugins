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

if ( ! interface_exists( '\ChurchPlugins\RequestAPI\InterfaceResponse' ) ) :

/**
 * API Response
 */
interface InterfaceResponse {


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
