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
		$this->assertNull( self::parent_item(), 'No product declared support, so no shared menu.' );
	}

	/**
	 * The site is recorded as an existing install when it has content, and
	 * as a fresh one when it has no content, settings or license key.
	 */
	public function test_notice_state_existing_and_fresh() {
		global $wpdb;

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
	 * With CP Essentials hosting Settings (its page under the shared menu),
	 * there is one Settings entry, Essentials' page is the first Settings
	 * tab, and Essentials' own reordering (its Menu::order(), run with the
	 * order the framework hands its filter) keeps the approved order.
	 */
	public function test_with_essentials_settings_host() {
		$essentials = WP_PLUGIN_DIR . '/cp-essentials/includes/Admin/Menu.php';

		if ( ! is_readable( $essentials ) ) {
			$this->markTestSkipped( 'CP Essentials is not installed.' );
		}

		require_once $essentials;

		add_action(
			'admin_menu',
			static function () {
				add_submenu_page( Menu::SLUG, 'Settings', 'Settings', 'manage_options', Menu::ESSENTIALS_SETTINGS, '__return_null' );
				add_submenu_page( Menu::SLUG, 'Church Info', 'Church Info', 'manage_options', 'cp-essentials', '__return_null' );
			},
			9
		);

		cp_fw_build_admin_menu( 'menuadmin' );

		$entries = self::entries();

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
}
