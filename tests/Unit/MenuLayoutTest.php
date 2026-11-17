<?php
/**
 * Layout rules of the shared "Church Plugins" admin menu.
 *
 * @package ChurchPlugins
 */

namespace ChurchPlugins\Tests;

use ChurchPlugins\Admin\Menu;
use ChurchPlugins\Admin\MenuLayout;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ChurchPlugins\Admin\MenuLayout
 */
class MenuLayoutTest extends TestCase {

	/**
	 * Product table as the framework ships it (labels need no WordPress).
	 *
	 * @return array
	 */
	protected function products() {
		return array(
			'cp-library'           => array( 'position' => 10, 'post_types' => array( 'cpl_item_type', 'cpl_item' ), 'settings' => 'cpl_main_options' ),
			'cp-groups'            => array( 'position' => 30, 'post_types' => array( 'cp_group' ), 'settings' => 'cp_groups_main_options' ),
			'cp-staff'             => array( 'position' => 40, 'post_types' => array( 'cp_staff' ), 'settings' => 'cp_staff_staff_options' ),
			'cp-locations'         => array( 'position' => 50, 'post_types' => array( 'cploc_location' ) ),
			'cp-resources'         => array( 'position' => 60, 'post_types' => array( 'cp_resource' ) ),
			'cp-notification-bars' => array( 'position' => 70, 'post_types' => array( 'cp_notification_bar' ) ),
			'cp-live'              => array( 'position' => 75, 'settings' => 'cp_live_main_options' ),
			'cp-sync'              => array( 'position' => 90, 'menu' => 'cps_settings' ),
		);
	}

	/**
	 * A top-level menu as core builds it on a site with CP Sermons (Series
	 * first), CP Groups and CP Staff, plus Pages and an unrelated plugin.
	 *
	 * @return array
	 */
	protected function core_menu() {
		return array(
			20 => array( 'Pages', 'edit_pages', 'edit.php?post_type=page', '', 'menu-top' ),
			26 => array( 'Series', 'edit_posts', 'edit.php?post_type=cpl_item_type', '', 'menu-top' ),
			27 => array( 'Groups', 'edit_posts', 'edit.php?post_type=cp_group', '', 'menu-top' ),
			28 => array( 'Staff', 'edit_posts', 'edit.php?post_type=cp_staff', '', 'menu-top' ),
			29 => array( 'Events', 'edit_posts', 'edit.php?post_type=tribe_events', '', 'menu-top' ),
			80 => array( 'Settings', 'manage_options', 'options-general.php', '', 'menu-top' ),
		);
	}

	/**
	 * Each product's first post type with a top-level menu becomes its area;
	 * unrelated menus stay.
	 */
	public function test_plan_areas_picks_each_products_top_level_menu() {
		$areas = MenuLayout::plan_areas( $this->core_menu(), $this->products() );

		$this->assertSame( array( 'cp-library', 'cp-groups', 'cp-staff' ), array_keys( $areas ) );
		$this->assertSame( 'edit.php?post_type=cpl_item_type', $areas['cp-library']['old'] );
		$this->assertSame( 'Series', $areas['cp-library']['label'] );
		$this->assertSame( 'edit_posts', $areas['cp-groups']['capability'] );
		$this->assertSame( 30, $areas['cp-groups']['position'] );
	}

	/**
	 * With "Messages" as CP Sermons' default menu item, the area leads with Messages.
	 */
	public function test_plan_areas_follows_cp_sermons_default_menu_item() {
		$menu     = $this->core_menu();
		$menu[26] = array( 'Messages', 'edit_posts', 'edit.php?post_type=cpl_item', '', 'menu-top' );

		$areas = MenuLayout::plan_areas( $menu, $this->products() );

		$this->assertSame( 'edit.php?post_type=cpl_item', $areas['cp-library']['old'] );
		$this->assertSame( 'Messages', $areas['cp-library']['label'] );
	}

