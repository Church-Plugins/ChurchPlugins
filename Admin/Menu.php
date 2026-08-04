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
 * Church Plugins products can opt into a single shared top-level menu instead
 * of each registering their own. A product declares support with
 * {@see Menu::add_support()} (typically during its setup, before `admin_menu`
 * fires); the parent menu is only registered when at least one product has
 * declared support. Products then attach their own screens as submenus using
 * {@see Menu::get_slug()} as the parent slug.
 *
 * @since 1.1.16
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
	 * Whether at least one product has declared support for the shared menu.
	 *
	 * @var bool
	 */
	protected $has_support = false;

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
	 * Constructor. Registers the parent menu early (priority 9) so products
	 * adding submenus at the default priority (10) can attach to it.
	 */
	protected function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ], 9 );
		// After all products have attached their submenus (default priority 10),
		// drop the self-referential parent item so the menu points at the first
		// product instead of an empty landing page.
		add_action( 'admin_menu', [ $this, 'remove_parent_submenu' ], 999 );
	}

	/**
	 * Declare support for the shared Church Plugins menu.
	 *
	 * Call before `admin_menu` fires (e.g. during plugin setup on
	 * `cp_core_loaded`). Instantiating the singleton registers the parent menu.
	 *
	 * @return string The parent menu slug, for use as a submenu parent.
	 * @since 1.1.16
	 */
	public static function add_support() {
		$instance = self::get_instance();
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
	 * Register the top-level Church Plugins menu.
	 *
	 * Only registers when a product has declared support. The menu has no page
	 * callback of its own; the landing page is the first submenu a product
	 * attaches.
	 *
	 * @return void
	 * @since 1.1.16
	 */
	public function register_menu() {
		if ( ! $this->has_support ) {
			return;
		}

		/**
		 * Filters the capability required to see the Church Plugins menu.
		 *
		 * @param string $capability The capability. Default 'manage_options'.
		 * @since 1.1.16
		 */
		$capability = apply_filters( 'cp_admin_menu_capability', 'manage_options' );

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
			 * @param int|float $position The menu position. Default 80.
			 * @since 1.1.16
			 */
			apply_filters( 'cp_admin_menu_position', 80 )
		);
	}

	/**
	 * Remove the self-referential parent submenu item.
	 *
	 * When the first submenu is added to a parent that has no page of its own,
	 * core's add_submenu_page() inserts a first submenu item that links back to
	 * the parent slug (an empty page). Removing it makes the top-level menu link
	 * to the first product that registered a submenu.
	 *
	 * @return void
	 * @since 1.1.16
	 */
	public function remove_parent_submenu() {
		if ( ! $this->has_support ) {
			return;
		}

		global $submenu;

		if ( empty( $submenu[ self::SLUG ] ) ) {
			return;
		}

		foreach ( $submenu[ self::SLUG ] as $index => $item ) {
			if ( isset( $item[2] ) && self::SLUG === $item[2] ) {
				unset( $submenu[ self::SLUG ][ $index ] );
			}
		}

		// Reindex so the first remaining product becomes the menu's landing page.
		$submenu[ self::SLUG ] = array_values( $submenu[ self::SLUG ] );
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
