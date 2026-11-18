<?php
/**
 * Shared top-level "Church Plugins" admin menu.
 *
 * @package ChurchPlugins
 */

namespace ChurchPlugins\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Shared "Church Plugins" parent menu.
 *
 * Since 1.1.23 every Church Plugins product's screens live under this one
 * menu, whether or not the product calls anything here:
 *
 *   Church Plugins
 *     Home               (CP Essentials, planned)
 *     Messages           tabs: Messages · Series · Speakers · Topics · … · Tools
 *     Events             (CP Essentials)
 *     Groups             tabs: Groups · Types · Categories · Life Stages
 *     Staff              tabs: Staff · Departments
 *     Locations · Resources · Notification Bars
 *     Church Info        (CP Essentials)
 *     Sync               (CP Sync)
 *     Settings           a tab per product (each product's own tabs below)
 *     Licenses           a row per paid product
 *
 * How: products keep registering their post types and pages exactly as
 * before. On `admin_menu` (priority 998) the framework removes each
 * product's top-level menu and adds one entry for it here. The old submenu
 * arrays stay in place, so every screen keeps its URL, hook suffix and
 * capability check; their items become tabs across the top of the area's
 * screens. Settings pages that were in WordPress's Settings menu are hidden
 * there and reached from Settings. See {@see MenuLayout} for the rules.
 *
 * Because the newest framework copy serves every product on a site, this
 * also moves products that were never updated for it.
 *
 * Filters:
 * - `cp_admin_shared_menu`      false keeps the old layout (opt-out).
 * - `cp_admin_products`         the product table.
 * - `cp_admin_menu_capability`  capability of the parent (default
 *                               `edit_posts` while it holds content, else
 *                               `manage_options`).
 * - `cp_admin_menu_position`    position (26 while it holds content, else 80).
 * - `cp_admin_area_tabs`        tabs of an area.
 * - `cp_admin_settings_tabs`    tabs of the Settings screen.
 *
 * @since 1.1.16
 * @since 1.1.23 Hosts every product's screens.
 */
class Menu {

	/**
	 * Single instance.
	 *
	 * @var Menu
	 */
	protected static $_instance;

	/**
	 * The parent menu slug, used by products as their submenu parent.
	 *
	 * @var string
	 */
	const SLUG = 'church-plugins';

	/**
	 * Slug of the Licenses screen the framework provides.
	 *
	 * @since 1.1.23
	 * @var string
	 */
	const LICENSES_SLUG = 'cp-licenses';

	/**
	 * Settings screen hosted by CP Essentials, when it registers one.
	 *
	 * @since 1.1.23
	 * @var string
	 */
	const ESSENTIALS_SETTINGS = 'cp-essentials-settings';

	/**
	 * Position of the menu while it holds content screens (under Pages and
	 * Comments, as CP Essentials places it).
	 *
	 * @since 1.1.23
	 * @var int
	 */
	const CONTENT_POSITION = 26;

	/**
	 * Whether at least one product has declared support for the shared menu.
	 *
	 * @var bool
	 */
	protected $has_support = false;

	/**
	 * Areas moved under the shared menu this request.
	 *
	 * @var array<string, array>
	 */
	protected $areas = array();

	/**
	 * Every area the shared menu takes this request, whoever is looking:
	 * {@see self::$areas} before it is narrowed to what the user can open.
	 *
	 * @since 1.1.23
	 * @var array<string, array>
	 */
	protected $planned = array();

	/**
	 * Slug of the Settings entry ('' when there is none).
	 *
	 * @var string
	 */
	protected $settings_entry = '';

	/**
	 * Settings tabs this request, cached once the menu is built.
	 *
	 * @var array|null
	 */
	protected $settings_tabs = null;

	/**
	 * Whether the screen being printed is one of the moved screens.
	 *
	 * @var bool
	 */
	protected $is_ours = false;

	/**
	 * Parent mappings to undo after the menu is printed.
	 *
	 * @var array|null
	 */
	protected $restore = null;

	/**
	 * Tab groups this request, cached once the menu is built.
	 *
	 * @var array|null
	 */
	protected $groups = null;

	/**
	 * Group and tab of a screen, by screen target, cached once the menu is built.
	 *
	 * @var array<string, array|null>
	 */
	protected $current = array();

	/**
	 * Get the single instance.
	 *
	 * @return Menu
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Menu ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Priority on `admin_menu` at which the parent menu is registered.
	 *
	 * Before 9: CP Essentials releases that add their screens at priority 9
	 * hooked when their file loaded, so at an equal priority they would run
	 * before this class (hooked on `plugins_loaded`) and find no parent; core
	 * then registers a page as `admin_page_<slug>` and refuses to open it.
	 *
	 * @since 1.1.23
	 * @var int
	 */
	const PARENT_PRIORITY = 8;

	/**
	 * Constructor. Registers the parent menu early (priority 8, 9 up to
	 * 1.1.22) so products adding submenus at priority 9 or the default (10)
	 * can attach to it.
	 */
	protected function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), self::PARENT_PRIORITY );
		// Where the parent was registered up to 1.1.22: covers a product that
		// declares support after priority 8.
		add_action( 'admin_menu', array( $this, 'register_menu_late' ), 9 );
		add_action( 'admin_menu', array( $this, 'relocate' ), 998 );
		// After all products have attached their submenus (default priority 10),
		// drop the self-referential parent item and put entries in order.
		add_action( 'admin_menu', array( $this, 'remove_parent_submenu' ), 999 );

		add_filter( 'parent_file', array( $this, 'parent_file' ), 20 );
		add_filter( 'submenu_file', array( $this, 'submenu_file' ), 20, 2 );
		add_filter( 'cp_essentials_menu_order', array( $this, 'essentials_order' ), 20 );
		add_action( 'admin_init', array( $this, 'redirect_old_settings_screens' ), 1 );
		add_action( 'all_admin_notices', array( $this, 'tabs' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Declare support for the shared Church Plugins menu.
	 *
	 * Since 1.1.23 products do not need to call this: the framework moves
	 * every product's screens here. Calling it still makes sure the parent
	 * exists for a product that adds its own submenu (CP Sync).
	 *
	 * @return string The parent menu slug, for use as a submenu parent.
	 * @since 1.1.16
	 */
	public static function add_support() {
		$instance              = self::get_instance();
		$instance->has_support = true;

		return self::SLUG;
	}

	/**
	 * The parent menu slug.
	 *
	 * @return string
	 * @since 1.1.16
	 */
	public static function get_slug() {
		return self::SLUG;
	}

	/**
	 * Whether any product has declared support for the shared menu.
	 *
	 * @return bool
	 * @since 1.1.16
	 */
	public function has_support() {
		return $this->has_support;
	}

	/**
	 * Whether products' screens move under the shared menu.
	 *
	 * Returning false from `cp_admin_shared_menu` keeps the layout of
	 * framework 1.1.22 and earlier: each product keeps its own top-level
	 * menu, settings and License tab. It is meant as a one-release escape
	 * hatch and will be removed.
	 *
	 * @return bool
	 * @since 1.1.23
	 */
	public static function enabled() {
		/**
		 * Whether Church Plugins products' screens move under the shared menu.
		 *
		 * @param bool $enabled Default true.
		 * @since 1.1.23
		 */
		return (bool) apply_filters( 'cp_admin_shared_menu', true );
	}

	/**
	 * The products the shared menu knows about.
	 *
	 * Known here (rather than declared by each product) so that products
	 * which were never updated for the shared menu still move.
	 *
	 * - `name`        product name.
	 * - `position`    slot in the menu (see {@see MenuLayout::fixed_slots()}).
	 * - `post_types`  post types whose top-level menu becomes the area, in
	 *                 order of preference (the first with a top-level menu).
	 * - `label_type`  post type whose plural label names the product's
	 *                 Settings tab (custom labels apply).
	 * - `settings`    slug of the product's main settings page.
	 * - `settings_label` Settings tab label when there is no `label_type`.
	 * - `menu`        slug of a page the product adds to the shared menu itself.
	 * - `menu_label`  label for that entry.
	 * - `taxonomies`  taxonomies the product owns: shown as tabs in its own
	 *                 area only, even when attached to other products' types.
	 *
	 * @return array<string, array>
	 * @since 1.1.23
	 */
	public static function products() {
		$products = array(
			'cp-library'           => array(
				'name'       => 'CP Sermons',
				'position'   => 10,
				'post_types' => array( 'cpl_item_type', 'cpl_item' ),
				'label_type' => 'cpl_item',
				'settings'   => 'cpl_main_options',
			),
			'cp-groups'            => array(
				'name'       => 'CP Groups',
				'position'   => 30,
				'post_types' => array( 'cp_group' ),
				'label_type' => 'cp_group',
				'settings'   => 'cp_groups_main_options',
			),
			'cp-staff'             => array(
				'name'       => 'CP Staff',
				'position'   => 40,
				'post_types' => array( 'cp_staff' ),
				'label_type' => 'cp_staff',
				'settings'   => 'cp_staff_staff_options',
			),
			'cp-locations'         => array(
				'name'       => 'CP Locations',
				'position'   => 50,
				'post_types' => array( 'cploc_location' ),
				'label_type' => 'cploc_location',
				'settings'   => 'cploc_main_options',
			),
			'cp-resources'         => array(
				'name'       => 'CP Resources',
				'position'   => 60,
				'post_types' => array( 'cp_resource' ),
				'label_type' => 'cp_resource',
				'settings'   => 'cp_resources_main_options',
				'taxonomies' => array( 'cp_resource_type', 'cp_resource_topic' ),
			),
			'cp-notification-bars' => array(
				'name'       => 'CP Notification Bars',
				'position'   => 70,
				'post_types' => array( 'cp_notification_bar' ),
				'label_type' => 'cp_notification_bar',
				'settings'   => 'cp_notification_bars_main_options',
			),
			'cp-live'              => array(
				'name'           => 'CP Live',
				'position'       => 75,
				'settings'       => 'cp_live_main_options',
				'settings_label' => __( 'Live', 'churchplugins' ),
			),
			'cp-sync'              => array(
				'name'       => 'CP Sync',
				'position'   => 90,
				'menu'       => 'cps_settings',
				'menu_label' => __( 'Sync', 'churchplugins' ),
			),
			'cp-connect'           => array(
				'name'           => 'CP Connect',
				'position'       => 92,
				'settings'       => 'cpc_main_options',
				'settings_label' => __( 'Connect', 'churchplugins' ),
			),
		);

		/**
		 * Filters the products the shared menu knows about.
		 *
		 * @param array $products key => { name, position, post_types?, label_type?, settings?, settings_label?, menu?, menu_label? }.
		 * @since 1.1.23
		 */
		$products = apply_filters( 'cp_admin_products', $products );

		return is_array( $products ) ? $products : array();
	}

	/**
	 * Post types registered through the framework's PostType class.
	 *
	 * @return string[]
	 */
	protected static function cp_post_types() {
		$class = '\ChurchPlugins\Setup\PostTypes\PostType';

		return class_exists( $class ) && method_exists( $class, 'registered_post_types' ) ? $class::registered_post_types() : array();
	}

	/**
	 * Areas that would move, from the current top-level menu.
	 *
	 * @return array<string, array>
	 */
	protected function plan() {
		global $menu;

		if ( ! self::enabled() ) {
			return array();
		}

		return MenuLayout::plan_areas( (array) $menu, self::products(), self::cp_post_types() );
	}

	/**
	 * Register the top-level Church Plugins menu.
	 *
	 * Registered when a product declared support, or when there is anything
	 * to move here: a product's content screens or a license.
	 *
	 * @return void
	 * @since 1.1.16
	 */
	public function register_menu() {
		$content = (bool) $this->plan();

		if ( ! $this->has_support && ! $content && ! ( self::enabled() && Licenses::has_any() ) ) {
			return;
		}

		/**
		 * Filters the capability required to see the Church Plugins menu.
		 *
		 * @param string $capability The capability. Default 'edit_posts' while the menu
		 *                           holds content screens, else 'manage_options'.
		 * @since 1.1.16
		 * @since 1.1.23 Defaults to 'edit_posts' while the menu holds content screens.
		 */
		$capability = apply_filters( 'cp_admin_menu_capability', $content ? 'edit_posts' : 'manage_options' );

		add_menu_page(
			__( 'Church Plugins', 'churchplugins' ),
			__( 'Church Plugins', 'churchplugins' ),
			$capability,
			self::SLUG,
			'',
			$this->get_menu_icon(),
			/**
			 * Filters the position of the Church Plugins menu.
			 *
			 * @param int|float $position The menu position. Default 26 while the menu
			 *                            holds content screens (under Pages and
			 *                            Comments), else 80.
			 * @since 1.1.16
			 * @since 1.1.23 Defaults to 26 while the menu holds content screens.
			 */
			apply_filters( 'cp_admin_menu_position', $content ? self::CONTENT_POSITION : 80 )
		);
	}

	/**
	 * Register the parent at priority 9 when it was not registered at
	 * {@see self::PARENT_PRIORITY}, for a product that declared support in
	 * between. Never adds a second parent.
	 *
	 * @return void
	 * @since 1.1.23
	 */
	public function register_menu_late() {
		if ( ! self::parent_registered() ) {
			$this->register_menu();
		}
	}

	/**
	 * Whether the parent menu was registered this request.
	 *
	 * @return bool
	 */
	protected static function parent_registered() {
		global $menu;

		foreach ( (array) $menu as $item ) {
			if ( isset( $item[2] ) && self::SLUG === $item[2] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Move products' screens under the shared menu.
	 *
	 * @return void
	 * @since 1.1.23
	 */
	public function relocate() {
		global $menu, $submenu, $_wp_real_parent_file;

		if ( ! self::enabled() || ! self::parent_registered() ) {
			return;
		}

		// Only the areas this user can open move. Entries are appended to
		// $submenu directly, without the capability check add_submenu_page()
		// does, and core links the top-level item to the first entry it finds:
		// an entry the user cannot open would be a link to a 403. An area the
		// user cannot open keeps its old top-level item, which core removes or
		// re-parents as it always has.
		$plan          = $this->plan();
		$this->planned = $plan;
		$this->areas   = MenuLayout::accessible_areas( $plan, 'current_user_can' );
		$this->groups  = null;
		$this->current = array();

		list( $menu, $submenu ) = MenuLayout::apply_areas( (array) $menu, (array) $submenu, $this->areas ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Moving menus is the point.

		$this->relabel_product_entries();

		Licenses::get_instance()->prepare_menu();

		$this->settings_tabs  = null;
		$this->settings_entry = '';
		$tabs                 = $this->settings_tabs();

		// Hide each product's settings page from the menu it was in (for
		// example WordPress's Settings menu); it is reached from Settings now.
		$real = is_array( $_wp_real_parent_file ) ? $_wp_real_parent_file : array();

		// Old top-level slugs of every planned area, moved or not.
		$area_parents = array_column( $plan, 'old' );

		foreach ( $tabs as $tab ) {
			// CP Essentials' Settings screen is the Settings entry itself.
			if ( empty( $tab['parent'] ) || empty( $tab['slug'] ) || self::ESSENTIALS_SETTINGS === $tab['slug'] ) {
				continue;
			}

			// A settings page under a product's own menu is never hidden. Where
			// the area moved, that menu is gone and its items are tabs. Where it
			// did not (this user cannot open the area: `manage_options` without
			// `edit_posts`), the top-level item is still there, and core decides
			// access to the page from the item's place under it: moved to a
			// hidden holder, core would fall back to the top-level item's
			// capability and refuse a page this user could open up to 1.1.22.
			if ( in_array( $tab['parent'], $area_parents, true ) ) {
				continue;
			}

			if ( ! $this->is_visible_parent( $tab['parent'] ) ) {
				continue;
			}

			list( $submenu, $real ) = MenuLayout::hide_item( (array) $submenu, $real, $tab['parent'], $tab['slug'] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$_wp_real_parent_file = $real; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->add_settings_entry( $tabs );

		Licenses::get_instance()->add_page();
	}

	/**
	 * Send a product settings page opened through a WordPress menu file
	 * (CP Live's old `options-general.php?page=cp_live_main_options`) to the
	 * same page under admin.php. Both addresses open the same page; under
	 * options-general.php core would open WordPress's Settings menu.
	 *
	 * @return void
	 * @since 1.1.23
	 */
	public function redirect_old_settings_screens() {
		global $pagenow;

		if ( ! self::enabled() || wp_doing_ajax() || in_array( $pagenow, array( 'admin.php', 'edit.php', 'admin-post.php' ), true ) ) {
			return;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads which screen was asked for.
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'GET' !== $method || '' === $page ) {
			return;
		}

		$slugs = array();

		foreach ( self::products() as $product ) {
			if ( ! empty( $product['settings'] ) ) {
				$slugs[] = (string) $product['settings'];
			}
		}

		if ( ! in_array( $page, $slugs, true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed through to the same screen, keys and values encoded.
		$query = array_filter( (array) wp_unslash( $_GET ), 'is_string' );

		wp_safe_redirect( admin_url( 'admin.php' ) . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) );
		exit;
	}

	/**
	 * Whether a submenu parent is printed in the menu (a top-level item, or
	 * the shared parent itself).
	 *
	 * @param string $parent Parent slug.
	 * @return bool
	 */
	protected function is_visible_parent( $parent ) {
		global $menu;

		foreach ( (array) $menu as $item ) {
			if ( isset( $item[2] ) && $parent === $item[2] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Give product entries that a product adds itself their menu label
	 * (CP Sync's "CP Sync" becomes "Sync").
	 *
	 * @return void
	 */
	protected function relabel_product_entries() {
		global $submenu;

		if ( empty( $submenu[ self::SLUG ] ) ) {
			return;
		}

		foreach ( self::products() as $product ) {
			if ( empty( $product['menu'] ) || empty( $product['menu_label'] ) ) {
				continue;
			}

			foreach ( $submenu[ self::SLUG ] as $index => $item ) {
				if ( isset( $item[2] ) && $product['menu'] === $item[2] ) {
					$submenu[ self::SLUG ][ $index ][0] = $product['menu_label']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				}
			}
		}
	}

	/**
	 * Add the Settings entry: CP Essentials' Settings screen when it has one,
	 * else a link to the first product's settings the current user can open.
	 * No entry when the user can open none of them (the entry is appended to
	 * $submenu directly, so nothing else checks).
	 *
	 * @param array $tabs Settings tabs.
	 * @return void
	 */
	protected function add_settings_entry( array $tabs ) {
		global $submenu;

		foreach ( (array) ( isset( $submenu[ self::SLUG ] ) ? $submenu[ self::SLUG ] : array() ) as $item ) {
			if ( isset( $item[2] ) && self::ESSENTIALS_SETTINGS === $item[2] ) {
				$this->settings_entry = self::ESSENTIALS_SETTINGS;
				return;
			}
		}

		$first = null;

		foreach ( $tabs as $tab ) {
			if ( ! empty( $tab['path'] ) && self::can_open( $tab ) ) {
				$first = $tab;
				break;
			}
		}

		if ( ! $first ) {
			return;
		}

		$first['capability'] = isset( $first['capability'] ) && '' !== $first['capability'] ? $first['capability'] : 'manage_options';

		$this->settings_entry       = $first['path'];
		$submenu[ self::SLUG ][]    = array( __( 'Settings', 'churchplugins' ), $first['capability'], $first['path'], __( 'Settings', 'churchplugins' ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Find a submenu item by slug anywhere in $submenu.
	 *
	 * @param string $slug Item slug.
	 * @return array{parent: string, item: array}|null
	 */
	protected static function find_item( $slug ) {
		global $submenu, $_wp_real_parent_file;

		foreach ( (array) $submenu as $parent => $items ) {
			foreach ( (array) $items as $item ) {
				if ( isset( $item[2] ) && (string) $slug === (string) $item[2] ) {
					$parent = (string) $parent;

					if ( 0 === strpos( $parent, MenuLayout::HOLDER_PREFIX ) && isset( $_wp_real_parent_file[ $parent ] ) ) {
						$parent = $_wp_real_parent_file[ $parent ];
					}

					return array(
						'parent' => $parent,
						'item'   => $item,
					);
				}
			}
		}

		return null;
	}

	/**
	 * Slugs of the products' main settings pages that are registered this
	 * request, whoever is looking: the page has a CMB2 options box, or is in
	 * the menu. (Core leaves a page out of the menu for a user who lacks its
	 * capability, so the menu alone would depend on the user.)
	 *
	 * @return string[]
	 * @since 1.1.23
	 */
	public static function registered_settings_pages() {
		$boxes = array();

		if ( class_exists( '\CMB2_Boxes' ) ) {
			foreach ( \CMB2_Boxes::get_all() as $cmb ) {
				if ( ! in_array( 'options-page', (array) $cmb->prop( 'object_types' ), true ) ) {
					continue;
				}

				$keys = $cmb->options_page_keys();

				if ( ! empty( $keys[0] ) ) {
					$boxes[] = (string) $keys[0];
				}
			}
		}

		$pages = array();

		foreach ( self::products() as $product ) {
			$slug = empty( $product['settings'] ) ? '' : (string) $product['settings'];

			if ( '' !== $slug && ( in_array( $slug, $boxes, true ) || self::find_item( $slug ) ) ) {
				$pages[] = $slug;
			}
		}

		return $pages;
	}

	/**
	 * Tabs of the Settings screen: one per product with settings, each
	 * covering every page of that product's settings (its own tabs).
	 *
	 * @return array[] [ { key, label, path, capability, pages, parent, slug, position } ]
	 * @since 1.1.23
	 */
	public function settings_tabs() {
		if ( null !== $this->settings_tabs ) {
			return $this->settings_tabs;
		}

		$tabs = array();

		if ( ! self::enabled() ) {
			return $tabs;
		}

		$essentials = self::find_item( self::ESSENTIALS_SETTINGS );

		if ( $essentials && self::SLUG === $essentials['parent'] ) {
			$tabs['cp-essentials'] = array(
				'key'        => 'cp-essentials',
				'label'      => __( 'Essentials', 'churchplugins' ),
				'path'       => 'admin.php?page=' . self::ESSENTIALS_SETTINGS,
				'capability' => isset( $essentials['item'][1] ) ? $essentials['item'][1] : 'manage_options',
				'pages'      => array( self::ESSENTIALS_SETTINGS ),
				'parent'     => self::SLUG,
				'slug'       => self::ESSENTIALS_SETTINGS,
				'position'   => 0,
			);
		}

		$license_ids = Licenses::ids();

		foreach ( self::products() as $key => $product ) {
			if ( empty( $product['settings'] ) ) {
				continue;
			}

			$found = self::find_item( $product['settings'] );

			if ( ! $found ) {
				continue;
			}

			$pages = array_values( array_diff( array_merge( array( $product['settings'] ), self::tab_group_pages( $product['settings'] ) ), $license_ids ) );

			$tabs[ $key ] = array(
				'key'        => (string) $key,
				'label'      => self::settings_label( $product ),
				'path'       => 'admin.php?page=' . $product['settings'],
				'capability' => isset( $found['item'][1] ) ? (string) $found['item'][1] : 'manage_options',
				'pages'      => array_values( array_unique( $pages ) ),
				'parent'     => $found['parent'],
				'slug'       => (string) $product['settings'],
				'position'   => isset( $product['position'] ) ? (int) $product['position'] : MenuLayout::DEFAULT_SLOT,
			);
		}

		/**
		 * Filters the tabs of the Settings screen.
		 *
		 * A screen host (CP Essentials) can list these as its own tabs; each
		 * tab's `path` is the product's existing settings page, which keeps
		 * its URL, form handling and sub-tabs.
		 *
		 * @param array $tabs key => { label, path, capability, pages: string[], position }.
		 * @since 1.1.23
		 */
		$tabs = apply_filters( 'cp_admin_settings_tabs', $tabs );
		$tabs = is_array( $tabs ) ? $tabs : array();

		uasort(
			$tabs,
			static function ( $a, $b ) {
				$a = isset( $a['position'] ) ? (int) $a['position'] : MenuLayout::DEFAULT_SLOT;
				$b = isset( $b['position'] ) ? (int) $b['position'] : MenuLayout::DEFAULT_SLOT;
				return $a - $b;
			}
		);

		// Cache once the menu has been built; while it is being built
		// (relocate() runs at 998) pages can still be added or hidden.
		if ( did_action( 'admin_menu' ) && ! doing_action( 'admin_menu' ) ) {
			$this->settings_tabs = $tabs;
		}

		return $tabs;
	}

	/**
	 * Label of a product's Settings tab.
	 *
	 * @param array $product Product entry.
	 * @return string
	 */
	protected static function settings_label( array $product ) {
		if ( ! empty( $product['label_type'] ) ) {
			$object = get_post_type_object( $product['label_type'] );

			if ( $object && ! empty( $object->labels->name ) ) {
				return (string) $object->labels->name;
			}
		}

		if ( ! empty( $product['settings_label'] ) ) {
			return (string) $product['settings_label'];
		}

		return isset( $product['name'] ) ? (string) $product['name'] : '';
	}

	/**
	 * Option keys of every CMB2 options page in a tab group (a product's
	 * settings tabs).
	 *
	 * @param string $group Tab group (the product's main settings slug).
	 * @return string[]
	 */
	protected static function tab_group_pages( $group ) {
		if ( ! class_exists( '\CMB2_Boxes' ) ) {
			return array();
		}

		$pages = array();

		foreach ( \CMB2_Boxes::get_all() as $cmb ) {
			if ( $group !== $cmb->prop( 'tab_group' ) ) {
				continue;
			}

			$keys = $cmb->options_page_keys();

			if ( ! empty( $keys[0] ) ) {
				$pages[] = (string) $keys[0];
			}
		}

		return $pages;
	}

	/**
	 * The Settings entry's slug ('' when there is none).
	 *
	 * @return string
	 * @since 1.1.23
	 */
	public function settings_entry() {
		return $this->settings_entry;
	}

	/**
	 * Areas moved this request.
	 *
	 * @return array<string, array>
	 * @since 1.1.23
	 */
	public function areas() {
		return $this->areas;
	}

	/**
	 * Every area the shared menu takes this request, including the ones the
	 * current user cannot open (which stay where they were for that user).
	 * The same for every user, unlike {@see self::areas()}.
	 *
	 * @return array<string, array>
	 * @since 1.1.23
	 */
	public function planned_areas() {
		return $this->planned;
	}

	/**
	 * URL of the first area a user can open, for links to "the new menu".
	 *
	 * @return string
	 * @since 1.1.23
	 */
	public function first_url() {
		global $submenu;

		foreach ( (array) ( isset( $submenu[ self::SLUG ] ) ? $submenu[ self::SLUG ] : array() ) as $item ) {
			if ( isset( $item[1], $item[2] ) && current_user_can( $item[1] ) ) {
				return admin_url( MenuLayout::item_path( self::SLUG, $item[2] ) );
			}
		}

		return admin_url();
	}

	/**
	 * Tab groups: one per area, plus Settings.
	 *
	 * @return array<string, array> key => { label, menu, tabs }.
	 * @since 1.1.23
	 */
	public function groups() {
		global $submenu;

		if ( null !== $this->groups ) {
			return $this->groups;
		}

		$groups  = array();
		$exclude = Licenses::ids();
		$tabs    = $this->settings_tabs();

		foreach ( $tabs as $tab ) {
			$exclude = array_merge( $exclude, isset( $tab['pages'] ) ? (array) $tab['pages'] : array() );
		}

		$owned = array();

		foreach ( self::products() as $product_key => $product ) {
			foreach ( isset( $product['taxonomies'] ) ? (array) $product['taxonomies'] : array() as $taxonomy ) {
				$owned[ (string) $taxonomy ] = (string) $product_key;
			}
		}

		foreach ( $this->areas as $key => $area ) {
			$items = isset( $submenu[ $area['old'] ] ) ? (array) $submenu[ $area['old'] ] : array();
			$area_tabs = MenuLayout::drop_foreign_taxonomies( MenuLayout::area_tabs( $items, $area['old'], $exclude ), $key, $owned );

			/**
			 * Filters the tabs of an area of the shared menu.
			 *
			 * @param array  $tabs Tabs: [ { label, path, capability } ].
			 * @param string $key  Area key (product key, or `post_type:<name>`).
			 * @param array  $area Area: { product, old, menu, label, capability, position }.
			 * @since 1.1.23
			 */
			$area_tabs = apply_filters( 'cp_admin_area_tabs', $area_tabs, $key, $area );

			$groups[ $key ] = array(
				'label' => MenuLayout::strip_count( $area['label'] ),
				'menu'  => $area['menu'],
				'tabs'  => is_array( $area_tabs ) ? array_values( $area_tabs ) : array(),
			);
		}

		if ( $tabs ) {
			$groups['settings'] = array(
				'label' => __( 'Settings', 'churchplugins' ),
				'menu'  => $this->settings_entry,
				'tabs'  => array_values( $tabs ),
			);
		}

		// Asked for several times per screen (menu highlight, tab bar, body
		// class, styles). Kept once the menu is built, as settings_tabs() is.
		if ( self::menu_built() ) {
			$this->groups = $groups;
		}

		return $groups;
	}

	/**
	 * Whether `admin_menu` has finished, so the menu no longer changes.
	 *
	 * @return bool
	 */
	protected static function menu_built() {
		return did_action( 'admin_menu' ) && ! doing_action( 'admin_menu' );
	}

	/**
	 * The current screen as a tab target.
	 *
	 * @return array|null
	 */
	protected static function current_target() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading which screen is open.
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( '' === $page && ! $screen ) {
			return null;
		}

		return MenuLayout::screen_target(
			$screen ? (string) $screen->base : '',
			$screen ? (string) $screen->post_type : '',
			$screen ? (string) $screen->taxonomy : '',
			$page
		);
	}

	/**
	 * The group and tab of the current screen.
	 *
	 * @return array{group: string, tab: int}|null
	 * @since 1.1.23
	 */
	public function current() {
		if ( ! self::enabled() || ( ! $this->areas && ! $this->settings_tabs() ) ) {
			return null;
		}

		$target = self::current_target();

		if ( ! $target ) {
			return null;
		}

		// By target: the screen can be set more than once in a request.
		$key = md5( (string) wp_json_encode( $target ) );

		if ( array_key_exists( $key, $this->current ) ) {
			return $this->current[ $key ];
		}

		$current = MenuLayout::current( $this->groups(), $target );

		if ( self::menu_built() ) {
			$this->current[ $key ] = $current;
		}

		return $current;
	}

	/**
	 * Open the Church Plugins menu on every moved screen.
	 *
	 * @param string $parent_file Parent menu file.
	 * @return string
	 * @since 1.1.23
	 */
	public function parent_file( $parent_file ) {
		$this->is_ours = false;

		if ( $this->current() ) {
			$this->is_ours = true;
			return self::SLUG;
		}

		foreach ( $this->areas as $area ) {
			if ( $parent_file === $area['old'] ) {
				$this->is_ours = true;
				return self::SLUG;
			}
		}

		return $parent_file;
	}

	/**
	 * Highlight the area's entry (or Settings) on every screen it covers.
	 *
	 * @param string|null $submenu_file Submenu file.
	 * @param string      $parent_file  Parent file.
	 * @return string|null
	 * @since 1.1.23
	 */
	public function submenu_file( $submenu_file, $parent_file = '' ) {
		global $plugin_page;

		unset( $parent_file );

		if ( ! $this->is_ours ) {
			return $submenu_file;
		}

		$this->map_parents_for_render();

		$current = $this->current();

		if ( $current ) {
			$groups = $this->groups();
			return '' !== (string) $groups[ $current['group'] ]['menu'] ? $groups[ $current['group'] ]['menu'] : $submenu_file;
		}

		// A screen of an area that is not one of its tabs.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		foreach ( $this->areas as $area ) {
			if ( $screen && ! $plugin_page && $screen->post_type && 'edit.php?post_type=' . $screen->post_type === $area['old'] ) {
				return $area['menu'];
			}
		}

		return $submenu_file;
	}

	/**
	 * While the menu is printed, point the hidden parents at the shared menu.
	 *
	 * Right after the `submenu_file` filter, core's menu-header.php calls
	 * get_admin_page_parent(), which resets `$parent_file` to the first
	 * submenu that lists the screen: for a moved screen, its old (hidden)
	 * parent, so the shared menu would not open. Mapping those parents in
	 * `$_wp_real_parent_file` makes that call answer `church-plugins`.
	 * The page's access check and hook suffix were resolved before this, and
	 * the mapping is undone as soon as the menu is printed (`adminmenu`).
	 *
	 * @return void
	 */
	protected function map_parents_for_render() {
		global $submenu, $_wp_real_parent_file;

		if ( null !== $this->restore ) {
			return;
		}

		$keys = array();

		foreach ( $this->areas as $area ) {
			$keys[] = $area['old'];
		}

		foreach ( $this->settings_tabs() as $tab ) {
			foreach ( isset( $tab['pages'] ) ? (array) $tab['pages'] : array() as $page ) {
				if ( isset( $submenu[ $page ] ) ) {
					$keys[] = $page;
				}
			}
		}

		foreach ( array_keys( (array) $submenu ) as $key ) {
			if ( 0 === strpos( (string) $key, MenuLayout::HOLDER_PREFIX ) ) {
				$keys[] = (string) $key;
			}
		}

		if ( ! is_array( $_wp_real_parent_file ) ) {
			$_wp_real_parent_file = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$this->restore = array();

		foreach ( array_unique( $keys ) as $key ) {
			$this->restore[ $key ]        = array_key_exists( $key, $_wp_real_parent_file ) ? $_wp_real_parent_file[ $key ] : null;
			$_wp_real_parent_file[ $key ] = self::SLUG; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		add_action( 'adminmenu', array( $this, 'restore_parents' ), 0 );
	}

	/**
	 * Undo {@see self::map_parents_for_render()}.
	 *
	 * @return void
	 */
	public function restore_parents() {
		global $_wp_real_parent_file;

		foreach ( (array) $this->restore as $key => $value ) {
			if ( null === $value ) {
				unset( $_wp_real_parent_file[ $key ] );
			} else {
				$_wp_real_parent_file[ $key ] = $value; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}

		$this->restore = null;
	}

	/**
	 * Order the shared menu and drop the self-referential parent item.
	 *
	 * When the first submenu is added to a parent that has no page of its own,
	 * core's add_submenu_page() inserts a first submenu item that links back to
	 * the parent slug (an empty page). Removing it makes the top-level menu link
	 * to the first entry.
	 *
	 * @return void
	 * @since 1.1.16
	 * @since 1.1.23 Also orders the entries.
	 */
	public function remove_parent_submenu() {
		global $submenu;

		if ( empty( $submenu[ self::SLUG ] ) ) {
			if ( self::enabled() ) {
				$this->remove_parent_without_entries();
			}

			return;
		}

		if ( ! self::enabled() ) {
			foreach ( $submenu[ self::SLUG ] as $index => $item ) {
				if ( isset( $item[2] ) && self::SLUG === $item[2] ) {
					unset( $submenu[ self::SLUG ][ $index ] );
				}
			}

			$submenu[ self::SLUG ] = array_values( $submenu[ self::SLUG ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			return;
		}

		$submenu[ self::SLUG ] = MenuLayout::sort_submenu( $submenu[ self::SLUG ], MenuLayout::slots( self::products(), $this->settings_entry ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->remove_parent_without_entries();
	}

	/**
	 * Take the top-level item out when the current user can open none of its
	 * entries. The parent has no page of its own, so on its own it would be a
	 * link to nothing. Core does this only for a user who lacks the parent's
	 * capability; a user can hold `edit_posts` and still have no entry here
	 * (every area uses its own capabilities).
	 *
	 * @return void
	 * @since 1.1.23
	 */
	protected function remove_parent_without_entries() {
		global $menu, $submenu;

		$items = isset( $submenu[ self::SLUG ] ) ? (array) $submenu[ self::SLUG ] : array();

		if ( MenuLayout::has_accessible_item( $items, 'current_user_can' ) ) {
			return;
		}

		unset( $submenu[ self::SLUG ] );

		foreach ( (array) $menu as $index => $item ) {
			if ( isset( $item[2] ) && self::SLUG === $item[2] ) {
				unset( $menu[ $index ] );
			}
		}
	}

	/**
	 * Keep the shared order when CP Essentials reorders the menu (its tidy()
	 * runs at admin_menu priority 1000 and puts its own entries first).
	 *
	 * @param array $order { first: string[], last: string[] }.
	 * @return array
	 * @since 1.1.23
	 */
	public function essentials_order( $order ) {
		global $submenu;

		if ( ! self::enabled() || empty( $submenu[ self::SLUG ] ) ) {
			return $order;
		}

		$slots = MenuLayout::slots( self::products(), $this->settings_entry );

		return MenuLayout::essentials_order( MenuLayout::sort_submenu( $submenu[ self::SLUG ], $slots ), $slots );
	}

	/**
	 * Print the tab bar across the top of an area's (or Settings') screens.
	 *
	 * Not on block-editor screens. The bar and the offset that went with it
	 * sat over the pre-publish panel. List and classic-editor screens are
	 * unchanged.
	 *
	 * @return void
	 * @since 1.1.23
	 */
	public function tabs() {
		if ( self::is_block_editor() ) {
			return;
		}

		$current = $this->current();

		if ( ! $current ) {
			return;
		}

		echo $this->render_tabs( $current['group'], $current['tab'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render_tabs().
	}

	/**
	 * Script that hid the block-editor tab bar when its frame did not start
	 * below the bar. Block-editor screens no longer print the bar, so nothing
	 * prints this script.
	 *
	 * @return string
	 * @since 1.1.23
	 */
	public static function editor_guard() {
		return '(function(){var b=document.querySelector(".cp-admin-tabs--editor");if(!b||!window.requestAnimationFrame){return;}var n=0;function off(){b.hidden=true;document.body.classList.remove("cp-has-editor-tabs");}function check(){var s=document.querySelector(".interface-interface-skeleton");if(!s){if(++n<120){window.requestAnimationFrame(check);}else if(b.offsetHeight){off();}return;}if(!b.offsetHeight){return;}if(s.getBoundingClientRect().top<b.getBoundingClientRect().bottom-1){off();}}window.addEventListener("load",check);window.addEventListener("resize",check);document.addEventListener("DOMContentLoaded",function(){check();if(window.ResizeObserver){var s=document.querySelector(".interface-interface-skeleton");if(s){new ResizeObserver(check).observe(s);}}});})();';
	}

	/**
	 * Whether the current screen is the block editor.
	 *
	 * @return bool
	 */
	protected static function is_block_editor() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor();
	}

	/**
	 * Whether the current user can open a tab.
	 *
	 * @param array $tab Tab.
	 * @return bool
	 */
	public static function can_open( array $tab ) {
		$target = MenuLayout::target( isset( $tab['path'] ) ? $tab['path'] : '' );

		if ( '' === $target['page'] && '' !== $target['taxonomy'] ) {
			$taxonomy = get_taxonomy( $target['taxonomy'] );

			if ( ! $taxonomy || ! $taxonomy->show_ui || ! current_user_can( $taxonomy->cap->manage_terms ) ) {
				return false;
			}
		} elseif ( '' === $target['page'] && '' !== $target['post_type'] ) {
			$object = get_post_type_object( $target['post_type'] );

			if ( ! $object || ! $object->show_ui || ! current_user_can( $object->cap->edit_posts ) ) {
				return false;
			}
		}

		return current_user_can( isset( $tab['capability'] ) && '' !== $tab['capability'] ? $tab['capability'] : 'manage_options' );
	}

	/**
	 * The tab bar for a group, or '' when fewer than two tabs can be opened.
	 *
	 * Same markup as CP Essentials' tab bars: core's nav tabs, the current tab
	 * marked with `aria-current="page"`.
	 *
	 * @param string $key         Group key.
	 * @param int    $current     Index of the current tab.
	 * @param string $extra_class Extra class.
	 * @return string
	 * @since 1.1.23
	 */
	public function render_tabs( $key, $current, $extra_class = '' ) {
		$groups = $this->groups();

		if ( ! isset( $groups[ $key ] ) ) {
			return '';
		}

		$links = '';
		$count = 0;

		foreach ( $groups[ $key ]['tabs'] as $index => $tab ) {
			if ( ! self::can_open( $tab ) ) {
				continue;
			}

			++$count;
			$active = (int) $index === (int) $current;

			$links .= sprintf(
				'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
				esc_url( admin_url( $tab['path'] ) ),
				$active ? ' nav-tab-active' : '',
				$active ? ' aria-current="page"' : '',
				esc_html( $tab['label'] )
			);
		}

		if ( $count < 2 ) {
			return '';
		}

		return sprintf(
			'<nav class="nav-tab-wrapper cp-admin-tabs%1$s" aria-label="%2$s">%3$s</nav>',
			'' !== $extra_class ? ' ' . esc_attr( $extra_class ) : '',
			/* translators: %s: section name, such as "Messages". */
			esc_attr( sprintf( __( '%s sections', 'churchplugins' ), $groups[ $key ]['label'] ) ),
			$links
		);
	}

	/**
	 * Body class. The block editor does not get a tab-bar offset.
	 *
	 * @param string $classes Classes.
	 * @return string
	 * @since 1.1.23
	 */
	public function body_class( $classes ) {
		return $classes;
	}

	/**
	 * Tab bar styles for list and classic-editor screens.
	 *
	 * Block-editor screens get none: the offset covered the pre-publish panel.
	 *
	 * @return void
	 * @since 1.1.23
	 */
	public function styles() {
		if ( self::is_block_editor() || ! $this->current() ) {
			return;
		}

		/*
		 * The editor's bar is fixed above the editor frame, which is moved
		 * down by the bar's height. The bar shows, and the frame moves, only
		 * when that frame (.interface-interface-skeleton) is on the page, so a
		 * renamed or missing frame hides the bar instead of letting it cover
		 * the editor; browsers without :has() never show it. editor_guard()
		 * also hides it if the frame does not start below it. Offsets from
		 * the admin menu use logical properties, so the bar clears the menu
		 * on the right in right-to-left languages.
		 */
		$bar   = 'body.cp-has-editor-tabs:has(.interface-interface-skeleton) .cp-admin-tabs--editor:not([hidden])';
		$frame = 'body.cp-has-editor-tabs:has(.cp-admin-tabs--editor:not([hidden])) .interface-interface-skeleton';

		$css = '.cp-admin-tabs{margin:12px 0 0;margin-inline:2px 20px}.cp-admin-tabs+.wrap{margin-top:8px}'
			. '.cp-admin-tabs--editor{display:none;position:fixed;z-index:30;top:32px;inset-inline:160px 0;margin:0;padding:8px 16px 0;background:#f0f0f1;border-bottom:1px solid #c3c4c7;height:44px;box-sizing:border-box;overflow-x:auto;white-space:nowrap}'
			. $bar . '{display:block}'
			. '.cp-admin-tabs--editor .nav-tab{margin-bottom:-1px;font-size:13px;padding:5px 10px}'
			. $frame . '{top:76px}'
			. '.folded .cp-admin-tabs--editor{inset-inline-start:36px}'
			. '.is-fullscreen-mode .cp-admin-tabs--editor{top:var(--wp-admin--admin-bar--height,0);inset-inline-start:0}.is-fullscreen-mode' . substr( $frame, 4 ) . '{top:calc(var(--wp-admin--admin-bar--height,0px) + 44px)}'
			. '@media (max-width:960px){.auto-fold .cp-admin-tabs--editor{inset-inline-start:36px}}'
			. '@media (max-width:782px){.cp-admin-tabs--editor,.auto-fold .cp-admin-tabs--editor,.folded .cp-admin-tabs--editor,.is-fullscreen-mode .cp-admin-tabs--editor{top:46px;inset-inline-start:0}' . $frame . ',.is-fullscreen-mode' . substr( $frame, 4 ) . '{top:90px}}';

		wp_add_inline_style( 'common', $css );
	}

	/**
	 * The menu icon as a base64-encoded SVG data URI.
	 *
	 * Inlined (rather than a URL) so WordPress's svg-painter can recolor its
	 * fills to match the active admin color scheme.
	 *
	 * @return string Data URI, or a dashicon fallback if the file is unreadable.
	 * @since 1.1.16
	 */
	protected function get_menu_icon() {
		$icon = CHURCHPLUGINS_DIR . 'assets/icons/church-plugins.svg';
		$svg  = is_readable( $icon ) ? file_get_contents( $icon ) : false;

		if ( false === $svg ) {
			return 'dashicons-networking';
		}

		// Pre-color the fills to the current color scheme's resting icon color.
		// svg-painter.js repaints menu SVGs on DOM-ready; without this the icon
		// paints black first and visibly flashes before the script runs.
		$svg = str_replace( 'fill="black"', 'fill="' . $this->get_base_icon_color() . '"', $svg );

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * The resting ("base") icon color for the current admin color scheme.
	 *
	 * Falls back to the default scheme's base gray, which is also correct when
	 * the color schemes have not been registered yet (they register after
	 * `admin_menu`, so non-default schemes are corrected by svg-painter.js).
	 *
	 * @return string A hex color.
	 * @since 1.1.16
	 */
	protected function get_base_icon_color() {
		global $_wp_admin_css_colors;

		$scheme = get_user_option( 'admin_color' );
		$scheme = $scheme ? $scheme : 'fresh';

		if ( isset( $_wp_admin_css_colors[ $scheme ]->icon_colors['base'] ) ) {
			return $_wp_admin_css_colors[ $scheme ]->icon_colors['base'];
		}

		return '#a7aaad';
	}
}
