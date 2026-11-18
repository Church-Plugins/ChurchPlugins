<?php
/**
 * The shared "Church Plugins" menu on a real site.
 *
 * @package ChurchPlugins
 */

namespace ChurchPlugins\Tests\Integration;

use ChurchPlugins\Admin\Licenses;
use ChurchPlugins\Admin\Menu;
use ChurchPlugins\Admin\MenuLayout;
use ChurchPlugins\Admin\MenuNotice;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SharedMenuTest extends TestCase {

	/**
	 * Slugs of the shared menu's entries.
	 *
	 * @return string[]
	 */
	protected static function entries() {
		global $submenu;

		return array_column( isset( $submenu[ Menu::SLUG ] ) ? $submenu[ Menu::SLUG ] : array(), 2 );
	}

	/**
	 * Slugs of the top-level menu.
	 *
	 * @return string[]
	 */
	protected static function top_level() {
		global $menu;

		return array_column( (array) $menu, 2 );
	}

	/**
	 * The parent menu item.
	 *
	 * @return array|null
	 */
	protected static function parent_item() {
		global $menu;

		foreach ( (array) $menu as $item ) {
			if ( Menu::SLUG === $item[2] ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Administrators get one menu: areas in order, then Settings and Licenses;
	 * no product top-level menus; CP Live gone from WordPress's Settings.
	 */
	public function test_administrator_menu() {
		global $submenu;

		cp_fw_build_admin_menu( 'menuadmin' );

		$parent = self::parent_item();
		$this->assertNotNull( $parent );
		$this->assertSame( 'edit_posts', $parent[1], 'Content editors can open the menu.' );

		$entries = self::entries();
		$this->assertContains( 'edit.php?post_type=cp_group', $entries );
		$this->assertContains( 'edit.php?post_type=cp_staff', $entries );
		$this->assertContains( Menu::LICENSES_SLUG, $entries );
		$this->assertSame( Menu::LICENSES_SLUG, end( $entries ), 'Licenses is last.' );
		$this->assertContains( Menu::get_instance()->settings_entry(), $entries );
		$this->assertLessThan( array_search( 'edit.php?post_type=cp_staff', $entries, true ), array_search( 'edit.php?post_type=cp_group', $entries, true ), 'Groups comes before Staff.' );

		foreach ( array( 'edit.php?post_type=cp_group', 'edit.php?post_type=cp_staff', 'edit.php?post_type=cpl_item_type', 'edit.php?post_type=cpl_item' ) as $old ) {
			$this->assertNotContains( $old, self::top_level(), "{$old} is no longer a top-level menu." );
		}

		$this->assertNotContains( 'cp_live_main_options', array_column( $submenu['options-general.php'], 2 ) );
	}

	/**
	 * Editors reach every content area but not Settings or Licenses.
	 */
	public function test_editor_menu() {
		cp_fw_build_admin_menu( 'menueditor' );

		$entries = self::entries();

		$this->assertContains( 'edit.php?post_type=cp_group', $entries );
		$this->assertContains( 'edit.php?post_type=cp_staff', $entries );
		$this->assertNotContains( Menu::LICENSES_SLUG, $entries );
		$this->assertNotContains( Menu::get_instance()->settings_entry(), $entries );
	}

	/**
	 * Moved pages keep their hook suffix and their access rule.
	 *
	 * @dataProvider moved_pages
	 *
	 * @param string $pagenow  Admin file.
	 * @param array  $get      Query.
	 * @param string $hook     Hook suffix the page had before the move.
	 */
	public function test_moved_pages_keep_hook_suffix_and_access( $pagenow, array $get, $hook ) {
		global $_registered_pages;

		cp_fw_build_admin_menu( 'menuadmin', $pagenow, $get );

		$parent = $pagenow . ( isset( $get['post_type'] ) ? '?post_type=' . $get['post_type'] : '' );

		$this->assertSame( $hook, get_plugin_page_hook( $get['page'], $parent ), 'admin.php resolves the same hook.' );
		$this->assertArrayHasKey( $hook, $_registered_pages );
		$this->assertTrue( user_can_access_admin_page(), 'An administrator can still open it.' );
	}

	/**
	 * Pages and the hook suffixes they were registered with.
	 *
	 * @return array
	 */
	public function moved_pages() {
		return array(
			'CP Live settings (from WordPress Settings)' => array( 'admin.php', array( 'page' => 'cp_live_main_options' ), 'settings_page_cp_live_main_options' ),
			'CP Sermons settings'                        => array( 'admin.php', array( 'page' => 'cpl_main_options' ), 'cpl_item_type_page_cpl_main_options' ),
			'CP Sermons Tools'                           => array( 'edit.php', array( 'post_type' => 'cpl_item_type', 'page' => 'cp-library-tools' ), 'cpl_item_type_page_cp-library-tools' ),
			'CP Groups settings sub-tab'                 => array( 'admin.php', array( 'page' => 'cp_groups_advanced_options' ), 'admin_page_cp_groups_advanced_options' ),
		);
	}

	/**
	 * An editor is still refused the settings pages.
	 */
	public function test_editor_cannot_open_moved_settings() {
		// Core refuses the page while it builds the menu, then wp_die()s.
		add_action(
			'admin_page_access_denied',
			static function () {
				throw new \RuntimeException( 'denied' );
			}
		);

		$this->expectExceptionMessage( 'denied' );

		cp_fw_build_admin_menu( 'menueditor', 'admin.php', array( 'page' => 'cpl_main_options' ) );
	}

	/**
	 * An administrator opens the same page (the check above is not a fluke
	 * of the harness).
	 */
	public function test_administrator_can_open_moved_settings() {
		cp_fw_build_admin_menu( 'menuadmin', 'admin.php', array( 'page' => 'cpl_main_options' ) );

		$this->assertTrue( user_can_access_admin_page() );
	}

	/**
	 * While the menu is printed, a moved screen opens the shared menu even
	 * though core recomputes the parent after the filters; the mapping is
	 * undone afterwards.
	 */
	public function test_moved_screen_opens_the_shared_menu() {
		global $parent_file, $submenu_file, $_wp_real_parent_file;

		cp_fw_build_admin_menu( 'menuadmin', 'edit.php', array( 'post_type' => 'cpl_item' ) );
		set_current_screen( 'edit-cpl_item' );

		$before = $_wp_real_parent_file;

		// What wp-admin/menu-header.php does.
		$parent_file  = apply_filters( 'parent_file', 'edit.php?post_type=cpl_item_type' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu_file = apply_filters( 'submenu_file', 'edit.php?post_type=cpl_item', $parent_file ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		get_admin_page_parent();

		$this->assertSame( Menu::SLUG, $parent_file );
		$this->assertSame( 'edit.php?post_type=cpl_item_type', $submenu_file, 'The Messages list highlights the sermons entry.' );

		do_action( 'adminmenu' );

		$this->assertSame( $before, $_wp_real_parent_file );
	}

	/**
	 * Tabs of the sermons area and of Settings.
	 */
	public function test_tab_groups() {
		cp_fw_build_admin_menu( 'menuadmin' );

		$groups = Menu::get_instance()->groups();

		$labels = array_column( $groups['cp-library']['tabs'], 'path' );
		$this->assertContains( 'edit.php?post_type=cpl_item', $labels );
		$this->assertContains( 'edit.php?post_type=cpl_item_type&page=cp-library-tools', $labels );
		$this->assertNotContains( 'admin.php?page=cpl_main_options', $labels, 'Settings is not an area tab.' );

		$settings = array_column( $groups['settings']['tabs'], 'pages', 'key' );
		$this->assertContains( 'cpl_item_options', $settings['cp-library'] );
		$this->assertNotContains( 'cpl_license', $settings['cp-library'], 'The License tab moved to Licenses.' );
		$this->assertArrayHasKey( 'cp-live', $settings );
	}

	/**
	 * A product that was not updated (its bundled framework is older and it
	 * calls none of the new APIs) still moves.
	 */
	public function test_unupdated_product_moves() {
		$file = WP_PLUGIN_DIR . '/cp-groups/includes/ChurchPlugins/init.php';

		if ( ! is_readable( $file ) || ! preg_match( "/const VERSION = '([0-9.]+)'/", (string) file_get_contents( $file ), $m ) || version_compare( $m[1], CHURCHPLUGINS_VERSION, '>=' ) ) {
			$this->markTestSkipped( 'CP Groups bundles this framework version; nothing to compare.' );
		}

		cp_fw_build_admin_menu( 'menuadmin' );

		$this->assertContains( 'edit.php?post_type=cp_group', self::entries(), "CP Groups (bundling {$m[1]}) moved." );
		$this->assertNotContains( 'edit.php?post_type=cp_group', self::top_level() );
		$this->assertContains( 'cp_groups_license', Licenses::ids() );
	}

	/**
	 * The old License tabs leave their product's settings tabs.
	 */
	public function test_license_boxes_leave_product_tabs() {
		cp_fw_build_admin_menu( 'menuadmin' );

		$this->assertTrue( Licenses::get_instance()->has_moved_tabs() );
		$this->assertSame( Licenses::TAB_GROUP, cmb2_get_metabox( 'cpl_options_page' )->prop( 'tab_group' ) );
		$this->assertSame( 'cpl_main_options', cmb2_get_metabox( 'cpl_item_options_page' )->prop( 'tab_group' ) );
	}

	/**
	 * `cp_admin_shared_menu` false keeps the old layout.
	 */
	public function test_opt_out_keeps_old_layout() {
		add_filter( 'cp_admin_shared_menu', '__return_false' );

		cp_fw_build_admin_menu( 'menuadmin' );

		$this->assertContains( 'edit.php?post_type=cp_group', self::top_level() );
		$this->assertContains( 'cp_live_main_options', array_column( $GLOBALS['submenu']['options-general.php'], 2 ) );
		$this->assertNotContains( 'edit.php?post_type=cp_group', self::entries() );
		$this->assertNotContains( Menu::LICENSES_SLUG, self::entries() );

		if ( function_exists( 'cp_essentials' ) ) {
			// CP Essentials declares support, so the menu holds its screens only.
			$this->assertSame( 1, count( array_keys( self::top_level(), Menu::SLUG, true ) ) );
		} else {
			$this->assertNull( self::parent_item(), 'No product declared support, so no shared menu.' );
		}
	}

	/**
	 * The site is recorded as an existing install when it has content, and
	 * as a fresh one when it has no content, settings or license key. CP
	 * Essentials is taken out of the picture here; see
	 * test_notice_state_with_essentials_active().
	 */
	public function test_notice_state_existing_and_fresh() {
		global $wpdb;

		add_filter( 'cp_admin_menu_essentials_active', '__return_false' );

		$wpdb->query( 'START TRANSACTION' );

		try {
			delete_option( MenuNotice::OPTION );
			MenuNotice::get_instance()->record_state();
			$state = get_option( MenuNotice::OPTION );
			$this->assertTrue( $state['existing'], 'A site with content is an existing install.' );

			// Strip the site back to a fresh install.
			delete_option( MenuNotice::OPTION );

			foreach ( Menu::products() as $product ) {
				foreach ( MenuLayout::product_post_types( $product ) as $post_type ) {
					$wpdb->delete( $wpdb->posts, array( 'post_type' => $post_type ) );
					wp_cache_delete( _count_posts_cache_key( $post_type ), 'counts' );
				}

				if ( ! empty( $product['settings'] ) ) {
					delete_option( $product['settings'] );
				}
			}

			foreach ( Licenses::ids() as $id ) {
				delete_option( $id );
			}

			MenuNotice::get_instance()->record_state();
			$state = get_option( MenuNotice::OPTION );
			$this->assertFalse( $state['existing'], 'A fresh install gets no notice.' );
		} finally {
			$wpdb->query( 'ROLLBACK' );
		}
	}

	/**
	 * A product shaped like CP Sync (declares support, adds its own entry to
	 * the shared menu, has a license with no CMB2 box) gets the "Sync" slot
	 * before Settings and a license row that uses the REST routes.
	 */
	public function test_cp_sync_shaped_product() {
		Menu::add_support();

		add_action(
			'admin_menu',
			static function () {
				add_submenu_page( Menu::get_slug(), 'CP Sync', 'CP Sync', 'manage_options', 'cps_settings', '__return_null' );
			}
		);

		new \ChurchPlugins\Setup\Admin\License( 'cps_license', 438, 'https://example.invalid', WP_PLUGIN_DIR . '/cp-sync/cp-sync.php', admin_url( 'admin.php?page=cps_license' ) );

		cp_fw_build_admin_menu( 'menuadmin' );

		$items   = $GLOBALS['submenu'][ Menu::SLUG ];
		$entries = array_column( $items, 2 );
		$sync    = array_search( 'cps_settings', $entries, true );

		$this->assertNotFalse( $sync );
		$this->assertSame( 'Sync', $items[ $sync ][0] );
		$this->assertLessThan( array_search( Menu::get_instance()->settings_entry(), $entries, true ), $sync, 'Sync comes before Settings.' );
		$this->assertGreaterThan( array_search( 'edit.php?post_type=cp_staff', $entries, true ), $sync, 'Sync comes after the content areas.' );

		$rows = array_column( Licenses::get_instance()->rows(), 'box', 'id' );
		$this->assertArrayHasKey( 'cps_license', $rows );
		$this->assertNull( $rows['cps_license'], 'No CMB2 box: the row uses the license REST routes.' );
		$this->assertNotNull( $rows['cpl_license'] );
	}

	/**
	 * Add pages to the shared menu the way CP Essentials releases that used
	 * priority 9 did: support declared at priority 1, and the pages added by
	 * a callback that runs before anything the framework has at priority 9
	 * (Essentials hooks when its file loads, the framework on
	 * `plugins_loaded`).
	 *
	 * @param string[] $pages Page slugs.
	 * @return void
	 */
	protected static function hook_early_pages( array $pages ) {
		global $wp_filter;

		add_action( 'admin_menu', array( Menu::class, 'add_support' ), 1 );

		// Everything already hooked at priority 9 goes behind the new callback.
		$hooked = isset( $wp_filter['admin_menu']->callbacks[9] ) ? $wp_filter['admin_menu']->callbacks[9] : array();

		foreach ( $hooked as $callback ) {
			remove_action( 'admin_menu', $callback['function'], 9 );
		}

		add_action(
			'admin_menu',
			static function () use ( $pages ) {
				foreach ( $pages as $page ) {
					add_submenu_page( Menu::SLUG, $page, $page, 'manage_options', $page, '__return_null' );
				}
			},
			9
		);

		foreach ( $hooked as $callback ) {
			add_action( 'admin_menu', $callback['function'], 9, $callback['accepted_args'] );
		}
	}

	/**
	 * Whether CP Essentials itself is active on the site.
	 *
	 * @return bool
	 */
	protected static function essentials_active() {
		return function_exists( 'cp_essentials' );
	}

	/**
	 * With CP Essentials hosting Settings (its page under the shared menu),
	 * there is one Church Plugins menu and one Settings entry, Essentials'
	 * page is the first Settings tab, and Essentials' own reordering (its
	 * Menu::order(), run with the order the framework hands its filter) keeps
	 * the approved order. Uses CP Essentials itself when it is active, else
	 * stand-in pages hooked the way it hooks.
	 */
	public function test_with_essentials_settings_host() {
		$essentials = WP_PLUGIN_DIR . '/cp-essentials/includes/Admin/Menu.php';

		if ( ! is_readable( $essentials ) ) {
			$this->markTestSkipped( 'CP Essentials is not installed.' );
		}

		if ( ! self::essentials_active() ) {
			require_once $essentials;

			self::hook_early_pages( array( Menu::ESSENTIALS_SETTINGS, 'cp-essentials' ) );
		}

		cp_fw_build_admin_menu( 'menuadmin' );

		$entries = self::entries();

		$this->assertSame( 1, count( array_keys( self::top_level(), Menu::SLUG, true ) ), 'One Church Plugins menu.' );
		$this->assertNotContains( Menu::SLUG, $entries, 'No link from the menu to itself.' );
		$this->assertSame( Menu::ESSENTIALS_SETTINGS, Menu::get_instance()->settings_entry() );
		$this->assertSame( 1, count( array_keys( $entries, Menu::ESSENTIALS_SETTINGS, true ) ) );
		$this->assertNotContains( 'admin.php?page=cpl_main_options', $entries, 'No second Settings entry.' );

		$tabs = Menu::get_instance()->settings_tabs();
		$this->assertSame( 'cp-essentials', reset( $tabs )['key'] );

		$order    = apply_filters( 'cp_essentials_menu_order', array( 'first' => array(), 'last' => array() ) );
		$reordered = \CP_Essentials\Admin\Menu::order( $GLOBALS['submenu'][ Menu::SLUG ], $order['first'], $order['last'] );

		$this->assertSame( $entries, array_column( $reordered, 2 ), 'Essentials keeps the framework order.' );
		$this->assertLessThan( array_search( 'cp-essentials', $entries, true ), array_search( 'edit.php?post_type=cp_staff', $entries, true ), 'Church Info comes after the content areas.' );
		$this->assertLessThan( array_search( Menu::ESSENTIALS_SETTINGS, $entries, true ), array_search( 'cp-essentials', $entries, true ), 'Church Info comes before Settings.' );
	}

	/**
	 * Build the menu for one plugin page as an administrator and check that
	 * core registered the page under the shared menu and opens it.
	 *
	 * @param string $page Page slug.
	 * @return void
	 */
	protected function assert_page_opens_under_shared_menu( $page ) {
		global $_registered_pages, $_parent_pages;

		// Core wp_die()s while it builds the menu when the page is refused.
		add_action(
			'admin_page_access_denied',
			static function () {
				throw new \RuntimeException( 'denied' );
			}
		);

		cp_fw_build_admin_menu( 'menuadmin', 'admin.php', array( 'page' => $page ) );

		$hook = 'church-plugins_page_' . $page;

		$this->assertSame( 1, count( array_keys( self::top_level(), Menu::SLUG, true ) ), 'One Church Plugins menu.' );
		$this->assertSame( Menu::SLUG, $_parent_pages[ $page ], 'The parent existed when the page was added.' );
		$this->assertSame( $hook, get_plugin_page_hookname( $page, Menu::SLUG ) );
		$this->assertSame( $hook, get_plugin_page_hook( $page, Menu::SLUG ) );
		$this->assertArrayHasKey( $hook, $_registered_pages );
		$this->assertArrayNotHasKey( 'admin_page_' . $page, $_registered_pages, 'Not registered as an orphan page.' );
		$this->assertTrue( user_can_access_admin_page() );
	}

	/**
	 * A page added at priority 9 from a hook registered before the
	 * framework's (CP Essentials releases that used priority 9) finds the
	 * parent already there.
	 */
	public function test_page_added_early_at_priority_9_opens() {
		self::hook_early_pages( array( 'cp-fw-early-page' ) );

		$this->assert_page_opens_under_shared_menu( 'cp-fw-early-page' );
	}

	/**
	 * CP Essentials' own screens open under the shared menu.
	 *
	 * @dataProvider essentials_pages
	 *
	 * @param string $page Page slug.
	 */
	public function test_essentials_screens_open( $page ) {
		if ( ! self::essentials_active() ) {
			$this->markTestSkipped( 'CP Essentials is not active.' );
		}

		$this->assert_page_opens_under_shared_menu( $page );
	}

	/**
	 * @return array
	 */
	public function essentials_pages() {
		return array(
			'Settings'    => array( Menu::ESSENTIALS_SETTINGS ),
			'Church Info' => array( 'cp-essentials' ),
		);
	}

	/**
	 * Allow the current user only what a Subscriber has, without creating a
	 * user on the site.
	 *
	 * @return void
	 */
	protected static function limit_to_subscriber() {
		add_filter(
			'user_has_cap',
			static function () {
				return array(
					'read'       => true,
					'level_0'    => true,
					'subscriber' => true,
				);
			},
			PHP_INT_MAX
		);
	}

	/**
	 * A Subscriber gets no Church Plugins menu: no entries are added for
	 * screens they cannot open, so nothing keeps the top-level item alive.
	 */
	public function test_subscriber_gets_no_menu() {
		self::limit_to_subscriber();

		cp_fw_build_admin_menu( 'menueditor' );

		$this->assertFalse( current_user_can( 'edit_posts' ), 'The harness user is limited to a Subscriber.' );
		$this->assertNull( self::parent_item(), 'No Church Plugins menu.' );
		$this->assertSame( array(), self::entries() );
		$this->assertSame( array(), Menu::get_instance()->areas() );
		$this->assertSame( '', Menu::get_instance()->settings_entry() );

		foreach ( array( 'edit.php?post_type=cp_group', 'edit.php?post_type=cp_staff', 'edit.php?post_type=cpl_item_type', 'edit.php?post_type=cpl_item' ) as $old ) {
			$this->assertNotContains( $old, self::top_level(), "{$old} is not shown either." );
		}
	}

	/**
	 * Every entry of the shared menu is one the user it is printed for can
	 * open, so the top-level item (which core links to the first entry) never
	 * leads to a refusal.
	 *
	 * @dataProvider menu_users
	 *
	 * @param string $login User login.
	 */
	public function test_every_entry_can_be_opened_by_the_user( $login ) {
		cp_fw_build_admin_menu( $login );

		$items = isset( $GLOBALS['submenu'][ Menu::SLUG ] ) ? $GLOBALS['submenu'][ Menu::SLUG ] : array();

		$this->assertNotEmpty( $items );

		foreach ( $items as $item ) {
			$this->assertTrue( current_user_can( $item[1] ), "{$login} can open {$item[2]}." );
		}
	}

	/**
	 * @return array
	 */
	public function menu_users() {
		return array(
			'administrator' => array( 'menuadmin' ),
			'editor'        => array( 'menueditor' ),
		);
	}

	/**
	 * A site with CP Essentials active when the shared menu first records
	 * its state is not an existing install, whatever content it has:
	 * Essentials fills the same post types under this same menu.
	 */
	public function test_notice_state_with_essentials_active() {
		global $wpdb;

		add_filter( 'cp_admin_menu_essentials_active', '__return_true' );

		$wpdb->query( 'START TRANSACTION' );

		try {
			delete_option( MenuNotice::OPTION );
			MenuNotice::get_instance()->record_state();
			$state = get_option( MenuNotice::OPTION );

			$this->assertIsArray( $state );
			$this->assertFalse( $state['existing'] );
		} finally {
			$wpdb->query( 'ROLLBACK' );
		}
	}

	/**
	 * The license rows print keys: they are refused to a user who cannot
	 * manage options, on whatever page a host prints them.
	 */
	public function test_license_rows_need_manage_options() {
		cp_fw_build_admin_menu( 'menueditor' );

		ob_start();
		Licenses::get_instance()->render_rows();

		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Each license row's fields have their own ids; names are shared, since
	 * each row is its own form posted to the same handler.
	 */
	public function test_license_rows_have_unique_field_ids() {
		cp_fw_build_admin_menu( 'menuadmin', 'admin.php', array( 'page' => Menu::LICENSES_SLUG ) );

		ob_start();
		Licenses::get_instance()->render_rows();
		// Drop the values (license keys) so a failure message can never print one.
		$html = (string) preg_replace( '/\\svalue=("[^"]*"|\'[^\']*\')/', '', (string) ob_get_clean() );

		preg_match_all( '/<(?:input|select|textarea)\b[^>]*?\sid=["\']([^"\']+)["\']/', $html, $ids );
		preg_match_all( '/<label\b[^>]*?\sfor=["\']([^"\']+)["\']/', $html, $fors );

		$this->assertNotEmpty( $ids[1] );
		$this->assertSame( array(), array_keys( array_filter( array_count_values( $ids[1] ), static function ( $count ) { return $count > 1; } ) ), 'No field id is used twice.' );
		$this->assertSame( array(), array_values( array_diff( array_unique( $fors[1] ), $ids[1] ) ), 'Every label points at a field on the screen.' );

		if ( count( Licenses::ids() ) > 1 && Licenses::get_instance()->has_moved_tabs() ) {
			$this->assertTrue( false !== strpos( $html, 'name="license"' ), 'Posted field names are unchanged.' );
			$this->assertFalse( strpos( $html, ' id="license"' ) );
			$this->assertFalse( strpos( $html, ' for="beta"' ) );
		}
	}
}
