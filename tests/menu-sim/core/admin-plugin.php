<?php
/**
 * WordPress admin menu functions, copied unchanged from WordPress 7.1.4
 * (wp-admin/includes/plugin.php). Only the functions the menu simulation
 * needs; see ../run.php.
 *
 * WordPress is licensed under the GPL v2 or later, as this repository is.
 *
 * @package WordPress
 */

// phpcs:ignoreFile -- Core code, kept as it is in core.

/**
 * Adds a top-level menu page.
 *
 * This function takes a capability which will be used to determine whether
 * or not a page is included in the menu.
 *
 * The function which is hooked in to handle the output of the page must check
 * that the user has the required capability as well.
 *
 * @since 1.5.0
 *
 * @global array $menu
 * @global array $admin_page_hooks
 * @global array $_registered_pages
 * @global array $_parent_pages
 *
 * @param string    $page_title The text to be displayed in the title tags of the page when the menu is selected.
 * @param string    $menu_title The text to be used for the menu.
 * @param string    $capability The capability required for this menu to be displayed to the user.
 * @param string    $menu_slug  The slug name to refer to this menu by. Should be unique for this menu page and only
 *                              include lowercase alphanumeric, dashes, and underscores characters to be compatible
 *                              with sanitize_key().
 * @param callable  $callback   Optional. The function to be called to output the content for this page.
 * @param string    $icon_url   Optional. The URL to the icon to be used for this menu.
 *                              * Pass a base64-encoded SVG using a data URI, which will be colored to match
 *                                the color scheme. This should begin with 'data:image/svg+xml;base64,'.
 *                              * Pass the name of a Dashicons helper class to use a font icon,
 *                                e.g. 'dashicons-chart-pie'.
 *                              * Pass 'none' to leave div.wp-menu-image empty so an icon can be added via CSS.
 * @param int|float $position   Optional. The position in the menu order this item should appear.
 * @return string The resulting page's hook_suffix.
 */
function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {
	global $menu, $admin_page_hooks, $_registered_pages, $_parent_pages;

	$menu_slug = plugin_basename( $menu_slug );

	$admin_page_hooks[ $menu_slug ] = sanitize_title( $menu_title );

	$hookname = get_plugin_page_hookname( $menu_slug, '' );

	if ( ! empty( $callback ) && ! empty( $hookname ) && current_user_can( $capability ) ) {
		add_action( $hookname, $callback );
	}

	if ( empty( $icon_url ) ) {
		$icon_url   = 'dashicons-admin-generic';
		$icon_class = 'menu-icon-generic ';
	} else {
		$icon_url   = set_url_scheme( $icon_url );
		$icon_class = '';
	}

	$new_menu = array( $menu_title, $capability, $menu_slug, $page_title, 'menu-top ' . $icon_class . $hookname, $hookname, $icon_url );

	if ( null !== $position && ! is_numeric( $position ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				/* translators: %s: add_menu_page() */
				__( 'The seventh parameter passed to %s should be numeric representing menu position.' ),
				'<code>add_menu_page()</code>'
			),
			'6.0.0'
		);
		$position = null;
	}

	if ( null === $position || ! is_numeric( $position ) ) {
		$menu[] = $new_menu;
	} elseif ( isset( $menu[ (string) $position ] ) ) {
		$collision_avoider = (int) base_convert( substr( md5( $menu_slug . $menu_title ), -4 ), 16, 10 ) * 0.00001;
		$position          = (string) ( $position + $collision_avoider );
		$menu[ $position ] = $new_menu;
	} else {
		/*
		 * Cast menu position to a string.
		 *
		 * This allows for floats to be passed as the position. PHP will normally cast a float to an
		 * integer value, this ensures the float retains its mantissa (positive fractional part).
		 *
		 * A string containing an integer value, eg "10", is treated as a numeric index.
		 */
		$position          = (string) $position;
		$menu[ $position ] = $new_menu;
	}

	$_registered_pages[ $hookname ] = true;

	// No parent as top level.
	$_parent_pages[ $menu_slug ] = false;

	return $hookname;
}