	/**
	 * A post type registered through the framework but not in the product
	 * table (a product the table does not know yet) still moves.
	 */
	public function test_plan_areas_moves_unknown_framework_post_types() {
		$menu     = $this->core_menu();
		$menu[30] = array( 'Prayer Requests', 'edit_posts', 'edit.php?post_type=cp_prayer', '', 'menu-top' );

		$areas = MenuLayout::plan_areas( $menu, $this->products(), array( 'cp_prayer', 'cp_group' ) );

		$this->assertArrayHasKey( 'post_type:cp_prayer', $areas );
		$this->assertSame( MenuLayout::DEFAULT_SLOT, $areas['post_type:cp_prayer']['position'] );
		$this->assertArrayNotHasKey( 'post_type:cp_group', $areas, 'A product area is not added twice.' );
		$this->assertArrayNotHasKey( 'post_type:tribe_events', $areas, 'Post types from other plugins stay put.' );
	}

	/**
	 * A product whose post type is shown under another menu (no top-level
	 * item) has no area to move.
	 */
	public function test_plan_areas_skips_products_without_a_top_level_menu() {
		$areas = MenuLayout::plan_areas( array( 20 => array( 'Pages', 'edit_pages', 'edit.php?post_type=page' ) ), $this->products() );

		$this->assertSame( array(), $areas );
	}

	/**
	 * Applying areas removes the old top-level items, keeps their submenu
	 * arrays (core resolves access and hook suffixes through them) and adds
	 * one entry per area under the shared parent.
	 */
	public function test_apply_areas_moves_top_level_items_and_keeps_old_submenus() {
		$menu    = $this->core_menu();
		$submenu = array(
			'edit.php?post_type=cpl_item_type' => array(
				array( 'Series', 'edit_posts', 'edit.php?post_type=cpl_item_type' ),
				array( 'Tools', 'manage_options', 'cp-library-tools' ),
			),
		);
		$areas   = MenuLayout::plan_areas( $menu, $this->products() );

		list( $menu, $submenu ) = MenuLayout::apply_areas( $menu, $submenu, $areas );

		$slugs = array_column( $menu, 2 );
		$this->assertNotContains( 'edit.php?post_type=cpl_item_type', $slugs );
		$this->assertNotContains( 'edit.php?post_type=cp_group', $slugs );
		$this->assertContains( 'edit.php?post_type=tribe_events', $slugs );
		$this->assertContains( 'edit.php?post_type=page', $slugs );

		$this->assertCount( 2, $submenu['edit.php?post_type=cpl_item_type'], 'The old submenu stays for core lookups.' );
		$this->assertSame(
			array( 'edit.php?post_type=cpl_item_type', 'edit.php?post_type=cp_group', 'edit.php?post_type=cp_staff' ),
			array_column( $submenu[ Menu::SLUG ], 2 )
		);
		$this->assertSame( 'edit_posts', $submenu[ Menu::SLUG ][0][1], 'An area entry keeps its menu capability.' );
	}

	/**
	 * Hiding an item keeps it findable for core under its real parent.
	 */
	public function test_hide_item_moves_item_to_a_holder_mapped_to_the_real_parent() {
		$submenu = array(
			'options-general.php' => array(
				array( 'General', 'manage_options', 'options-general.php' ),
				array( 'CP Live', 'manage_options', 'cp_live_main_options' ),
			),
		);

		list( $submenu, $real, $moved ) = MenuLayout::hide_item( $submenu, array(), 'options-general.php', 'cp_live_main_options' );

		$holder = MenuLayout::HOLDER_PREFIX . 'options-general.php';

		$this->assertTrue( $moved );
		$this->assertSame( array( 'options-general.php' ), array_column( $submenu['options-general.php'], 2 ) );
		$this->assertSame( 'cp_live_main_options', $submenu[ $holder ][0][2] );
		$this->assertSame( 'options-general.php', $real[ $holder ] );
	}

