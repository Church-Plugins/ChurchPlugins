<?php
/**
 * Builds the WordPress admin menu for one scenario, without WordPress.
 *
 * Usage: php run.php '<scenario json>'   (prints the result as JSON)
 *
 * The menu is built by core's own code: core/ holds wp-includes/plugin.php,
 * class-wp-hook.php, class-wp-filter-sentinel.php and wp-admin/includes/menu.php
 * unchanged from WordPress 7.1.4, and the menu functions of
 * wp-admin/includes/plugin.php (GPL v2 or later, as this repository is).
 * Everything else core would provide is a stand-in defined here: capabilities
 * come from the scenario, options and post counts live in arrays, and the
 * products register their screens the way the released plugins do.
 *
 * It runs in its own process because it defines core's real hook functions,
 * which the unit suite's bootstrap replaces with recorders. No site, no
 * database, no network.
 *
 * Scenario keys (all optional):
 *   caps             string[]  capabilities of the current user.
 *   products         string[]  keys of $defs below.
 *   essentials       ''|'early'|'late'|'old9'  CP Essentials stand-in: absent,
 *                    hooked before the framework, after it, or before it with
 *                    its screens at admin_menu priority 9 (older releases).
 *   essentials_types { post_type: label }  content types Essentials turned on.
 *   optout           bool      `cp_admin_shared_menu` returns false.
 *   cmb2             bool      products' settings and license pages have CMB2 boxes.
 *   options          { name: value }   options that already exist.
 *   posts            { post_type: count }
 *   license_keys     { license id: key }
 *   multisite        { arrived?: int, registered: 'Y-m-d H:i:s' }
 *   user_registered  'Y-m-d H:i:s'
 *   ajax_first       bool      an AJAX request (admin_init, no menu) comes first.
 *   area_caps        { post_type: capability }  a post type with its own capabilities.
 *   settings_tabs    { key: tab }  tabs a host adds through `cp_admin_settings_tabs`.
 *   direct_entries   [ [ label, capability, slug ] ]  entries other code appends
 *                    to the shared menu's $submenu itself, with no capability check.
 *   sermon_menu      'item_type'|'item'  CP Sermons top-level entry. `item_type`
 *                    registers Series (cpl_item_type); `item` registers Messages
 *                    (cpl_item). Omitted means `item_type`.
 *   requests         { name: { pagenow, get, typenow?, screen?, tabs? } }
 *                    `screen.block_editor` makes get_current_screen() report
 *                    a block editor. `tabs` prints the tab bar, body class
 *                    and inline styles for that screen. `dispatch` runs the
 *                    plugin page the way wp-admin/admin.php does: `load-{$hook}`,
 *                    then the admin header, then the page callback.
 *   render           string[]  'notice', 'licenses'.
 *
 * @package ChurchPlugins
 */

// phpcs:ignoreFile -- Test stand-ins for WordPress.

error_reporting( E_ALL );
ini_set( 'display_errors', 'stderr' );

set_error_handler(
	static function ( $severity, $message, $file, $line ) {
		fwrite( STDERR, "PHP error: $message in $file:$line\n" );
		exit( 3 );
	}
);

$fw = dirname( __DIR__, 2 ) . '/';
$sc = json_decode( isset( $argv[1] ) ? $argv[1] : '', true );

if ( ! is_array( $sc ) ) {
	fwrite( STDERR, "bad scenario\n" );
	exit( 2 );
}

$sc += array(
	'caps'             => array(),
	'products'         => array(),
	'essentials'       => '',
	'essentials_types' => array(),
	'optout'           => false,
	'cmb2'             => false,
	'options'          => array(),
	'posts'            => array(),
	'license_keys'     => array(),
	'multisite'        => null,
	'user_registered'  => '2020-01-01 00:00:00',
	'ajax_first'       => false,
	'area_caps'        => array(),
	'direct_entries'   => array(),
	'settings_tabs'    => array(),
	'requests'         => array(),
	'render'           => array(),
	'sermon_menu'      => 'item_type',
);

define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_PLUGIN_DIR', '/sim/wp-content/plugins' );
define( 'WPMU_PLUGIN_DIR', '/sim/wp-content/mu-plugins' );
define( 'CHURCHPLUGINS_DIR', $fw );
define( 'CHURCHPLUGINS_VERSION', 'sim' );