/**
 * Adds a submenu page.
 *
 * This function takes a capability which will be used to determine whether
 * or not a page is included in the menu.
 *
 * The function which is hooked in to handle the output of the page must check
 * that the user has the required capability as well.
 *
 * @since 1.5.0
 * @since 5.3.0 Added the `$position` parameter.
 *
 * @global array $submenu
 * @global array $menu
 * @global array $_wp_real_parent_file
 * @global bool  $_wp_submenu_nopriv
 * @global array $_registered_pages
 * @global array $_parent_pages
 *
 * @param string    $parent_slug The slug name for the parent menu (or the file name of a standard
 *                               WordPress admin page).
 * @param string    $page_title  The text to be displayed in the title tags of the page when the menu
 *                               is selected.
 * @param string    $menu_title  The text to be used for the menu.
 * @param string    $capability  The capability required for this menu to be displayed to the user.
 * @param string    $menu_slug   The slug name to refer to this menu by. Should be unique for this menu
 *                               and only include lowercase alphanumeric, dashes, and underscores characters
 *                               to be compatible with sanitize_key().
 * @param callable  $callback    Optional. The function to be called to output the content for this page.
 * @param int|float $position    Optional. The position in the menu order this item should appear.
 * @return string|false The resulting page's hook_suffix, or false if the user does not have the capability required.
 */
function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
	global $submenu, $menu, $_wp_real_parent_file, $_wp_submenu_nopriv,
		$_registered_pages, $_parent_pages;

	$menu_slug   = plugin_basename( $menu_slug );
	$parent_slug = plugin_basename( $parent_slug );

	if ( isset( $_wp_real_parent_file[ $parent_slug ] ) ) {
		$parent_slug = $_wp_real_parent_file[ $parent_slug ];
	}

	if ( ! current_user_can( $capability ) ) {
		$_wp_submenu_nopriv[ $parent_slug ][ $menu_slug ] = true;
		return false;
	}

	/*
	 * If the parent doesn't already have a submenu, add a link to the parent
	 * as the first item in the submenu. If the submenu file is the same as the
	 * parent file someone is trying to link back to the parent manually. In
	 * this case, don't automatically add a link back to avoid duplication.
	 */
	if ( ! isset( $submenu[ $parent_slug ] ) && $menu_slug !== $parent_slug ) {
		foreach ( (array) $menu as $parent_menu ) {
			if ( $parent_menu[2] === $parent_slug && current_user_can( $parent_menu[1] ) ) {
				$submenu[ $parent_slug ][] = array_slice( $parent_menu, 0, 4 );
			}
		}
	}

	$new_sub_menu = array( $menu_title, $capability, $menu_slug, $page_title );

	if ( null !== $position && ! is_numeric( $position ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				/* translators: %s: add_submenu_page() */
				__( 'The seventh parameter passed to %s should be numeric representing menu position.' ),
				'<code>add_submenu_page()</code>'
			),
			'5.3.0'
		);
		$position = null;
	}

	if (
		null === $position ||
		( ! isset( $submenu[ $parent_slug ] ) || $position >= count( $submenu[ $parent_slug ] ) )
	) {
		$submenu[ $parent_slug ][] = $new_sub_menu;
	} else {
		// Test for a negative position.
		$position = max( $position, 0 );
		if ( 0 === $position ) {
			// For negative or `0` positions, prepend the submenu.
			array_unshift( $submenu[ $parent_slug ], $new_sub_menu );
		} else {
			$position = absint( $position );
			// Grab all of the items before the insertion point.
			$before_items = array_slice( $submenu[ $parent_slug ], 0, $position, true );
			// Grab all of the items after the insertion point.
			$after_items = array_slice( $submenu[ $parent_slug ], $position, null, true );
			// Add the new item.
			$before_items[] = $new_sub_menu;
			// Merge the items.
			$submenu[ $parent_slug ] = array_merge( $before_items, $after_items );
		}
	}

	// Sort the parent array.
	ksort( $submenu[ $parent_slug ] );

	$hookname = get_plugin_page_hookname( $menu_slug, $parent_slug );
	if ( ! empty( $callback ) && ! empty( $hookname ) ) {
		add_action( $hookname, $callback );
	}

	$_registered_pages[ $hookname ] = true;

	/*
	 * Backward-compatibility for plugins using add_management_page().
	 * See wp-admin/admin.php for redirect from edit.php to tools.php.
	 */
	if ( 'tools.php' === $parent_slug ) {
		$_registered_pages[ get_plugin_page_hookname( $menu_slug, 'edit.php' ) ] = true;
	}

	// No parent as top level.
	$_parent_pages[ $menu_slug ] = $parent_slug;

	return $hookname;
}

/**
 * Removes a top-level admin menu.
 *
 * Example usage:
 *
 *  - `remove_menu_page( 'tools.php' )`
 *  - `remove_menu_page( 'plugin_menu_slug' )`
 *
 * @since 3.1.0
 *
 * @global array $menu
 *
 * @param string $menu_slug The slug of the menu.
 * @return array|false The removed menu on success, false if not found.
 */