	/**
	 * Hiding something that is not there changes nothing.
	 */
	public function test_hide_item_without_the_item_changes_nothing() {
		$submenu = array( 'options-general.php' => array( array( 'General', 'manage_options', 'options-general.php' ) ) );

		list( $after, $real, $moved ) = MenuLayout::hide_item( $submenu, array(), 'options-general.php', 'missing' );

		$this->assertFalse( $moved );
		$this->assertSame( $submenu, $after );
		$this->assertSame( array(), $real );
	}

	/**
	 * Tab links are built the way core links submenu items.
	 */
	public function test_item_path_matches_core_links() {
		$this->assertSame( 'edit.php?post_type=cpl_item', MenuLayout::item_path( 'edit.php?post_type=cpl_item_type', 'edit.php?post_type=cpl_item' ) );
		$this->assertSame( 'edit-tags.php?taxonomy=cpl_topic&post_type=cpl_item_type', MenuLayout::item_path( 'edit.php?post_type=cpl_item_type', 'edit-tags.php?taxonomy=cpl_topic&amp;post_type=cpl_item_type' ) );
		$this->assertSame( 'edit.php?post_type=cpl_item_type&page=cp-library-tools', MenuLayout::item_path( 'edit.php?post_type=cpl_item_type', 'cp-library-tools' ) );
		$this->assertSame( 'admin.php?page=cpl_main_options', MenuLayout::item_path( 'church-plugins', 'cpl_main_options' ) );
	}

	/**
	 * An area's tabs are its old submenu minus "Add New" links and the pages
	 * that moved to Settings or Licenses.
	 */
	public function test_area_tabs_drop_add_new_settings_and_license_pages() {
		$items = array(
			array( 'Series', 'edit_posts', 'edit.php?post_type=cpl_item_type' ),
			array( 'Add New Series', 'edit_posts', 'post-new.php?post_type=cpl_item_type' ),
			array( 'Messages', 'edit_posts', 'edit.php?post_type=cpl_item' ),
			array( 'Topics', 'manage_categories', 'edit-tags.php?taxonomy=cpl_topic&amp;post_type=cpl_item_type' ),
			array( 'Tools', 'manage_options', 'cp-library-tools' ),
			array( 'Settings', 'manage_options', 'cpl_main_options' ),
		);

		$tabs = MenuLayout::area_tabs( $items, 'edit.php?post_type=cpl_item_type', array( 'cpl_main_options', 'cpl_license' ) );

		$this->assertSame( array( 'Series', 'Messages', 'Topics', 'Tools' ), array_column( $tabs, 'label' ) );
		$this->assertSame( 'manage_categories', $tabs[2]['capability'] );
		$this->assertSame( 'manage_options', $tabs[3]['capability'] );
	}

	/**
	 * Count bubbles are not part of a tab label.
	 */
	public function test_strip_count_removes_bubbles() {
		$this->assertSame( 'Messages', MenuLayout::strip_count( 'Messages <span class="awaiting-mod count-3"><span class="pending-count">3</span></span>' ) );
		$this->assertSame( 'Life Stages', MenuLayout::strip_count( 'Life Stages' ) );
	}