/**
 * Thrown in place of core ending the request (wp_die(), a redirect's exit).
 */
class SimDie extends Exception {}

/**
 * Stand-in for WP_Screen. `block_editor` in the scenario makes
 * is_block_editor() true.
 */
class SimScreen {
	public $base = '';
	public $post_type = '';
	public $taxonomy = '';
	public $block_editor = false;

	public function __construct( array $screen ) {
		foreach ( $screen as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $value;
			}
		}
	}

	public function is_block_editor() {
		return (bool) $this->block_editor;
	}
}

$GLOBALS['sim_caps']         = array_fill_keys( $sc['caps'], true );
$GLOBALS['sim_options']      = $sc['options'];
$GLOBALS['sim_site_options'] = isset( $sc['multisite']['arrived'] ) ? array( 'cp_admin_menu_layout_since' => $sc['multisite']['arrived'] ) : array();
$GLOBALS['sim_user_options'] = array();
$GLOBALS['sim_ptypes']       = array();
$GLOBALS['sim_taxes']        = array();
$GLOBALS['sim_sc']           = $sc;
$GLOBALS['wp_plugin_paths']  = array();

require __DIR__ . '/core/plugin.php';

function __( $t, $d = '' ) { return $t; }
function esc_html__( $t, $d = '' ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_url( $t ) { return (string) $t; }
function esc_js( $t ) { return (string) $t; }
function sanitize_title( $t ) { return strtolower( trim( preg_replace( '/[^a-zA-Z0-9_-]+/', '-', (string) $t ), '-' ) ); }
function sanitize_key( $t ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $t ) ); }
function sanitize_text_field( $t ) { return trim( (string) $t ); }
function wp_unslash( $t ) { return $t; }
function wp_json_encode( $t ) { return json_encode( $t ); }
function wp_parse_str( $s, &$r ) { parse_str( (string) $s, $r ); }
function wp_normalize_path( $p ) { return str_replace( '\\', '/', (string) $p ); }
function wp_is_stream( $p ) { return false; }
function __return_false() { return false; }
function __return_null() { return null; }
function set_url_scheme( $u ) { return $u; }
function current_user_can( $cap ) { return isset( $GLOBALS['sim_caps'][ $cap ] ); }
function is_network_admin() { return false; }
function is_user_admin() { return false; }
function is_multisite() { return null !== $GLOBALS['sim_sc']['multisite']; }
function wp_doing_ajax() { return ! empty( $GLOBALS['sim_ajax'] ); }
function admin_url( $p = '' ) { return 'http://sim/wp-admin/' . ltrim( $p, '/' ); }
function get_user_option( $k ) { return isset( $GLOBALS['sim_user_options'][ $k ] ) ? $GLOBALS['sim_user_options'][ $k ] : false; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['sim_options'] ) ? $GLOBALS['sim_options'][ $k ] : $d; }
function add_option( $k, $v ) { if ( ! array_key_exists( $k, $GLOBALS['sim_options'] ) ) { $GLOBALS['sim_options'][ $k ] = $v; } }
function get_site_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['sim_site_options'] ) ? $GLOBALS['sim_site_options'][ $k ] : $d; }
function add_site_option( $k, $v ) { if ( ! array_key_exists( $k, $GLOBALS['sim_site_options'] ) ) { $GLOBALS['sim_site_options'][ $k ] = $v; } }
function get_site() { return (object) array( 'registered' => $GLOBALS['sim_sc']['multisite']['registered'] ); }
function post_type_exists( $p ) { return isset( $GLOBALS['sim_ptypes'][ $p ] ); }
function get_post_type_object( $p ) { return isset( $GLOBALS['sim_ptypes'][ $p ] ) ? $GLOBALS['sim_ptypes'][ $p ] : null; }
function get_taxonomy( $t ) { return isset( $GLOBALS['sim_taxes'][ $t ] ) ? $GLOBALS['sim_taxes'][ $t ] : false; }
function get_current_screen() { return isset( $GLOBALS['sim_screen'] ) ? $GLOBALS['sim_screen'] : null; }
function wp_die( $m = '', $c = 0 ) { throw new SimDie( (string) $m ); }
function wp_safe_redirect( $u ) { throw new SimDie( 'REDIRECT ' . $u ); }
function add_query_arg( ...$a ) {
	if ( is_array( $a[0] ) ) {
		$args = $a[0];
		$url  = isset( $a[1] ) ? $a[1] : 'http://sim/wp-admin/current';
	} else {
		$args = array( $a[0] => $a[1] );
		$url  = isset( $a[2] ) ? $a[2] : 'http://sim/wp-admin/current';
	}

	foreach ( $args as $k => $v ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v;
	}

	return $url;
}
function wp_nonce_url( $u, $a ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . '_wpnonce=nonce'; }
function wp_create_nonce( $a ) { return 'nonce'; }
function wp_add_inline_style( $handle = '', $data = '' ) {
	$GLOBALS['sim_styles'][] = array( (string) $handle, (string) $data );
}
function wp_add_inline_script() {}
function wp_enqueue_script() {}
function get_plugin_data() { return array(); }
function submit_button( $text, $type = '', $name = 'submit', $wrap = true, $attrs = array() ) { echo '<input type="submit" name="' . esc_attr( $name ) . '" id="' . esc_attr( isset( $attrs['id'] ) ? $attrs['id'] : $name ) . '" value="' . esc_attr( $text ) . '">'; }
function wp_get_current_user() { return (object) array( 'user_registered' => $GLOBALS['sim_sc']['user_registered'] ); }
function wp_count_posts( $post_type ) { return (object) array( 'publish' => isset( $GLOBALS['sim_sc']['posts'][ $post_type ] ) ? (int) $GLOBALS['sim_sc']['posts'][ $post_type ] : 0, 'auto-draft' => 3 ); }
function wp_sprintf( $pattern, $items ) {
	$items = array_values( (array) $items );
	$last  = array_pop( $items );

	return $items ? implode( ', ', $items ) . ' and ' . $last : (string) $last;
}