function remove_menu_page( $menu_slug ) {
	global $menu;

	foreach ( $menu as $i => $item ) {
		if ( $menu_slug === $item[2] ) {
			unset( $menu[ $i ] );
			return $item;
		}
	}

	return false;
}

/**
 * Removes an admin submenu.
 *
 * Example usage:
 *
 *  - `remove_submenu_page( 'themes.php', 'nav-menus.php' )`
 *  - `remove_submenu_page( 'tools.php', 'plugin_submenu_slug' )`
 *  - `remove_submenu_page( 'plugin_menu_slug', 'plugin_submenu_slug' )`
 *
 * @since 3.1.0
 *
 * @global array $submenu
 *
 * @param string $menu_slug    The slug for the parent menu.
 * @param string $submenu_slug The slug of the submenu.
 * @return array|false The removed submenu on success, false if not found.
 */
function remove_submenu_page( $menu_slug, $submenu_slug ) {
	global $submenu;

	if ( ! isset( $submenu[ $menu_slug ] ) ) {
		return false;
	}

	foreach ( $submenu[ $menu_slug ] as $i => $item ) {
		if ( $submenu_slug === $item[2] ) {
			unset( $submenu[ $menu_slug ][ $i ] );
			return $item;
		}
	}

	return false;
}

/**
 * Gets the parent file of the current admin page.
 *
 * @since 1.5.0
 *
 * @global string $parent_file
 * @global array  $menu
 * @global array  $submenu
 * @global string $pagenow              The filename of the current screen.
 * @global string $typenow              The post type of the current screen.
 * @global string $plugin_page
 * @global array  $_wp_real_parent_file
 * @global array  $_wp_menu_nopriv
 * @global array  $_wp_submenu_nopriv
 *
 * @param string $parent_page Optional. The slug name for the parent menu (or the file name
 *                            of a standard WordPress admin page). Default empty string.
 * @return string The parent file of the current admin page.
 */
function get_admin_page_parent( $parent_page = '' ) {
	global $parent_file, $menu, $submenu, $pagenow, $typenow,
		$plugin_page, $_wp_real_parent_file, $_wp_menu_nopriv, $_wp_submenu_nopriv;

	if ( ! empty( $parent_page ) && 'admin.php' !== $parent_page ) {
		return $_wp_real_parent_file[ $parent_page ] ?? $parent_page;
	}

	if ( 'admin.php' === $pagenow && isset( $plugin_page ) ) {
		foreach ( (array) $menu as $parent_menu ) {
			if ( $parent_menu[2] === $plugin_page ) {
				$parent_file = $plugin_page;
				return $_wp_real_parent_file[ $parent_file ] ?? $parent_file;
			}
		}
		if ( isset( $_wp_menu_nopriv[ $plugin_page ] ) ) {
			$parent_file = $plugin_page;
			return $_wp_real_parent_file[ $parent_file ] ?? $parent_file;
		}
	}

	if ( isset( $plugin_page ) && isset( $_wp_submenu_nopriv[ $pagenow ][ $plugin_page ] ) ) {
		$parent_file = $pagenow;
		return $_wp_real_parent_file[ $parent_file ] ?? $parent_file;
	}

	foreach ( array_keys( (array) $submenu ) as $parent_page ) {
		foreach ( $submenu[ $parent_page ] as $submenu_array ) {
			if ( isset( $_wp_real_parent_file[ $parent_page ] ) ) {
				$parent_page = $_wp_real_parent_file[ $parent_page ];
			}

			if ( ! empty( $typenow ) && "$pagenow?post_type=$typenow" === $submenu_array[2] ) {
				$parent_file = $parent_page;
				return $parent_page;
			} elseif ( empty( $typenow ) && $pagenow === $submenu_array[2]
				&& ( empty( $parent_file ) || ! str_contains( $parent_file, '?' ) )
			) {
				$parent_file = $parent_page;
				return $parent_page;
			} elseif ( isset( $plugin_page ) && $plugin_page === $submenu_array[2] ) {
				$parent_file = $parent_page;
				return $parent_page;
			}
		}
	}

	if ( empty( $parent_file ) ) {
		$parent_file = '';
	}
	return '';
}

/**
 * Gets the hook attached to the administrative page of a plugin.
 *
 * @since 1.5.0
 *
 * @param string $plugin_page The slug name of the plugin page.
 * @param string $parent_page The slug name for the parent menu (or the file name of a standard
 *                            WordPress admin page).
 * @return string|null Hook attached to the plugin page, null otherwise.
 */
