<?php
/**
 * Pure helpers behind the shared "Church Plugins" admin menu.
 *
 * Nothing here calls WordPress: every method takes the arrays it works on and
 * returns a result, so the layout rules can be unit tested without a site.
 *
 * @package ChurchPlugins
 */

namespace ChurchPlugins\Admin;

/**
 * Layout rules for the shared menu: which top-level menus move, where entries
 * go, which screens become tabs, and how a screen is matched to a tab.
 *
 * @since 1.1.23
 */
class MenuLayout {

	/**
	 * Slot of an entry whose slug the layout does not know.
	 */
	const DEFAULT_SLOT = 95;

	/**
	 * Prefix of the hidden submenu holders. See {@see self::hide_item()}.
	 */
	const HOLDER_PREFIX = 'cp-admin-hidden:';

	/**
	 * Slots of the entries the layout knows, in the approved order:
	 * Home · Messages · Events · Groups · Staff · Locations · Resources ·
	 * Notification Bars · Church Info · Sync · Settings · Licenses.
	 *
	 * Area entries (post type lists) get their product's slot from
	 * {@see self::slots()}; these are the fixed ones.
	 *
	 * @return array<string, int>
	 */
	public static function fixed_slots() {
		return array(
			'cp-essentials-home'           => 0,
			'edit.php?post_type=cp_event'  => 20,
			'cp-essentials'                => 80,
			'cps_settings'                 => 90,
			'cp-essentials-settings'       => 100,
			'cp-essentials-licenses'       => 110,
			Menu::LICENSES_SLUG            => 110,
		);
	}

	/**
	 * Slots for every known slug: the fixed ones plus each product's post
	 * type lists and menu slug.
	 *
	 * @param array  $products       Product table, see {@see Menu::products()}.
	 * @param string $settings_entry Slug of the Settings entry, if any.
	 * @return array<string, int>
	 */
	public static function slots( array $products, $settings_entry = '' ) {
		$slots = self::fixed_slots();

		foreach ( $products as $product ) {
			$position = isset( $product['position'] ) ? (int) $product['position'] : self::DEFAULT_SLOT;

			foreach ( self::product_post_types( $product ) as $post_type ) {
				$slots[ 'edit.php?post_type=' . $post_type ] = $position;
			}

			if ( ! empty( $product['menu'] ) ) {
				$slots[ (string) $product['menu'] ] = $position;
			}
		}

		if ( '' !== (string) $settings_entry ) {
			$slots[ (string) $settings_entry ] = 100;
		}

		return $slots;
	}

	/**
	 * Post types a product's area may lead with, in order of preference.
	 *
	 * @param array $product Product entry.
	 * @return string[]
	 */
	public static function product_post_types( array $product ) {
		return isset( $product['post_types'] ) ? array_values( array_filter( array_map( 'strval', (array) $product['post_types'] ) ) ) : array();
	}

	/**
	 * Slot of a submenu slug.
	 *
	 * @param string $slug  Submenu slug.
	 * @param array  $slots Known slots.
	 * @return int
	 */
	public static function slot( $slug, array $slots ) {
		$slug = str_replace( '&amp;', '&', (string) $slug );

		return isset( $slots[ $slug ] ) ? (int) $slots[ $slug ] : self::DEFAULT_SLOT;
	}

	/**
	 * Order submenu items by slot. Items in the same slot keep the order they
	 * were added in. Drops the parent's self-link.
	 *
	 * @param array  $items  Submenu items (index 2 is the slug).
	 * @param array  $slots  Known slots.
	 * @param string $parent Parent slug, whose self-link is dropped.
	 * @return array
	 */
	public static function sort_submenu( array $items, array $slots, $parent = Menu::SLUG ) {
		$keyed = array();
		$index = 0;

		foreach ( $items as $item ) {
			$slug = isset( $item[2] ) ? (string) $item[2] : '';

			if ( $parent === $slug ) {
				continue;
			}

			$keyed[] = array( self::slot( $slug, $slots ), $index++, $item );
		}

		usort(
			$keyed,
			static function ( $a, $b ) {
				return $a[0] === $b[0] ? $a[1] - $b[1] : $a[0] - $b[0];
			}
		);

		return array_map(
			static function ( $row ) {
				return $row[2];
			},
			$keyed
		);
	}