require __DIR__ . '/core/admin-plugin.php';

if ( $sc['cmb2'] ) {
	/**
	 * A CMB2 options-page box, as far as the framework asks.
	 */
	class SimCmb {
		public $cmb_id;
		protected $props;

		public function __construct( $key, $tab_group, $fields ) {
			$this->cmb_id = $key . '_page';
			$this->props  = array(
				'object_types' => array( 'options-page' ),
				'option_key'   => $key,
				'tab_group'    => $tab_group,
				'fields'       => array_fill_keys( $fields, array() ),
			);
		}

		public function prop( $name ) {
			return isset( $this->props[ $name ] ) ? $this->props[ $name ] : null;
		}

		public function set_prop( $name, $value ) {
			$this->props[ $name ] = $value;
		}

		public function options_page_keys() {
			return array( $this->props['option_key'] );
		}

		public function show_form() {
			foreach ( array_keys( $this->props['fields'] ) as $field ) {
				echo '<div class="cmb-row"><label for="' . $field . '">' . $field . '</label><input type="text" name="' . $field . '" id="' . $field . '"></div>';
			}
		}
	}

	class CMB2_Boxes {
		public static $boxes = array();

		public static function get_all() {
			return self::$boxes;
		}
	}
}

/**
 * CP Essentials stand-in, mirroring its includes/Admin/Menu.php.
 *
 * @param int $screens_priority admin_menu priority of its screens.
 */