function get_plugin_page_hook( $plugin_page, $parent_page ) {
	$hook = get_plugin_page_hookname( $plugin_page, $parent_page );
	if ( has_action( $hook ) ) {
		return $hook;
	} else {
		return null;
	}
}

/**
 * Gets the hook name for the administrative page of a plugin.
 *
 * @since 1.5.0
 *
 * @global array $admin_page_hooks
 *
 * @param string $plugin_page The slug name of the plugin page.
 * @param string $parent_page The slug name for the parent menu (or the file name of a standard
 *                            WordPress admin page).
 * @return string Hook name for the plugin page.
 */
function get_plugin_page_hookname( $plugin_page, $parent_page ) {
	global $admin_page_hooks;

	$parent = get_admin_page_parent( $parent_page );

	$page_type = 'admin';
	if ( empty( $parent_page ) || 'admin.php' === $parent_page || isset( $admin_page_hooks[ $plugin_page ] ) ) {
		if ( isset( $admin_page_hooks[ $plugin_page ] ) ) {
			$page_type = 'toplevel';
		} elseif ( isset( $admin_page_hooks[ $parent ] ) ) {
			$page_type = $admin_page_hooks[ $parent ];
		}
	} elseif ( isset( $admin_page_hooks[ $parent ] ) ) {
		$page_type = $admin_page_hooks[ $parent ];
	}

	$plugin_name = preg_replace( '!\.php!', '', $plugin_page );

	return $page_type . '_page_' . $plugin_name;
}

/**
 * Determines whether the current user can access the current admin page.
 *
 * @since 1.5.0
 *
 * @global string $pagenow            The filename of the current screen.
 * @global array  $menu
 * @global array  $submenu
 * @global array  $_wp_menu_nopriv
 * @global array  $_wp_submenu_nopriv
 * @global string $plugin_page
 * @global array  $_registered_pages
 *
 * @return bool True if the current user can access the admin page, false otherwise.
 */
function user_can_access_admin_page() {
	global $pagenow, $menu, $submenu, $_wp_menu_nopriv, $_wp_submenu_nopriv,
		$plugin_page, $_registered_pages;

	$parent = get_admin_page_parent();

	if ( ! isset( $plugin_page ) && isset( $_wp_submenu_nopriv[ $parent ][ $pagenow ] ) ) {
		return false;
	}

	if ( isset( $plugin_page ) ) {
		if ( isset( $_wp_submenu_nopriv[ $parent ][ $plugin_page ] ) ) {
			return false;
		}

		$hookname = get_plugin_page_hookname( $plugin_page, $parent );

		if ( ! isset( $_registered_pages[ $hookname ] ) ) {
			return false;
		}
	}

	if ( empty( $parent ) ) {
		if ( isset( $_wp_menu_nopriv[ $pagenow ] ) ) {
			return false;
		}
		if ( isset( $_wp_submenu_nopriv[ $pagenow ][ $pagenow ] ) ) {
			return false;
		}
		if ( isset( $plugin_page ) && isset( $_wp_submenu_nopriv[ $pagenow ][ $plugin_page ] ) ) {
			return false;
		}
		if ( isset( $plugin_page ) && isset( $_wp_menu_nopriv[ $plugin_page ] ) ) {
			return false;
		}

		foreach ( array_keys( $_wp_submenu_nopriv ) as $key ) {
			if ( isset( $_wp_submenu_nopriv[ $key ][ $pagenow ] ) ) {
				return false;
			}
			if ( isset( $plugin_page ) && isset( $_wp_submenu_nopriv[ $key ][ $plugin_page ] ) ) {
				return false;
			}
		}

		return true;
	}

	if ( isset( $plugin_page ) && $plugin_page === $parent && isset( $_wp_menu_nopriv[ $plugin_page ] ) ) {
		return false;
	}

	if ( isset( $submenu[ $parent ] ) ) {
		foreach ( $submenu[ $parent ] as $submenu_array ) {
			if ( isset( $plugin_page ) && $submenu_array[2] === $plugin_page ) {
				return current_user_can( $submenu_array[1] );
			} elseif ( $submenu_array[2] === $pagenow ) {
				return current_user_can( $submenu_array[1] );
			}
		}
	}

	foreach ( $menu as $menu_array ) {
		if ( $menu_array[2] === $parent ) {
			return current_user_can( $menu_array[1] );
		}
	}

	return true;
}
