<?php
/**
 * Boot a real WordPress admin request for the integration suite.
 *
 * Environment:
 *   CP_FW_WP_LOAD  Path to wp-load.php (required).
 *   CP_FW_WP_URL   Site to load, e.g. http://church.local/menu-test/ on a
 *                  multisite network (default: the main site).
 *   CP_FW_DB_SOCKET MySQL socket, when the CLI's php.ini lacks it (PHPUnit's
 *                  child processes do not inherit -d settings).
 *
 * The site needs Church Plugins products active (CP Sermons, CP Groups and
 * CP Staff at least, CP Live for the WordPress Settings case), this copy of
 * the framework loaded (the highest version on the site), and users with the
 * logins `menuadmin` (administrator) and `menueditor` (editor). Tests that
 * write run in a transaction that is rolled back.
 *
 * Each test runs in its own process: core builds the admin menu once per
 * request.
 *
 * @package ChurchPlugins
 */

$cp_fw_wp_load = getenv( 'CP_FW_WP_LOAD' );
$cp_fw_wp_url  = getenv( 'CP_FW_WP_URL' );

if ( ! $cp_fw_wp_load || ! is_readable( $cp_fw_wp_load ) ) {
	fwrite( STDERR, "Integration suite: set CP_FW_WP_LOAD to a readable wp-load.php.\n" );
	exit( 1 );
}

if ( $cp_fw_wp_url ) {
	$cp_fw_parts               = parse_url( $cp_fw_wp_url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WordPress is not loaded yet.
	$_SERVER['HTTP_HOST']      = isset( $cp_fw_parts['host'] ) ? $cp_fw_parts['host'] : 'localhost';
	$_SERVER['SERVER_NAME']    = $_SERVER['HTTP_HOST'];
	$_SERVER['REQUEST_URI']    = ( isset( $cp_fw_parts['path'] ) ? rtrim( $cp_fw_parts['path'], '/' ) : '' ) . '/wp-admin/index.php';
	$_SERVER['SERVER_PORT']    = '80';
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['PHP_SELF']       = $_SERVER['REQUEST_URI'];
}

if ( getenv( 'CP_FW_DB_SOCKET' ) ) {
	ini_set( 'mysqli.default_socket', getenv( 'CP_FW_DB_SOCKET' ) ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- Test environment only.
}

define( 'WP_ADMIN', true );
define( 'WP_USE_THEMES', false );

require_once $cp_fw_wp_load;
require_once ABSPATH . 'wp-admin/includes/admin.php';

/**
 * Build the admin menu the way wp-admin/admin.php does, as one user.
 *
 * @param string $login   User login.
 * @param string $pagenow Admin file, e.g. `edit.php`.
 * @param array  $get     Query arguments.
 * @return void
 */
function cp_fw_build_admin_menu( $login, $pagenow = 'index.php', array $get = array() ) {
	// Core's menu files assign these at file scope; required from a function
	// they would be locals.
	global $menu, $submenu, $plugin_page, $typenow, $parent_file, $submenu_file, $self, $hook_suffix, $page_hook,
		$_wp_last_object_menu, $_wp_last_utility_menu, $_wp_menu_nopriv, $_wp_real_parent_file, $_wp_submenu_nopriv,
		$admin_page_hooks, $_registered_pages, $_parent_pages, $compat, $menu_order, $default_menu_order;

	$user = get_user_by( 'login', $login );

	if ( ! $user ) {
		throw new RuntimeException( "Integration suite: user {$login} is missing." );
	}

	wp_set_current_user( $user->ID );

	$_GET                = $get;
	$_REQUEST            = $get;
	$GLOBALS['pagenow']  = $pagenow; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$self                = $pagenow; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$typenow             = isset( $get['post_type'] ) ? $get['post_type'] : ''; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$plugin_page         = isset( $get['page'] ) ? plugin_basename( $get['page'] ) : null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

	require ABSPATH . 'wp-admin/menu.php';
}