function sim_essentials( $screens_priority ) {
	define( 'CP_ESSENTIALS_VERSION', 'sim' );

	add_action(
		'admin_menu',
		function () {
			if ( class_exists( 'ChurchPlugins\\Admin\\Menu' ) ) {
				\ChurchPlugins\Admin\Menu::add_support();
			}
		},
		1
	);
	add_action(
		'admin_menu',
		function () {
			global $menu;

			if ( class_exists( 'ChurchPlugins\\Admin\\Menu' ) ) {
				return;
			}

			foreach ( (array) $menu as $item ) {
				if ( isset( $item[2] ) && 'church-plugins' === $item[2] ) {
					return;
				}
			}

			add_menu_page( 'Church Plugins', 'Church Plugins', (string) apply_filters( 'cp_admin_menu_capability', 'manage_options' ), 'church-plugins', '', '', apply_filters( 'cp_admin_menu_position', 80 ) );
		},
		9
	);
	add_action(
		'admin_menu',
		function () {
			foreach ( $GLOBALS['sim_sc']['essentials_types'] as $pt => $label ) {
				add_submenu_page( 'church-plugins', $label, $label, 'edit_posts', 'edit.php?post_type=' . $pt );
			}

			add_submenu_page( 'church-plugins', 'Church Info', 'Church Info', 'manage_options', 'cp-essentials', '__return_null' );
			add_submenu_page( 'church-plugins', 'Settings', 'Settings', 'manage_options', 'cp-essentials-settings', '__return_null' );
		},
		$screens_priority
	);
	add_filter(
		'cp_admin_menu_capability',
		function ( $c ) {
			return $GLOBALS['sim_sc']['essentials_types'] ? 'edit_posts' : $c;
		}
	);
	add_filter(
		'cp_admin_menu_position',
		function ( $p ) {
			return $GLOBALS['sim_sc']['essentials_types'] ? 26 : $p;
		}
	);
	add_action(
		'admin_menu',
		function () {
			global $menu, $submenu;

			$seen = false;

			foreach ( (array) $menu as $i => $item ) {
				if ( ! isset( $item[2] ) || 'church-plugins' !== $item[2] ) {
					continue;
				}

				if ( $seen ) {
					unset( $menu[ $i ] );
				}

				$seen = true;
			}

			if ( empty( $submenu['church-plugins'] ) ) {
				return;
			}

			$first = array( 'cp-essentials-home' );

			foreach ( $GLOBALS['sim_sc']['essentials_types'] as $pt => $label ) {
				$first[] = 'edit.php?post_type=' . $pt;
			}

			$first[] = 'cp-essentials';
			$first[] = 'cp-essentials-settings';
			$order   = apply_filters(
				'cp_essentials_menu_order',
				array(
					'first' => $first,
					'last'  => array( 'cp-essentials-licenses' ),
				)
			);
			$head    = array();
			$tail    = array();
			$rest    = array();

			foreach ( $submenu['church-plugins'] as $item ) {
				$slug = isset( $item[2] ) ? (string) $item[2] : '';

				if ( 'church-plugins' === $slug ) {
					continue;
				}

				$p = array_search( $slug, (array) $order['first'], true );

				if ( false !== $p ) {
					$head[ $p ] = $item;
					continue;
				}

				$p = array_search( $slug, (array) $order['last'], true );

				if ( false !== $p ) {
					$tail[ $p ] = $item;
					continue;
				}

				$rest[] = $item;
			}

			ksort( $head );
			ksort( $tail );
			$submenu['church-plugins'] = array_merge( array_values( $head ), $rest, array_values( $tail ) );
		},
		1000
	);
}

// ---- "Plugins loaded": Essentials (early), the framework, Essentials (late), products. ----

if ( in_array( $sc['essentials'], array( 'early', 'old9' ), true ) ) {
	sim_essentials( 'old9' === $sc['essentials'] ? 9 : 10 );
}

if ( $sc['optout'] ) {
	add_filter( 'cp_admin_shared_menu', '__return_false' );
}

// The framework, as Admin\_Init::includes() loads it.
foreach ( array( 'MenuLayout', 'Menu', 'Licenses', 'MenuNotice' ) as $file ) {
	require $fw . 'Admin/' . $file . '.php';
}

\ChurchPlugins\Admin\Menu::get_instance();
\ChurchPlugins\Admin\Licenses::get_instance();
\ChurchPlugins\Admin\MenuNotice::get_instance();

if ( 'late' === $sc['essentials'] ) {
	sim_essentials( 10 );
}

/**
 * Setup\Admin\License stand-in.
 */
class SimLicense {
	public $id;
	public $product;