	/**
	 * Every kind of screen finds its tab: list, add-new and edit screens of a
	 * post type, taxonomy screens, and plugin pages.
	 */
	public function test_current_matches_post_type_taxonomy_and_page_screens() {
		$groups = array(
			'cp-library' => array(
				'tabs' => array(
					array( 'path' => 'edit.php?post_type=cpl_item_type' ),
					array( 'path' => 'edit.php?post_type=cpl_item' ),
					array( 'path' => 'edit-tags.php?taxonomy=cpl_topic&post_type=cpl_item_type' ),
					array( 'path' => 'edit.php?post_type=cpl_item_type&page=cp-library-tools' ),
				),
			),
			'settings'   => array(
				'tabs' => array(
					array( 'path' => 'admin.php?page=cpl_main_options', 'pages' => array( 'cpl_main_options', 'cpl_item_options' ) ),
				),
			),
		);

		$this->assertSame( array( 'group' => 'cp-library', 'tab' => 1 ), MenuLayout::current( $groups, MenuLayout::screen_target( 'edit', 'cpl_item', '' ) ) );
		$this->assertSame( array( 'group' => 'cp-library', 'tab' => 1 ), MenuLayout::current( $groups, MenuLayout::screen_target( 'post', 'cpl_item', '' ) ), 'Add-new and edit screens.' );
		$this->assertSame( array( 'group' => 'cp-library', 'tab' => 2 ), MenuLayout::current( $groups, MenuLayout::screen_target( 'edit-tags', 'cpl_item_type', 'cpl_topic' ) ) );
		$this->assertSame( array( 'group' => 'cp-library', 'tab' => 2 ), MenuLayout::current( $groups, MenuLayout::screen_target( 'term', 'cpl_item_type', 'cpl_topic' ) ) );
		$this->assertSame( array( 'group' => 'cp-library', 'tab' => 3 ), MenuLayout::current( $groups, MenuLayout::screen_target( 'edit', 'cpl_item_type', '', 'cp-library-tools' ) ), 'A plugin page under a post type is not that post type\'s list.' );
		$this->assertSame( array( 'group' => 'settings', 'tab' => 0 ), MenuLayout::current( $groups, MenuLayout::screen_target( '', '', '', 'cpl_item_options' ) ), 'A product\'s settings sub-tab belongs to its Settings tab.' );
		$this->assertNull( MenuLayout::current( $groups, MenuLayout::screen_target( 'edit', 'post', '' ) ) );
		$this->assertNull( MenuLayout::screen_target( 'dashboard', '', '' ) );
	}

	/**
	 * Entries are ordered Home · Messages · Events · Groups · Staff · … ·
	 * Church Info · Sync · Settings · Licenses, unknown entries before
	 * Settings, and the parent's self-link is dropped.
	 */
	public function test_sort_submenu_follows_the_approved_order() {
		$items = array(
			array( 'Church Plugins', 'edit_posts', Menu::SLUG ),
			array( 'Sync', 'manage_options', 'cps_settings' ),
			array( 'Licenses', 'manage_options', Menu::LICENSES_SLUG ),
			array( 'Settings', 'manage_options', 'admin.php?page=cpl_main_options' ),
			array( 'Staff', 'edit_posts', 'edit.php?post_type=cp_staff' ),
			array( 'Something else', 'manage_options', 'other-plugin' ),
			array( 'Church Info', 'manage_options', 'cp-essentials' ),
			array( 'Groups', 'edit_posts', 'edit.php?post_type=cp_group' ),
			array( 'Events', 'edit_posts', 'edit.php?post_type=cp_event' ),
			array( 'Messages', 'edit_posts', 'edit.php?post_type=cpl_item' ),
			array( 'Home', 'edit_posts', 'cp-essentials-home' ),
		);

		$sorted = MenuLayout::sort_submenu( $items, MenuLayout::slots( $this->products(), 'admin.php?page=cpl_main_options' ) );

		$this->assertSame(
			array( 'Home', 'Messages', 'Events', 'Groups', 'Staff', 'Church Info', 'Sync', 'Something else', 'Settings', 'Licenses' ),
			array_column( $sorted, 0 )
		);
	}

	/**
	 * CP Essentials' own reordering is handed the same order.
	 */
	public function test_essentials_order_keeps_licenses_last() {
		$items = array(
			array( 'Messages', 'edit_posts', 'edit.php?post_type=cpl_item' ),
			array( 'Church Info', 'manage_options', 'cp-essentials' ),
			array( 'Settings', 'manage_options', 'cp-essentials-settings' ),
			array( 'Licenses', 'manage_options', Menu::LICENSES_SLUG ),
		);

		$order = MenuLayout::essentials_order( $items, MenuLayout::slots( $this->products() ) );

		$this->assertSame( array( 'edit.php?post_type=cpl_item', 'cp-essentials', 'cp-essentials-settings' ), $order['first'] );
		$this->assertSame( array( Menu::LICENSES_SLUG ), $order['last'] );
	}