	/**
	 * The order to hand CP Essentials' `cp_essentials_menu_order` filter, so its
	 * own reordering (admin_menu priority 1000) keeps the shared order.
	 *
	 * @param array $items Submenu items, already sorted.
	 * @param array $slots Known slots.
	 * @return array{first: string[], last: string[]}
	 */
	public static function essentials_order( array $items, array $slots ) {
		$first = array();
		$last  = array();

		foreach ( $items as $item ) {
			$slug = isset( $item[2] ) ? (string) $item[2] : '';

			if ( '' === $slug ) {
				continue;
			}

			if ( self::slot( $slug, $slots ) >= 110 ) {
				$last[] = $slug;
			} else {
				$first[] = $slug;
			}
		}

		return array(
			'first' => $first,
			'last'  => $last,
		);
	}

	/**
	 * Decide which top-level menus become areas of the shared menu.
	 *
	 * A product's area is the first of its post types that has a top-level
	 * menu (CP Sermons leads with Series or Messages, per its "default menu
	 * item" setting). Any other top-level post type list registered through
	 * the framework's PostType class becomes an area in the default slot.
	 *
	 * @param array    $menu          Core's $menu.
	 * @param array    $products      Product table.
	 * @param string[] $cp_post_types Post types registered through the framework.
	 * @return array<string, array> key => { product, old, menu, label, capability, position }.
	 */
	public static function plan_areas( array $menu, array $products, array $cp_post_types = array() ) {
		$top = array();

		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) && 0 === strpos( (string) $item[2], 'edit.php?post_type=' ) ) {
				$top[ (string) $item[2] ] = $item;
			}
		}

		$areas = array();
		$taken = array();

		foreach ( $products as $key => $product ) {
			foreach ( self::product_post_types( $product ) as $post_type ) {
				$slug = 'edit.php?post_type=' . $post_type;

				if ( ! isset( $top[ $slug ] ) || isset( $taken[ $slug ] ) ) {
					continue;
				}

				$areas[ (string) $key ] = self::area( (string) $key, $top[ $slug ], isset( $product['position'] ) ? (int) $product['position'] : self::DEFAULT_SLOT );
				$taken[ $slug ]         = true;
				break;
			}
		}

		foreach ( $cp_post_types as $post_type ) {
			$slug = 'edit.php?post_type=' . $post_type;

			if ( ! isset( $top[ $slug ] ) || isset( $taken[ $slug ] ) ) {
				continue;
			}

			$areas[ 'post_type:' . $post_type ] = self::area( '', $top[ $slug ], self::DEFAULT_SLOT );
			$taken[ $slug ]                     = true;
		}

		return $areas;
	}

	/**
	 * One area from a top-level menu item.
	 *
	 * @param string $product  Product key, '' for a post type the table does not list.
	 * @param array  $item     Top-level menu item.
	 * @param int    $position Slot.
	 * @return array
	 */
	protected static function area( $product, array $item, $position ) {
		return array(
			'product'    => $product,
			'old'        => (string) $item[2],
			'menu'       => (string) $item[2],
			'label'      => (string) $item[0],
			'capability' => isset( $item[1] ) ? (string) $item[1] : 'edit_posts',
			'position'   => (int) $position,
		);
	}

	/**
	 * The areas a user can open.
	 *
	 * apply_areas() appends an entry per area straight to $submenu, where
	 * nothing checks capabilities the way core's add_submenu_page() does, and
	 * core links the top-level item to the first entry. So only areas the
	 * user can open are moved.
	 *
	 * @param array    $areas From {@see self::plan_areas()}.
	 * @param callable $can   Capability check: fn( string $capability ): bool.
	 * @return array
	 */
	public static function accessible_areas( array $areas, $can ) {
		$open = array();

		foreach ( $areas as $key => $area ) {
			if ( ! empty( $area['capability'] ) && call_user_func( $can, $area['capability'] ) ) {
				$open[ $key ] = $area;
			}
		}

		return $open;
	}

	/**
	 * Whether a submenu has an item the user can open (the parent's link to
	 * itself does not count).
	 *
	 * @param array    $items  Submenu items.
	 * @param callable $can    Capability check: fn( string $capability ): bool.
	 * @param string   $parent Shared parent slug.
	 * @return bool
	 */
	public static function has_accessible_item( array $items, $can, $parent = Menu::SLUG ) {
		foreach ( $items as $item ) {
			if ( ! isset( $item[1], $item[2] ) || (string) $parent === (string) $item[2] ) {
				continue;
			}

			if ( call_user_func( $can, $item[1] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Make the ids of a form's fields unique on a screen that prints several
	 * forms with the same field ids (one license box per product: each has
	 * `id="license"` and `id="beta"`). Prefixes the `id` of each field and
	 * the `for` of its labels; `name` attributes, and so what the form posts,
	 * are untouched.
	 *
	 * @param string   $html      Form markup.
	 * @param string[] $field_ids Field ids to prefix.
	 * @param string   $prefix    Prefix, unique per form.
	 * @return string
	 */
	public static function prefix_field_ids( $html, array $field_ids, $prefix ) {
		$html = (string) $html;

		foreach ( $field_ids as $field_id ) {
			$field_id = (string) $field_id;

			if ( '' === $field_id ) {
				continue;
			}

			$replaced = preg_replace_callback(
				'/(\s(?:id|for)=)(["\'])' . preg_quote( $field_id, '/' ) . '\2/',
				static function ( $match ) use ( $prefix, $field_id ) {
					return $match[1] . $match[2] . $prefix . '-' . $field_id . $match[2];
				},
				$html
			);

			$html = null === $replaced ? $html : $replaced;
		}

		return $html;
	}

	/**
	 * Move areas under the shared parent.
	 *
	 * Pass only areas the user can open ({@see self::accessible_areas()}):
	 * the entries are added without a capability check.
	 *
	 * The old top-level item is removed from $menu. Its submenu array stays
	 * where it is: core finds a page's parent, hook suffix and capability
	 * through $submenu, so every screen keeps its URL, its hooks and its
	 * access rules. With no top-level item the old submenu is never printed.
	 *
	 * @param array  $menu    Core's $menu.
	 * @param array  $submenu Core's $submenu.
	 * @param array  $areas   From {@see self::plan_areas()}.
	 * @param string $parent  Shared parent slug.
	 * @return array{0: array, 1: array} The new $menu and $submenu.
	 */
	public static function apply_areas( array $menu, array $submenu, array $areas, $parent = Menu::SLUG ) {
		$old = array();

		foreach ( $areas as $area ) {
			$old[ $area['old'] ] = $area;
		}

		foreach ( $menu as $index => $item ) {
			if ( isset( $item[2] ) && isset( $old[ (string) $item[2] ] ) ) {
				unset( $menu[ $index ] );
			}
		}

		if ( ! isset( $submenu[ $parent ] ) ) {
			$submenu[ $parent ] = array();
		}

		foreach ( $areas as $area ) {
			$label                = self::strip_count( $area['label'] );
			$submenu[ $parent ][] = array( $area['label'], $area['capability'], $area['menu'], $label );
		}

		return array( $menu, $submenu );
	}

	/**
	 * Hide one submenu item from the menu while keeping its page reachable.
	 *
	 * The item moves to a holder array that core never prints. The holder is
	 * mapped back to the real parent in $_wp_real_parent_file, so core still
	 * resolves the page's parent (and so its hook suffix, e.g.
	 * `settings_page_cp_live_main_options`) the same as before.
	 *
	 * @param array  $submenu     Core's $submenu.
	 * @param array  $real_parent Core's $_wp_real_parent_file.
	 * @param string $parent      Parent the item is listed under.
	 * @param string $slug        Item slug.
	 * @return array{0: array, 1: array, 2: bool} $submenu, $_wp_real_parent_file, whether it moved.
	 */
	public static function hide_item( array $submenu, array $real_parent, $parent, $slug ) {
		if ( empty( $submenu[ $parent ] ) ) {
			return array( $submenu, $real_parent, false );
		}

		$moved = false;
		$real  = isset( $real_parent[ $parent ] ) ? $real_parent[ $parent ] : $parent;
		$hold  = self::HOLDER_PREFIX . $real;

		foreach ( $submenu[ $parent ] as $index => $item ) {
			if ( isset( $item[2] ) && (string) $slug === (string) $item[2] ) {
				$submenu[ $hold ][] = $item;
				unset( $submenu[ $parent ][ $index ] );
				$moved = true;
			}
		}

		if ( $moved ) {
			$real_parent[ $hold ] = $real;
		}

		return array( $submenu, $real_parent, $moved );
	}

	/**
	 * Where a submenu item links, the way core builds the link: a slug with
	 * `.php` is used as is; a plugin page under a `.php` parent becomes
	 * `parent&page=slug`; any other plugin page becomes `admin.php?page=slug`.
	 *
	 * @param string $parent Parent slug.
	 * @param string $slug   Item slug.
	 * @return string Admin path.
	 */
	public static function item_path( $parent, $slug ) {
		$slug = str_replace( '&amp;', '&', (string) $slug );

		if ( false !== strpos( $slug, '.php' ) ) {
			return $slug;
		}

		$file = strtok( (string) $parent, '?' );

		if ( $file && '.php' === substr( $file, -4 ) && 'admin.php' !== $file ) {
			return $parent . ( false === strpos( $parent, '?' ) ? '?' : '&' ) . 'page=' . rawurlencode( $slug );
		}

		return 'admin.php?page=' . rawurlencode( $slug );
	}

	/**
	 * Tabs of an area: every item of its old submenu except "Add New" links
	 * and the pages that live elsewhere now (settings, licenses).
	 *
	 * @param array    $items   Items of the old submenu (core already dropped
	 *                          the ones the user cannot open).
	 * @param string   $parent  Old top-level slug.
	 * @param string[] $exclude Page slugs that are not tabs.
	 * @return array[] [ { label, path, capability } ]
	 */
	public static function area_tabs( array $items, $parent, array $exclude = array() ) {
		$tabs = array();
		$seen = array();

		foreach ( $items as $item ) {
			$slug = isset( $item[2] ) ? str_replace( '&amp;', '&', (string) $item[2] ) : '';

			if ( '' === $slug || 0 === strpos( $slug, 'post-new.php' ) || in_array( $slug, $exclude, true ) ) {
				continue;
			}

			$path = self::item_path( $parent, $slug );

			if ( isset( $seen[ $path ] ) ) {
				continue;
			}

			$seen[ $path ] = true;
			$tabs[]        = array(
				'label'      => self::strip_count( isset( $item[0] ) ? (string) $item[0] : '' ),
				'path'       => $path,
				'capability' => isset( $item[1] ) ? (string) $item[1] : 'manage_options',
			);
		}

		return $tabs;
	}

	/**
	 * Drop taxonomy tabs that belong to another product's area (CP Resources'
	 * types and topics, when resources are attached to sermons or groups).
	 *
	 * @param array  $tabs  Tabs.
	 * @param string $area  Area key (product key).
	 * @param array  $owned taxonomy => owning product key.
	 * @return array
	 */
	public static function drop_foreign_taxonomies( array $tabs, $area, array $owned ) {
		return array_values(
			array_filter(
				$tabs,
				static function ( $tab ) use ( $area, $owned ) {
					$target = self::target( isset( $tab['path'] ) ? $tab['path'] : '' );

					return '' === $target['taxonomy'] || '' !== $target['page'] || ! isset( $owned[ $target['taxonomy'] ] ) || $owned[ $target['taxonomy'] ] === (string) $area;
				}
			)
		);
	}

	/**
	 * Remove markup (such as a count bubble) from a menu label.
	 *
	 * @param string $label Menu label.
	 * @return string
	 */
	public static function strip_count( $label ) {
		$label = preg_replace( '#<span[^>]*class=["\'][^"\']*(count|awaiting|update-plugins|pending)[^"\']*["\'][^>]*>.*?</span>#is', '', (string) $label );

		return trim( strip_tags( (string) $label ) );
	}

	/**
	 * What an admin path points at.
	 *
	 * @param string $path Admin path such as `edit-tags.php?taxonomy=x&post_type=y`.
	 * @return array{post_type: string, taxonomy: string, page: string}
	 */
	public static function target( $path ) {
		$query = array();
		$parts = parse_url( str_replace( '&amp;', '&', (string) $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure helper, no WordPress.

		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		return array(
			'post_type' => isset( $query['post_type'] ) && is_string( $query['post_type'] ) ? $query['post_type'] : '',
			'taxonomy'  => isset( $query['taxonomy'] ) && is_string( $query['taxonomy'] ) ? $query['taxonomy'] : '',
			'page'      => isset( $query['page'] ) && is_string( $query['page'] ) ? $query['page'] : '',
		);
	}

	/**
	 * The current screen as a tab target, or null when it is not a screen a
	 * tab can point at.
	 *
	 * @param string $base      Screen base (`edit`, `post`, `edit-tags`, `term`, …).
	 * @param string $post_type Screen post type.
	 * @param string $taxonomy  Screen taxonomy.
	 * @param string $page      The `page` request argument.
	 * @return array{post_type: string, taxonomy: string, page: string}|null
	 */
	public static function screen_target( $base, $post_type, $taxonomy, $page = '' ) {
		if ( '' !== (string) $page ) {
			return array(
				'post_type' => '',
				'taxonomy'  => '',
				'page'      => (string) $page,
			);
		}

		if ( in_array( $base, array( 'edit-tags', 'term' ), true ) && '' !== (string) $taxonomy ) {
			return array(
				'post_type' => '',
				'taxonomy'  => (string) $taxonomy,
				'page'      => '',
			);
		}

		if ( in_array( $base, array( 'edit', 'post' ), true ) && '' !== (string) $post_type ) {
			return array(
				'post_type' => (string) $post_type,
				'taxonomy'  => '',
				'page'      => '',
			);
		}

		return null;
	}

	/**
	 * Whether a tab is the screen described by a target.
	 *
	 * @param array $tab    Tab.
	 * @param array $target Screen target.
	 * @return bool
	 */
	public static function matches( array $tab, array $target ) {
		$tab_target = self::target( isset( $tab['path'] ) ? $tab['path'] : '' );

		if ( '' !== $target['page'] ) {
			return $tab_target['page'] === $target['page'];
		}

		if ( '' !== $target['taxonomy'] ) {
			return $tab_target['taxonomy'] === $target['taxonomy'];
		}

		return '' === $tab_target['taxonomy'] && '' === $tab_target['page'] && '' !== $tab_target['post_type'] && $tab_target['post_type'] === $target['post_type'];
	}

	/**
	 * The group and tab a target belongs to.
	 *
	 * Settings pages are matched by their whole page list (a product's
	 * settings tab covers every sub-tab of its settings).
	 *
	 * @param array $groups key => { tabs: [ { path, pages? } ] }.
	 * @param array $target Screen target.
	 * @return array{group: string, tab: int}|null
	 */
	public static function current( array $groups, array $target ) {
		foreach ( $groups as $key => $group ) {
			foreach ( $group['tabs'] as $index => $tab ) {
				$pages = isset( $tab['pages'] ) ? (array) $tab['pages'] : array();

				if ( self::matches( $tab, $target ) || ( '' !== $target['page'] && in_array( $target['page'], $pages, true ) ) ) {
					return array(
						'group' => (string) $key,
						'tab'   => (int) $index,
					);
				}
			}
		}

		return null;
	}

	/**
	 * Where an old license screen sends the browser, or '' to leave it.
	 *
	 * @param string   $page         The `page` request argument.
	 * @param string[] $license_ids  Registered license ids (each was a settings page slug).
	 * @param array    $query        Request query arguments.
	 * @param string   $licenses_url URL of the Licenses screen.
	 * @return string
	 */
	public static function license_redirect( $page, array $license_ids, array $query, $licenses_url ) {
		if ( '' === (string) $page || ! in_array( (string) $page, $license_ids, true ) ) {
			return '';
		}

		$keep = array();

		foreach ( array( 'sl_activation', 'message', 'settings-updated' ) as $arg ) {
			if ( isset( $query[ $arg ] ) && is_string( $query[ $arg ] ) ) {
				$keep[ $arg ] = $query[ $arg ];
			}
		}

		$url = (string) $licenses_url;

		if ( $keep ) {
			$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $keep, '', '&', PHP_QUERY_RFC3986 );
		}

		// With an activation error the page opens at the top, where the
		// License class prints the error; otherwise at the product's row.
		return isset( $keep['sl_activation'] ) ? $url : $url . '#' . rawurlencode( (string) $page );
	}

	/**
	 * Whether a site had Church Plugins in use before the shared menu arrived.
	 *
	 * Decided once, the first time the shared menu runs on a site. Any saved
	 * product settings, any license key, or any content in a product's post
	 * types means people have been using the old menus. A fresh install has
	 * none of these when it first loads the admin.
	 *
	 *
	 * Not when CP Essentials is active at that moment. Essentials creates
	 * content in the same post types (messages, groups, staff) under this same
	 * menu, so a church that started on Essentials and then adds a paid
	 * product has content but never saw the old menus.
	 *
	 * @param int  $content_count     Posts (any status but auto-draft) in product post types.
	 * @param bool $has_license       Whether any product has a license key saved.
	 * @param bool $saved_settings    Whether any product's main settings were saved.
	 * @param bool $essentials_active Whether CP Essentials is active.
	 * @return bool
	 */
	public static function is_existing_install( $content_count, $has_license, $saved_settings, $essentials_active = false ) {
		if ( $essentials_active ) {
			return false;
		}

		return (int) $content_count > 0 || (bool) $has_license || (bool) $saved_settings;
	}

	/**
	 * Whether the one-time "menus moved" notice is shown to a user.
	 *
	 * @param array $state       Stored layout state: { existing: bool, since: int }.
	 * @param int   $registered  When the user registered (timestamp).
	 * @param bool  $saw_content Whether the user could open the moved content menus.
	 * @param bool  $saw_settings Whether the user could open the moved settings.
	 * @param bool  $dismissed   Whether the user dismissed it.
	 * @return bool
	 */
	public static function notice_applies( array $state, $registered, $saw_content, $saw_settings, $dismissed ) {
		if ( $dismissed || empty( $state['existing'] ) ) {
			return false;
		}

		if ( ! $saw_content && ! $saw_settings ) {
			return false;
		}

		// People who joined after the move never saw the old menus.
		$since = isset( $state['since'] ) ? (int) $state['since'] : 0;

		return ! ( $since && (int) $registered > $since );
	}
}