	public function __construct( $id, $product ) {
		$this->id      = $id;
		$this->product = $product;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_plugin_file() {
		return WP_PLUGIN_DIR . '/' . $this->product . '/' . $this->product . '.php';
	}

	public function get( $key ) {
		return 'license' === $key && isset( $GLOBALS['sim_sc']['license_keys'][ $this->id ] ) ? $GLOBALS['sim_sc']['license_keys'][ $this->id ] : '';
	}

	public function is_active() {
		return false;
	}
}

// How the released plugins register their screens.
$defs = array(
	'cp-library' => array(
		'types'    => array( 'cpl_item_type' => 'Series' ),
		'subtypes' => array(
			'cpl_item'    => 'Messages',
			'cpl_speaker' => 'Speakers',
		),
		'taxes'    => array( 'cpl_topic' => 'Topics' ),
		'settings' => array( 'cpl_main_options', 'edit.php?post_type=cpl_item_type' ),
		'subpages' => array( 'cpl_item_options', 'cpl_advanced_options' ),
		'license'  => 'cpl_license',
		'extra'    => array( array( 'edit.php?post_type=cpl_item_type', 'Tools', 'manage_options', 'cp-library-tools' ) ),
	),
	'cp-groups'  => array(
		'types'    => array( 'cp_group' => 'Groups' ),
		'taxes'    => array( 'cp_group_type' => 'Types' ),
		'settings' => array( 'cp_groups_main_options', 'edit.php?post_type=cp_group' ),
		'subpages' => array( 'cp_groups_advanced_options' ),
		'license'  => 'cp_groups_license',
	),
	'cp-locations' => array(
		'types'    => array( 'cploc_location' => 'Locations' ),
		'settings' => array( 'cploc_main_options', 'edit.php?post_type=cploc_location' ),
		'subpages' => array(),
		'license'  => 'cploc_license',
	),
	'cp-staff'   => array(
		'types'    => array( 'cp_staff' => 'Staff' ),
		'settings' => array( 'cp_staff_staff_options', 'edit.php?post_type=cp_staff' ),
		'subpages' => array(),
		'license'  => 'cp_staff_license',
	),
	'cp-live'    => array(
		'types'    => array(),
		'settings' => array( 'cp_live_main_options', 'options-general.php' ),
		'subpages' => array( 'cp_live_youtube_options' ),
		'license'  => 'cp_live_license',
	),
	// Declares support and adds its own entry; its license has no CMB2 box.
	'cp-sync'    => array(
		'types'   => array(),
		'license' => 'cps_license',
		'sync'    => true,
	),
);

// `default_menu_item=item` registers Messages as the top-level entry.
// `item_type` (the fixture used above) registers Series.
if ( 'item' === $sc['sermon_menu'] ) {
	$defs['cp-library']['types']      = array( 'cpl_item' => 'Messages' );
	$defs['cp-library']['subtypes']   = array(
		'cpl_item_type' => 'Series',
		'cpl_speaker'   => 'Speakers',
	);
	$defs['cp-library']['settings'][1] = 'edit.php?post_type=cpl_item';
	$defs['cp-library']['extra'][0][0] = 'edit.php?post_type=cpl_item';
}

foreach ( $sc['products'] as $key ) {
	$d = $defs[ $key ];

	foreach ( $d['types'] + ( isset( $d['subtypes'] ) ? $d['subtypes'] : array() ) as $pt => $label ) {
		$GLOBALS['sim_ptypes'][ $pt ] = (object) array(
			'name'    => $pt,
			'show_ui' => true,
			'labels'  => (object) array(
				'name'      => $label,
				'menu_name' => $label,
				'all_items' => 'All ' . $label,
				'add_new'   => 'Add New',
			),
			'cap'     => (object) array(
				'edit_posts'   => 'edit_posts',
				'create_posts' => 'edit_posts',
			),
		);
	}

	foreach ( isset( $d['taxes'] ) ? $d['taxes'] : array() as $tx => $label ) {
		$GLOBALS['sim_taxes'][ $tx ] = (object) array(
			'name'    => $tx,
			'show_ui' => true,
			'cap'     => (object) array( 'manage_terms' => 'manage_categories' ),
			'label'   => $label,
			'of'      => key( $d['types'] ),
		);
	}

	if ( ! empty( $d['license'] ) ) {
		\ChurchPlugins\Admin\Licenses::register( new SimLicense( $d['license'], $key ) );
	}

	if ( $sc['cmb2'] && ! empty( $d['settings'] ) ) {
		foreach ( array_merge( array( $d['settings'][0] ), $d['subpages'] ) as $page ) {
			CMB2_Boxes::$boxes[] = new SimCmb( $page, $d['settings'][0], array( 'title' ) );
		}

		CMB2_Boxes::$boxes[] = new SimCmb( $d['license'], $d['settings'][0], array( 'license', 'beta' ) );
	}

	if ( ! empty( $d['sync'] ) ) {
		\ChurchPlugins\Admin\Menu::add_support();
		add_action(
			'admin_menu',
			function () {
				$GLOBALS['sim_sync_hook'] = add_submenu_page( 'church-plugins', 'CP Sync', 'CP Sync', 'manage_options', 'cps_settings', '__return_null' );
			}
		);
	}

	// CMB2 options pages, admin_menu priority 10.
	add_action(
		'admin_menu',
		function () use ( $d ) {
			if ( ! empty( $d['settings'] ) ) {
				add_submenu_page( $d['settings'][1], 'Settings', 'Settings', 'manage_options', $d['settings'][0], '__return_null' );

				foreach ( $d['subpages'] as $sub ) {
					add_submenu_page( $d['settings'][0], 'Settings', 'Settings', 'manage_options', $sub, '__return_null' );
				}

				if ( ! empty( $d['license'] ) ) {
					add_submenu_page( $d['settings'][0], 'License', 'License', 'manage_options', $d['license'], '__return_null' );
				}
			}

			foreach ( isset( $d['subtypes'] ) ? $d['subtypes'] : array() as $pt => $label ) {
				add_submenu_page( 'edit.php?post_type=' . key( $d['types'] ), $label, $label, 'edit_posts', 'edit.php?post_type=' . $pt );
			}

			foreach ( isset( $d['extra'] ) ? $d['extra'] : array() as $e ) {
				add_submenu_page( $e[0], $e[1], $e[1], $e[2], $e[3], '__return_null' );
			}
		}
	);
}

if ( $sc['settings_tabs'] ) {
	add_filter(
		'cp_admin_settings_tabs',
		function ( $tabs ) {
			return $GLOBALS['sim_sc']['settings_tabs'] + $tabs;
		}
	);
}

if ( $sc['direct_entries'] ) {
	add_action(
		'admin_menu',
		function () {
			global $submenu;

			foreach ( $GLOBALS['sim_sc']['direct_entries'] as $entry ) {
				$submenu['church-plugins'][] = $entry;
			}
		},
		20
	);
}

// ---- An AJAX request first: admin_init without a menu. ----

if ( $sc['ajax_first'] ) {
	$GLOBALS['sim_ajax'] = true;
	$pagenow             = 'admin-ajax.php';
	do_action( 'admin_init' );
	$GLOBALS['sim_ajax'] = false;
}

// ---- wp-admin/menu.php stand-in: core's menu, plus the products' post types. ----

global $menu, $submenu, $pagenow, $plugin_page, $typenow, $parent_file, $submenu_file, $admin_page_hooks, $_registered_pages, $_parent_pages, $_wp_real_parent_file, $_wp_menu_nopriv, $_wp_submenu_nopriv, $compat;

$menu    = array(
	2  => array( 'Dashboard', 'read', 'index.php', '', 'menu-top', 'menu-dashboard', '' ),
	5  => array( 'Posts', 'edit_posts', 'edit.php', '', 'menu-top', 'menu-posts', '' ),
	20 => array( 'Pages', 'edit_pages', 'edit.php?post_type=page', '', 'menu-top', 'menu-pages', '' ),
	25 => array( 'Comments', 'edit_posts', 'edit-comments.php', '', 'menu-top', 'menu-comments', '' ),
	70 => array( 'Users', 'list_users', 'users.php', '', 'menu-top', 'menu-users', '' ),
	80 => array( 'Settings', 'manage_options', 'options-general.php', '', 'menu-top', 'menu-settings', '' ),
);
$submenu = array(
	'index.php'               => array( 0 => array( 'Home', 'read', 'index.php' ) ),
	'edit.php'                => array(
		5  => array( 'All Posts', 'edit_posts', 'edit.php' ),
		10 => array( 'Add New', 'edit_posts', 'post-new.php' ),
	),
	'edit.php?post_type=page' => array( 5 => array( 'All Pages', 'edit_pages', 'edit.php?post_type=page' ) ),
	'users.php'               => array(
		5  => array( 'All Users', 'list_users', 'users.php' ),
		15 => array( 'Profile', 'read', 'profile.php' ),
	),
	'options-general.php'     => array(
		10 => array( 'General', 'manage_options', 'options-general.php' ),
		15 => array( 'Writing', 'manage_options', 'options-writing.php' ),
	),
);
// As wp-admin/menu.php sets it: hook suffixes are named after these.
$compat  = array(
	'index'           => 'dashboard',
	'edit'            => 'posts',
	'post'            => 'posts',
	'upload'          => 'media',
	'link-manager'    => 'links',
	'edit-pages'      => 'pages',
	'page'            => 'pages',
	'edit-comments'   => 'comments',
	'options-general' => 'settings',
	'themes'          => 'appearance',
);
$pos     = 26;

foreach ( $sc['products'] as $key ) {
	foreach ( $defs[ $key ]['types'] as $pt => $label ) {
		$file = 'edit.php?post_type=' . $pt;

		while ( isset( $menu[ $pos ] ) ) {
			++$pos;
		}

		$cap                  = isset( $sc['area_caps'][ $pt ] ) ? $sc['area_caps'][ $pt ] : 'edit_posts';
		$menu[ $pos ]         = array( $label, $cap, $file, '', 'menu-top', 'menu-posts-' . $pt, '' );
		$submenu[ $file ][5]  = array( 'All ' . $label, $cap, $file );
		$submenu[ $file ][10] = array( 'Add New', $cap, 'post-new.php?post_type=' . $pt );
		$i                    = 15;

		foreach ( $GLOBALS['sim_taxes'] as $tx ) {
			if ( $tx->of === $pt ) {
				$submenu[ $file ][ $i++ ] = array( $tx->label, 'manage_categories', 'edit-tags.php?taxonomy=' . $tx->name . '&amp;post_type=' . $pt );
			}
		}
	}
}

// Core builds the menu once per request, then checks the page asked for. The
// build does not depend on the page, so it is built once here (for the
// Dashboard) and core's check is asked again for each request below.
$pagenow                   = 'index.php';
$typenow                   = '';
$_GET                      = array();
$_SERVER['REQUEST_METHOD'] = 'GET';

$out = array();

try {
	require __DIR__ . '/core/admin-menu.php';
} catch ( SimDie $e ) {
	$out['dashboard_denied'] = $e->getMessage();
}

$out['top'] = array();

foreach ( $menu as $item ) {
	$out['top'][ $item[2] ] = $item[1];
}

$out['shared'] = array();

foreach ( isset( $submenu['church-plugins'] ) ? $submenu['church-plugins'] : array() as $item ) {
	$out['shared'][] = array(
		'label' => $item[0],
		'slug'  => $item[2],
		'cap'   => $item[1],
		'can'   => current_user_can( $item[1] ),
	);
}

// Where the top-level item links, as _wp_menu_output() decides: the first
// submenu item, or the parent's own (empty) page when it has none.
$out['top_link'] = null;

if ( isset( $out['top']['church-plugins'] ) ) {
	$first           = empty( $submenu['church-plugins'] ) ? null : array_values( $submenu['church-plugins'] )[0];
	$out['top_link'] = array(
		'slug' => $first ? $first[2] : 'church-plugins',
		'can'  => $first ? current_user_can( $first[1] ) : false,
	);
}

$shared_menu           = \ChurchPlugins\Admin\Menu::get_instance();
$out['areas']          = array_keys( $shared_menu->areas() );
$out['planned']        = array_keys( $shared_menu->planned_areas() );
$out['settings_entry'] = $shared_menu->settings_entry();
$out['sync_hook']      = isset( $GLOBALS['sim_sync_hook'] ) ? $GLOBALS['sim_sync_hook'] : null;

// ---- Each request: core's access check, the page hook, admin_init, the highlight. ----

$out['requests'] = array();

foreach ( array( '' => array( 'pagenow' => 'index.php', 'get' => array() ) ) + $sc['requests'] as $name => $request ) {
	$pagenow = $request['pagenow'];
	$_GET    = $request['get'];
	$typenow = isset( $request['typenow'] ) ? $request['typenow'] : ( isset( $_GET['post_type'] ) ? $_GET['post_type'] : '' );

	if ( isset( $_GET['page'] ) ) {
		$plugin_page = plugin_basename( $_GET['page'] );
	} else {
		$plugin_page = null;
	}

	$GLOBALS['sim_screen'] = isset( $request['screen'] ) ? new SimScreen( $request['screen'] ) : null;

	$result = array( 'access' => user_can_access_admin_page() );

	if ( isset( $plugin_page ) ) {
		$result['hook'] = get_plugin_page_hook( $plugin_page, $typenow ? $pagenow . '?post_type=' . $typenow : $pagenow );

		if ( ! $result['hook'] ) {
			$result['hook'] = get_plugin_page_hook( $plugin_page, $plugin_page );
		}
	}

	try {
		do_action( 'admin_init' );
	} catch ( SimDie $e ) {
		$result['redirect'] = 0 === strpos( $e->getMessage(), 'REDIRECT ' ) ? substr( $e->getMessage(), 9 ) : $e->getMessage();
	}

	if ( isset( $request['screen'] ) ) {
		$parent_file         = apply_filters( 'parent_file', isset( $request['parent_file'] ) ? $request['parent_file'] : $pagenow );
		$submenu_file        = apply_filters( 'submenu_file', null, $parent_file );
		$result['highlight'] = array( $parent_file, $submenu_file );
		do_action( 'adminmenu' );
	}

	if ( ! empty( $request['tabs'] ) ) {
		$GLOBALS['sim_styles'] = array();
		ob_start();
		$shared_menu->tabs();
		$result['tabs']       = ob_get_clean();
		$result['body_class'] = $shared_menu->body_class( 'wp-admin' );
		$shared_menu->styles();
		$result['styles'] = $GLOBALS['sim_styles'];
	}

	// wp-admin/admin.php includes admin-header.php after load-{$hook} and
	// before the page callback. A page with no callback is included as a
	// plugin file, which dies with "Cannot load %s."
	if ( ! empty( $request['dispatch'] ) && isset( $plugin_page ) && ! isset( $result['redirect'] ) ) {
		if ( empty( $result['access'] ) ) {
			$result['die'] = 'Sorry, you are not allowed to access this page.';
		} elseif ( ! empty( $result['hook'] ) ) {
			$result['output']      = '';
			$result['header_sent'] = false;
			$buffers               = ob_get_level();

			try {
				do_action( 'load-' . $result['hook'] );
				$result['header_sent'] = true;
				$result['output']      = '<div id="wpbody">';
				ob_start();
				do_action( $result['hook'] );
				$result['output']    .= ob_get_clean();
				$result['dispatched'] = true;
			} catch ( SimDie $e ) {
				while ( ob_get_level() > $buffers ) {
					$result['output'] .= ob_get_clean();
				}

				$message = $e->getMessage();

				if ( 0 === strpos( $message, 'REDIRECT ' ) ) {
					$result['redirect']    = substr( $message, 9 );
					$result['redirect_at'] = $result['header_sent'] ? 'callback' : 'load';
				} else {
					$result['die'] = $message;
				}
			}
		} else {
			$result['die'] = 'Cannot load ' . htmlentities( $plugin_page ) . '.';
		}
	}

	if ( '' !== $name ) {
		$out['requests'][ $name ] = $result;
	}
}

$pagenow               = 'index.php';
$_GET                  = array();
$plugin_page           = null;
$GLOBALS['sim_screen'] = null;

$out['notice_state'] = get_option( 'cp_admin_menu_layout', null );
$out['network_since'] = isset( $GLOBALS['sim_site_options']['cp_admin_menu_layout_since'] );

if ( is_array( $out['notice_state'] ) ) {
	unset( $out['notice_state']['since'] );
}

if ( in_array( 'notice', $sc['render'], true ) ) {
	ob_start();
	do_action( 'admin_notices' );
	$out['notice'] = ob_get_clean();
}

if ( in_array( 'licenses', $sc['render'], true ) ) {
	ob_start();
	\ChurchPlugins\Admin\Licenses::get_instance()->render();
	$out['licenses'] = ob_get_clean();
}

echo json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