	/**
	 * Old License tabs forward to the Licenses screen with the activation
	 * error a failed activation put in the URL.
	 */
	public function test_license_redirect_keeps_activation_messages() {
		$url = MenuLayout::license_redirect(
			'cpl_license',
			array( 'cpl_license', 'cp_groups_license' ),
			array( 'sl_activation' => 'false', 'message' => 'Your license key expired.', 'other' => 'x' ),
			'https://example.test/wp-admin/admin.php?page=cp-licenses'
		);

		$this->assertSame( 'https://example.test/wp-admin/admin.php?page=cp-licenses&sl_activation=false&message=Your%20license%20key%20expired.', $url, 'Opens at the top, where the error is printed.' );
		$this->assertSame( 'x#cp_groups_license', MenuLayout::license_redirect( 'cp_groups_license', array( 'cp_groups_license' ), array(), 'x' ), 'Without an error it opens at the row.' );
	}

	/**
	 * Other pages are left alone.
	 */
	public function test_license_redirect_ignores_other_pages() {
		$this->assertSame( '', MenuLayout::license_redirect( 'cpl_main_options', array( 'cpl_license' ), array(), 'x' ) );
		$this->assertSame( '', MenuLayout::license_redirect( '', array( 'cpl_license' ), array(), 'x' ) );
	}

	/**
	 * Any sign of use makes a site an existing install; a fresh one has none.
	 */
	public function test_is_existing_install() {
		$this->assertFalse( MenuLayout::is_existing_install( 0, false, false ) );
		$this->assertTrue( MenuLayout::is_existing_install( 3, false, false ) );
		$this->assertTrue( MenuLayout::is_existing_install( 0, true, false ) );
		$this->assertTrue( MenuLayout::is_existing_install( 0, false, true ) );
	}

	/**
	 * The notice goes to existing installs only, to people who could see the
	 * old menus, registered before the move, until they dismiss it.
	 */
	public function test_notice_applies() {
		$existing = array( 'existing' => true, 'since' => 1000 );

		$this->assertTrue( MenuLayout::notice_applies( $existing, 500, true, false, false ), 'Editor who used the content menus.' );
		$this->assertTrue( MenuLayout::notice_applies( $existing, 500, false, true, false ), 'Admin who used the settings.' );
		$this->assertFalse( MenuLayout::notice_applies( array( 'existing' => false, 'since' => 1000 ), 500, true, true, false ), 'Fresh install.' );
		$this->assertFalse( MenuLayout::notice_applies( $existing, 500, true, true, true ), 'Dismissed.' );
		$this->assertFalse( MenuLayout::notice_applies( $existing, 500, false, false, false ), 'Could not see the old menus.' );
		$this->assertFalse( MenuLayout::notice_applies( $existing, 2000, true, true, false ), 'Registered after the move.' );
		$this->assertFalse( MenuLayout::notice_applies( array(), 500, true, true, false ), 'No state recorded.' );
	}

	/**
	 * A product's own taxonomies only show as tabs in its own area.
	 */
	public function test_drop_foreign_taxonomies() {
		$tabs  = array(
			array( 'label' => 'Groups', 'path' => 'edit.php?post_type=cp_group' ),
			array( 'label' => 'Types', 'path' => 'edit-tags.php?taxonomy=cp_group_type&post_type=cp_group' ),
			array( 'label' => 'Resource Types', 'path' => 'edit-tags.php?taxonomy=cp_resource_type&post_type=cp_group' ),
		);
		$owned = array( 'cp_resource_type' => 'cp-resources' );

		$this->assertSame( array( 'Groups', 'Types' ), array_column( MenuLayout::drop_foreign_taxonomies( $tabs, 'cp-groups', $owned ), 'label' ) );
		$this->assertCount( 3, MenuLayout::drop_foreign_taxonomies( $tabs, 'cp-resources', $owned ) );
	}
}
