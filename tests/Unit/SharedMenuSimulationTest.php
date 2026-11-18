<?php
/**
 * The shared menu as core builds it, for a matrix of roles and product sets.
 *
 * MenuLayoutTest covers the pure rules. These tests cover what Admin\Menu,
 * Admin\Licenses and Admin\MenuNotice do with them when they are hooked into
 * core's own menu build: tests/menu-sim/run.php runs that build (core's
 * wp-admin/includes/menu.php and plugin API, copied from WordPress 7.1.4)
 * in a child process, one per scenario, with capabilities, options and
 * products' registrations stood in. No WordPress site and no database.
 *
 * @package ChurchPlugins
 */

namespace ChurchPlugins\Tests;

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class SharedMenuSimulationTest extends TestCase {

	const ROLES = array(
		'subscriber'    => array( 'read' ),
		'editor'        => array( 'read', 'edit_posts', 'edit_pages', 'manage_categories' ),
		'administrator' => array( 'read', 'edit_posts', 'edit_pages', 'manage_categories', 'list_users', 'manage_options' ),
		// `manage_options` without `edit_posts`: can open settings, not content.
		'options-only'  => array( 'read', 'manage_options' ),
	);

	const SETS = array(
		'sermons'   => array( 'cp-library' ),
		'all'       => array( 'cp-library', 'cp-groups', 'cp-staff', 'cp-live', 'cp-sync' ),
		'live+sync' => array( 'cp-live', 'cp-sync' ),
		'sync'      => array( 'cp-sync' ),
	);

	/**
	 * CP Essentials stand-in: absent, hooked before the framework, hooked
	 * after it, and hooked before it with its screens at priority 9 (as
	 * older releases add them).
	 */
	const ESSENTIALS = array( 'none', 'early', 'late', 'old9' );

	/**
	 * What each product registers: its area's post type and slot, its
	 * settings page and where that page hangs, and the page's hook suffix
	 * (the one it had before the shared menu).
	 */
	const PRODUCTS = array(
		'cp-library' => array(
			'area'     => 'cpl_item',
			'slot'     => 10,
			'settings' => 'cpl_main_options',
			'hook'     => 'cpl_item_page_cpl_main_options',
		),
		'cp-groups'  => array(
			'area'     => 'cp_group',
			'slot'     => 30,
			'settings' => 'cp_groups_main_options',
			'hook'     => 'cp_group_page_cp_groups_main_options',
		),
		'cp-staff'   => array(
			'area'     => 'cp_staff',
			'slot'     => 40,
			'settings' => 'cp_staff_staff_options',
			'hook'     => 'cp_staff_page_cp_staff_staff_options',
		),
		'cp-live'    => array(
			'slot'     => 75,
			'settings' => 'cp_live_main_options',
			'hook'     => 'settings_page_cp_live_main_options',
		),
		'cp-sync'    => array(
			'slot' => 90,
		),
	);

	/**
	 * Results by scenario, so tests that share one run it once.
	 *
	 * @var array<string, array>
	 */
	protected static $results = array();

	/**
	 * Run a scenario through tests/menu-sim/run.php.
	 *
	 * @param array $scenario Scenario; see run.php.
	 * @return array
	 */
	protected static function simulate( array $scenario ) {
		$json = (string) json_encode( $scenario );

		if ( ! isset( self::$results[ $json ] ) ) {
			$pipes   = array();
			$process = proc_open(
				array( PHP_BINARY, dirname( __DIR__ ) . '/menu-sim/run.php', $json ),
				array(
					1 => array( 'pipe', 'w' ),
					2 => array( 'pipe', 'w' ),
				),
				$pipes
			);

			self::assertIsResource( $process, 'The simulation could not be started.' );

			$stdout = stream_get_contents( $pipes[1] );
			$stderr = stream_get_contents( $pipes[2] );
			$status = proc_close( $process );
			$result = json_decode( (string) $stdout, true );

			self::assertSame( 0, $status, "The simulation failed for $json:\n$stderr\n$stdout" );
			self::assertIsArray( $result, "The simulation printed no result for $json:\n$stderr\n$stdout" );
			self::assertSame( '', $stderr, "The simulation printed errors for $json." );

			self::$results[ $json ] = $result;
		}

		return self::$results[ $json ];
	}

	/**
	 * @param string $role       Key of self::ROLES.
	 * @param string $set        Key of self::SETS.
	 * @param string $essentials One of self::ESSENTIALS.
	 * @return array Scenario.
	 */
	protected static function scenario( $role, $set, $essentials ) {
		return array(
			'caps'             => self::ROLES[ $role ],
			'products'         => self::SETS[ $set ],
			'essentials'       => 'none' === $essentials ? '' : $essentials,
			'essentials_types' => 'none' === $essentials ? array() : array( 'cp_event' => 'Events' ),
			'requests'         => self::requests( self::SETS[ $set ], 'none' !== $essentials ),
		);
	}

	/**
	 * The pages asked for in a scenario.
	 *
	 * @param string[] $products   Active products.
	 * @param bool     $essentials Whether CP Essentials is active.
	 * @return array name => request.
	 */
	protected static function requests( array $products, $essentials ) {
		$requests = array(
			'licenses' => array(
				'pagenow' => 'admin.php',
				'get'     => array( 'page' => 'cp-licenses' ),
			),
		);

		foreach ( $products as $product ) {
			$known = self::PRODUCTS[ $product ];

			if ( isset( $known['area'] ) ) {
				$requests[ "$product list" ] = array(
					'pagenow' => 'edit.php',
					'get'     => array( 'post_type' => $known['area'] ),
				);
				// The address the settings page had under the product's own menu.
				$requests[ "$product settings, old address" ] = array(
					'pagenow' => 'edit.php',
					'get'     => array(
						'post_type' => $known['area'],
						'page'      => $known['settings'],
					),
				);
			}

			if ( isset( $known['settings'] ) ) {
				$requests[ "$product settings" ] = array(
					'pagenow' => 'admin.php',
					'get'     => array( 'page' => $known['settings'] ),
				);
			}
		}

		if ( in_array( 'cp-library', $products, true ) ) {
			$requests['sermons settings sub-tab'] = array(
				'pagenow' => 'admin.php',
				'get'     => array( 'page' => 'cpl_item_options' ),
			);
			$requests['sermons tools']            = array(
				'pagenow' => 'edit.php',
				'get'     => array(
					'post_type' => 'cpl_item',
					'page'      => 'cp-library-tools',
				),
			);
			$requests['sermons license tab']      = array(
				'pagenow' => 'admin.php',
				'get'     => array( 'page' => 'cpl_license' ),
			);
		}

		if ( in_array( 'cp-live', $products, true ) ) {
			$requests['live settings, old address'] = array(
				'pagenow' => 'options-general.php',
				'get'     => array( 'page' => 'cp_live_main_options' ),
			);
		}

		if ( in_array( 'cp-sync', $products, true ) ) {
			$requests['sync'] = array(
				'pagenow' => 'admin.php',
				'get'     => array( 'page' => 'cps_settings' ),
			);
		}

		if ( $essentials ) {
			$requests['church info']         = array(
				'pagenow' => 'admin.php',
				'get'     => array( 'page' => 'cp-essentials' ),
			);
			$requests['essentials settings'] = array(
				'pagenow' => 'admin.php',
				'get'     => array( 'page' => 'cp-essentials-settings' ),
			);
		}

		return $requests;
	}

	/**
	 * @return array[] role, product set, Essentials mode.
	 */
	public function matrix() {
		$cases = array();

		foreach ( array_keys( self::ROLES ) as $role ) {
			foreach ( array_keys( self::SETS ) as $set ) {
				foreach ( self::ESSENTIALS as $essentials ) {
					$cases[ "$role, $set, essentials $essentials" ] = array( $role, $set, $essentials );
				}
			}
		}

		return $cases;
	}

	/**
	 * The entries the shared menu should hold for a user, in order, worked
	 * out here from the approved layout and the user's capabilities.
	 *
	 * @param string $role       Role.
	 * @param string $set        Product set.
	 * @param string $essentials Essentials mode.
	 * @return string[] Slugs.
	 */
	protected static function expected_entries( $role, $set, $essentials ) {
		$content = in_array( 'edit_posts', self::ROLES[ $role ], true );
		$options = in_array( 'manage_options', self::ROLES[ $role ], true );
		$slots   = array();
		$first   = null;

		foreach ( self::SETS[ $set ] as $product ) {
			$known = self::PRODUCTS[ $product ];

			if ( isset( $known['area'] ) && $content ) {
				$slots[ $known['slot'] ] = 'edit.php?post_type=' . $known['area'];
			}

			if ( isset( $known['settings'] ) && ( null === $first || $known['slot'] < self::PRODUCTS[ $first ]['slot'] ) ) {
				$first = $product;
			}

			if ( 'cp-sync' === $product && $options ) {
				$slots[90] = 'cps_settings';
			}
		}

		if ( 'none' !== $essentials ) {
			if ( $content ) {
				$slots[20] = 'edit.php?post_type=cp_event';
			}

			if ( $options ) {
				$slots[80]  = 'cp-essentials';
				$slots[100] = 'cp-essentials-settings';
			}
		} elseif ( $options && null !== $first ) {
			// Without a host, Settings opens the first product's settings.
			$slots[100] = 'admin.php?page=' . self::PRODUCTS[ $first ]['settings'];
		}

		if ( $options ) {
			$slots[110] = 'cp-licenses';
		}

		ksort( $slots );

		return array_values( $slots );
	}

	/**
	 * Presence, order and capability of the shared menu's entries.
	 *
	 * @dataProvider matrix
	 *
	 * @param string $role       Role.
	 * @param string $set        Product set.
	 * @param string $essentials Essentials mode.
	 */
	public function test_entries( $role, $set, $essentials ) {
		$result   = self::simulate( self::scenario( $role, $set, $essentials ) );
		$expected = self::expected_entries( $role, $set, $essentials );

		$this->assertArrayNotHasKey( 'dashboard_denied', $result );
		$this->assertSame( $expected, array_column( $result['shared'], 'slug' ) );

		foreach ( $result['shared'] as $entry ) {
			$this->assertTrue( $entry['can'], "{$entry['label']} ({$entry['slug']}) is in the menu of a user who cannot open it." );
		}

		// The top-level item is there exactly when it has an entry, and links
		// to an entry the user can open. (Core prints a top-level item for a
		// user who lacks its capability when one of its entries is open to
		// them, which is how the options-only user reaches Settings.)
		$this->assertSame( (bool) $expected, isset( $result['top']['church-plugins'] ) );

		if ( $expected ) {
			$this->assertSame( $expected[0], $result['top_link']['slug'] );
			$this->assertTrue( $result['top_link']['can'] );
		} else {
			$this->assertNull( $result['top_link'] );
		}

		// A product's own top-level menu is gone for a user who can open it.
		foreach ( self::SETS[ $set ] as $product ) {
			if ( isset( self::PRODUCTS[ $product ]['area'] ) && in_array( 'edit_posts', self::ROLES[ $role ], true ) ) {
				$this->assertArrayNotHasKey( 'edit.php?post_type=' . self::PRODUCTS[ $product ]['area'], $result['top'] );
			}
		}

		// Every area is planned for every user; only the ones the user can open move.
		$areas = array();

		foreach ( self::SETS[ $set ] as $product ) {
			if ( isset( self::PRODUCTS[ $product ]['area'] ) ) {
				$areas[] = $product;
			}
		}

		$this->assertSame( $areas, $result['planned'] );
		$this->assertSame( in_array( 'edit_posts', self::ROLES[ $role ], true ) ? $areas : array(), $result['areas'] );
	}

	/**
	 * Every screen opens for exactly the users who could open it before the
	 * shared menu, under the hook suffix it had.
	 *
	 * @dataProvider matrix
	 *
	 * @param string $role       Role.
	 * @param string $set        Product set.
	 * @param string $essentials Essentials mode.
	 */
	public function test_access_and_hook_suffixes( $role, $set, $essentials ) {
		$result  = self::simulate( self::scenario( $role, $set, $essentials ) );
		$content = in_array( 'edit_posts', self::ROLES[ $role ], true );
		$options = in_array( 'manage_options', self::ROLES[ $role ], true );
		$asked   = $result['requests'];

		$this->assertSame( $options, $asked['licenses']['access'], 'Licenses' );

		if ( $options ) {
			$this->assertSame( 'church-plugins_page_cp-licenses', $asked['licenses']['hook'] );
		}

		foreach ( self::SETS[ $set ] as $product ) {
			$known = self::PRODUCTS[ $product ];

			if ( isset( $known['area'] ) ) {
				$this->assertSame( $content, $asked[ "$product list" ]['access'], "$product list" );
				$this->assertSame( $options, $asked[ "$product settings, old address" ]['access'], "$product settings, old address" );
			}

			if ( isset( $known['settings'] ) ) {
				$this->assertSame( $options, $asked[ "$product settings" ]['access'], "$product settings" );

				if ( $options ) {
					$this->assertSame( $known['hook'], $asked[ "$product settings" ]['hook'], "$product settings" );
					$this->assertArrayNotHasKey( 'redirect', $asked[ "$product settings" ] );
				}
			}

			if ( isset( $known['area'], $known['settings'] ) && $options ) {
				$this->assertSame( $known['hook'], $asked[ "$product settings, old address" ]['hook'] );
			}
		}

		if ( isset( $asked['sermons tools'] ) ) {
			$this->assertSame( $options, $asked['sermons tools']['access'], 'Tools' );
			$this->assertSame( $options, $asked['sermons settings sub-tab']['access'], 'Settings sub-tab' );
			$this->assertSame( $options, $asked['sermons license tab']['access'], 'License tab' );

			if ( $options ) {
				$this->assertSame( 'cpl_item_page_cp-library-tools', $asked['sermons tools']['hook'] );
				$this->assertSame( 'admin_page_cpl_item_options', $asked['sermons settings sub-tab']['hook'] );
				$this->assertSame( 'http://sim/wp-admin/admin.php?page=cp-licenses#cpl_license', $asked['sermons license tab']['redirect'] );
			}
		}

		if ( isset( $asked['live settings, old address'] ) ) {
			$this->assertSame( $options, $asked['live settings, old address']['access'], 'CP Live under options-general.php' );

			if ( $options ) {
				$this->assertSame( 'http://sim/wp-admin/admin.php?page=cp_live_main_options', $asked['live settings, old address']['redirect'] );
			}
		}

		if ( isset( $asked['sync'] ) ) {
			$this->assertSame( $options, $asked['sync']['access'], 'Sync' );
			$this->assertSame( $options ? 'church-plugins_page_cps_settings' : false, $result['sync_hook'] );
		}

		if ( 'none' !== $essentials ) {
			$this->assertSame( $options, $asked['church info']['access'], 'Church Info' );
			$this->assertSame( $options, $asked['essentials settings']['access'], 'Essentials settings' );

			if ( $options ) {
				// `admin_page_…` here means the page found no parent and core refuses it.
				$this->assertSame( 'church-plugins_page_cp-essentials', $asked['church info']['hook'] );
				$this->assertSame( 'church-plugins_page_cp-essentials-settings', $asked['essentials settings']['hook'] );
			}
		}
	}

	/**
	 * The opt-out leaves core's menu as the products registered it.
	 *
	 * @dataProvider matrix
	 *
	 * @param string $role       Role.
	 * @param string $set        Product set.
	 * @param string $essentials Essentials mode.
	 */
	public function test_opt_out_moves_nothing( $role, $set, $essentials ) {
		if ( 'old9' === $essentials ) {
			// Covered by the other two hook orders; at 9 the pages depend on the parent's priority only.
			$this->addToAssertionCount( 1 );
			return;
		}

		$result  = self::simulate( array( 'optout' => true ) + self::scenario( $role, $set, $essentials ) );
		$content = in_array( 'edit_posts', self::ROLES[ $role ], true );
		$options = in_array( 'manage_options', self::ROLES[ $role ], true );

		$this->assertSame( array(), $result['areas'] );
		$this->assertSame( '', $result['settings_entry'] );
		$this->assertNotContains( 'cp-licenses', array_column( $result['shared'], 'slug' ) );

		foreach ( self::SETS[ $set ] as $product ) {
			$known = self::PRODUCTS[ $product ];

			if ( isset( $known['area'] ) ) {
				$this->assertSame( $content || $options, isset( $result['top'][ 'edit.php?post_type=' . $known['area'] ] ), "$product keeps its own menu." );
				$this->assertSame( $content, $result['requests'][ "$product list" ]['access'] );
				$this->assertSame( $options, $result['requests'][ "$product settings, old address" ]['access'] );
			}
		}

		if ( isset( $result['requests']['live settings, old address'] ) ) {
			$this->assertSame( $options, $result['requests']['live settings, old address']['access'] );
			$this->assertArrayNotHasKey( 'redirect', $result['requests']['live settings, old address'] );
		}

		if ( isset( $result['requests']['sermons license tab'] ) ) {
			$this->assertArrayNotHasKey( 'redirect', $result['requests']['sermons license tab'] );
		}
	}

	/**
	 * A post type with its own capabilities: a user with `edit_posts` but not
	 * that capability has no entry, so no top-level item either, also when
	 * other code put an entry the user cannot open straight into $submenu.
	 */
	public function test_parent_is_removed_when_no_entry_can_be_opened() {
		$scenario = array(
			'caps'      => self::ROLES['editor'],
			'products'  => array( 'cp-staff' ),
			'area_caps' => array( 'cp_staff' => 'edit_staff_members' ),
		);

		$result = self::simulate( $scenario );

		$this->assertSame( array( 'cp-staff' ), $result['planned'] );
		$this->assertSame( array(), $result['areas'] );
		$this->assertArrayNotHasKey( 'church-plugins', $result['top'] );

		$result = self::simulate( $scenario + array( 'direct_entries' => array( array( 'Reports', 'manage_options', 'cp-reports' ) ) ) );

		$this->assertArrayNotHasKey( 'church-plugins', $result['top'], 'The top-level item would link to a page this user cannot open.' );
		$this->assertSame( array(), $result['shared'] );

		// The same entry keeps the menu for a user who can open it.
		$result = self::simulate(
			array(
				'caps'           => self::ROLES['options-only'],
				'products'       => array( 'cp-staff' ),
				'area_caps'      => array( 'cp_staff' => 'edit_staff_members' ),
				'direct_entries' => array( array( 'Reports', 'manage_options', 'cp-reports' ) ),
			)
		);

		$this->assertArrayHasKey( 'church-plugins', $result['top'] );
		$this->assertContains( 'cp-reports', array_column( $result['shared'], 'slug' ) );
	}

	/**
	 * The Settings entry opens the first settings tab the user can open, not
	 * simply the first tab: a host can list a tab (`cp_admin_settings_tabs`)
	 * that needs a capability the user lacks.
	 */
	public function test_settings_entry_skips_tabs_the_user_cannot_open() {
		$scenario = array(
			'products'      => array( 'cp-library', 'cp-live' ),
			'settings_tabs' => array(
				'network' => array(
					'key'        => 'network',
					'label'      => 'Network',
					'path'       => 'admin.php?page=cp-network-settings',
					'capability' => 'manage_network_options',
					'pages'      => array( 'cp-network-settings' ),
					'position'   => 1,
				),
			),
		);

		$result = self::simulate( $scenario + array( 'caps' => self::ROLES['administrator'] ) );

		$this->assertSame( 'admin.php?page=cpl_main_options', $result['settings_entry'] );
		$this->assertSame(
			array( 'edit.php?post_type=cpl_item', 'admin.php?page=cpl_main_options', 'cp-licenses' ),
			array_column( $result['shared'], 'slug' )
		);
		$this->assertSame( array( true, true, true ), array_column( $result['shared'], 'can' ) );

		// A user who can open the host's tab gets it: it comes first.
		$result = self::simulate( $scenario + array( 'caps' => array_merge( self::ROLES['administrator'], array( 'manage_network_options' ) ) ) );

		$this->assertSame( 'admin.php?page=cp-network-settings', $result['settings_entry'] );

		// A user who can open no tab gets no Settings entry.
		$result = self::simulate( $scenario + array( 'caps' => self::ROLES['editor'] ) );

		$this->assertSame( '', $result['settings_entry'] );
		$this->assertSame( array( 'edit.php?post_type=cpl_item' ), array_column( $result['shared'], 'slug' ) );
	}

	/**
	 * A moved list screen opens the shared menu on its area's entry.
	 */
	public function test_moved_screen_highlights_its_area() {
		$result = self::simulate(
			array(
				'caps'     => self::ROLES['administrator'],
				'products' => self::SETS['all'],
				'requests' => array(
					'groups' => array(
						'pagenow' => 'edit.php',
						'get'     => array( 'post_type' => 'cp_group' ),
						'screen'  => array(
							'base'      => 'edit',
							'post_type' => 'cp_group',
							'taxonomy'  => '',
						),
					),
				),
			)
		);

		$this->assertSame( array( 'church-plugins', 'edit.php?post_type=cp_group' ), $result['requests']['groups']['highlight'] );
	}

	/**
	 * The old-address redirect encodes query keys as well as values.
	 */
	public function test_settings_redirect_encodes_keys_and_values() {
		$result = self::simulate(
			array(
				'caps'     => self::ROLES['administrator'],
				'products' => array( 'cp-live' ),
				'requests' => array(
					'old' => array(
						'pagenow' => 'options-general.php',
						'get'     => array(
							'page'    => 'cp_live_main_options',
							'a&b=c#d' => 'v w&x=y',
							'list'    => array( 'dropped' ),
						),
					),
				),
			)
		);

		$this->assertSame(
			'http://sim/wp-admin/admin.php?page=cp_live_main_options&a%26b%3Dc%23d=v%20w%26x%3Dy',
			$result['requests']['old']['redirect']
		);
	}

	/**
	 * On the Licenses screen every product's CMB2 box has the field ids
	 * `license` and `beta`: each row's ids are prefixed, names are not.
	 */
	public function test_license_rows_do_not_share_field_ids() {
		$result = self::simulate(
			array(
				'caps'     => self::ROLES['administrator'],
				'products' => self::SETS['all'],
				'cmb2'     => true,
				'render'   => array( 'licenses' ),
			)
		);

		$html = $result['licenses'];

		$this->assertSame( 1, preg_match_all( '/\sid="([^"]+)"/', $html, $ids ) > 0 ? 1 : 0 );
		$this->assertSame( array(), array_values( array_diff_assoc( $ids[1], array_unique( $ids[1] ) ) ), 'Ids used more than once.' );

		foreach ( array( 'cpl_license', 'cp_groups_license', 'cp_staff_license', 'cp_live_license' ) as $id ) {
			$this->assertStringContainsString( '<label for="' . $id . '-license">', $html );
			$this->assertStringContainsString( 'name="license" id="' . $id . '-license"', $html );
			$this->assertStringContainsString( 'name="beta" id="' . $id . '-beta"', $html );
		}

		// CP Sync has no CMB2 box: its row uses the REST form.
		$this->assertStringContainsString( 'data-cp-license="cps_license"', $html );

		// Nothing is printed for a user without manage_options.
		$result = self::simulate(
			array(
				'caps'     => self::ROLES['editor'],
				'products' => self::SETS['all'],
				'cmb2'     => true,
				'render'   => array( 'licenses' ),
			)
		);

		$this->assertSame( '', $result['licenses'] );
	}

	/**
	 * @return array[] scenario (merged over an administrator with CMB2 boxes), expected `existing`.
	 */
	public function install_states() {
		return array(
			'fresh install'                                => array(
				array( 'products' => self::SETS['all'] ),
				false,
			),
			'content in a moved post type'                 => array(
				array(
					'products' => array( 'cp-library' ),
					'posts'    => array( 'cpl_item' => 2 ),
				),
				true,
			),
			'content, first seen by a subscriber'          => array(
				array(
					'caps'     => self::ROLES['subscriber'],
					'products' => array( 'cp-library' ),
					'posts'    => array( 'cpl_item' => 2 ),
				),
				true,
			),
			'content in a secondary post type'             => array(
				array(
					'products' => array( 'cp-library' ),
					'posts'    => array( 'cpl_item_type' => 1 ),
				),
				true,
			),
			'saved settings'                               => array(
				array(
					'products' => array( 'cp-groups' ),
					'options'  => array( 'cp_groups_main_options' => array( 'x' => 1 ) ),
				),
				true,
			),
			'saved settings, first seen by an editor'      => array(
				array(
					'caps'     => self::ROLES['editor'],
					'products' => array( 'cp-groups' ),
					'options'  => array( 'cp_groups_main_options' => array( 'x' => 1 ) ),
				),
				true,
			),
			'saved settings of a product in WP Settings'   => array(
				array(
					'products' => array( 'cp-live' ),
					'options'  => array( 'cp_live_main_options' => array( 'x' => 1 ) ),
				),
				true,
			),
			'license key of a moved product'               => array(
				array(
					'products'     => array( 'cp-live', 'cp-sync' ),
					'license_keys' => array( 'cp_live_license' => 'k' ),
				),
				true,
			),
			'license key of a moved product, no CMB2 box'  => array(
				array(
					'cmb2'         => false,
					'products'     => array( 'cp-staff' ),
					'license_keys' => array( 'cp_staff_license' => 'k' ),
				),
				true,
			),
			// CP Sync was under this menu all along: nothing of it moved.
			'only CP Sync, with a saved key'               => array(
				array(
					'products'     => array( 'cp-sync' ),
					'license_keys' => array( 'cps_license' => 'k' ),
				),
				false,
			),
			'CP Sync key beside a moved product'           => array(
				array(
					'products'     => array( 'cp-library', 'cp-sync' ),
					'license_keys' => array( 'cps_license' => 'k' ),
				),
				false,
			),
			'settings left by a product no longer active'  => array(
				array(
					'products' => array( 'cp-sync' ),
					'options'  => array( 'cp_live_main_options' => array( 'x' => 1 ) ),
				),
				false,
			),
			'content, with CP Essentials active'           => array(
				array(
					'products'         => array( 'cp-library' ),
					'posts'            => array( 'cpl_item' => 2 ),
					'essentials'       => 'early',
					'essentials_types' => array( 'cp_event' => 'Events' ),
				),
				false,
			),
			'content, an AJAX request comes first'         => array(
				array(
					'products'   => array( 'cp-library' ),
					'posts'      => array( 'cpl_item' => 2 ),
					'ajax_first' => true,
				),
				true,
			),
			'network: first site to run the shared menu'   => array(
				array(
					'products'  => array( 'cp-staff' ),
					'options'   => array( 'cp_staff_staff_options' => array( 'x' => 1 ) ),
					'multisite' => array( 'registered' => '2021-01-01 00:00:00' ),
				),
				true,
			),
			// Also sets the network's date, which the assertion below checks.
			'network: first site, not in use'              => array(
				array(
					'products'  => array( 'cp-staff' ),
					'multisite' => array( 'registered' => '2021-01-01 00:00:00' ),
				),
				false,
			),
			'network: subsite older than the shared menu'  => array(
				array(
					'products'  => array( 'cp-staff' ),
					'options'   => array( 'cp_staff_staff_options' => array( 'x' => 1 ) ),
					'multisite' => array(
						'arrived'    => 1700000000,
						'registered' => '2021-01-01 00:00:00',
					),
				),
				true,
			),
			// Its settings option was written by the product's first-activation routine.
			'network: subsite created after the shared menu' => array(
				array(
					'products'  => array( 'cp-staff' ),
					'options'   => array( 'cp_staff_staff_options' => array( 'x' => 1 ) ),
					'multisite' => array(
						'arrived'    => 1700000000,
						'registered' => '2024-06-01 00:00:00',
					),
				),
				false,
			),
		);
	}

	/**
	 * Whether a site is recorded as one that used the old menus.
	 *
	 * @dataProvider install_states
	 *
	 * @param array $scenario Scenario.
	 * @param bool  $existing Expected state.
	 */
	public function test_existing_install_is_decided_from_what_moved( array $scenario, $existing ) {
		$result = self::simulate(
			$scenario + array(
				'caps'   => self::ROLES['administrator'],
				'cmb2'   => true,
				'render' => array( 'notice' ),
			)
		);

		$this->assertSame(
			array(
				'version'  => 'sim',
				'existing' => $existing,
			),
			$result['notice_state']
		);

		if ( ! $existing ) {
			$this->assertSame( '', $result['notice'] );
		}

		if ( isset( $scenario['multisite'] ) ) {
			$this->assertTrue( $result['network_since'], 'The network remembers when the shared menu arrived.' );
		}
	}

	/**
	 * A site recorded as fresh stays fresh when a product with content is
	 * added later (the CP Sync-only site that later installs CP Sermons).
	 */
	public function test_recorded_state_is_kept() {
		$result = self::simulate(
			array(
				'caps'     => self::ROLES['administrator'],
				'products' => array( 'cp-sync', 'cp-library' ),
				'posts'    => array( 'cpl_item' => 5 ),
				'options'  => array(
					'cp_admin_menu_layout' => array(
						'version'  => 'sim',
						'since'    => 1700000000,
						'existing' => false,
					),
				),
				'render'   => array( 'notice' ),
			)
		);

		$this->assertFalse( $result['notice_state']['existing'] );
		$this->assertSame( '', $result['notice'] );
	}

	/**
	 * The notice names Settings and Licenses only to a user who can open them.
	 */
	public function test_notice_names_only_what_the_user_can_open() {
		$scenario = array(
			'products' => array( 'cp-library', 'cp-groups' ),
			'posts'    => array( 'cpl_item' => 2 ),
			'cmb2'     => true,
			'render'   => array( 'notice' ),
		);

		$admin = self::simulate( $scenario + array( 'caps' => self::ROLES['administrator'] ) )['notice'];

		$this->assertStringContainsString( 'Messages and Groups are now under Church Plugins', $admin );
		$this->assertStringContainsString( 'Church Plugins → Settings, and license keys under Church Plugins → Licenses.', $admin );
		$this->assertStringContainsString( 'Your content, settings and saved links are unchanged.', $admin );
		$this->assertStringContainsString( 'href="http://sim/wp-admin/edit.php?post_type=cpl_item"', $admin );

		$editor = self::simulate( $scenario + array( 'caps' => self::ROLES['editor'] ) )['notice'];

		$this->assertStringContainsString( 'Messages and Groups are now under Church Plugins', $editor );
		$this->assertStringNotContainsString( 'Settings', $editor );
		$this->assertStringNotContainsString( 'Licenses', $editor );
		$this->assertStringNotContainsString( 'license', $editor );
		$this->assertStringContainsString( 'Your content and saved links are unchanged.', $editor );

		// Settings but no content screens: no area is named.
		$options = self::simulate( $scenario + array( 'caps' => self::ROLES['options-only'] ) )['notice'];

		$this->assertStringContainsString( 'Your Church Plugins screens are now under Church Plugins', $options );
		$this->assertStringContainsString( 'Church Plugins → Licenses.', $options );

		// A subscriber saw none of it.
		$this->assertSame( '', self::simulate( $scenario + array( 'caps' => self::ROLES['subscriber'] ) )['notice'] );
	}

	/**
	 * The tab bar is printed on a list screen and a classic editor, and not
	 * on the block editor, where it covered the pre-publish panel.
	 */
	public function test_tab_bar_is_skipped_on_the_block_editor() {
		$screen = array(
			'post_type' => 'cpl_item',
			'taxonomy'  => '',
		);

		$result = self::simulate(
			array(
				'caps'     => self::ROLES['administrator'],
				'products' => array( 'cp-library' ),
				'requests' => array(
					'list'    => array(
						'pagenow' => 'edit.php',
						'get'     => array( 'post_type' => 'cpl_item' ),
						'screen'  => array( 'base' => 'edit' ) + $screen,
						'tabs'    => true,
					),
					'classic' => array(
						'pagenow' => 'post.php',
						'get'     => array( 'post_type' => 'cpl_item' ),
						'screen'  => array(
							'base'         => 'post',
							'block_editor' => false,
						) + $screen,
						'tabs'    => true,
					),
					'block'   => array(
						'pagenow' => 'post-new.php',
						'get'     => array( 'post_type' => 'cpl_item' ),
						'screen'  => array(
							'base'         => 'post',
							'block_editor' => true,
						) + $screen,
						'tabs'    => true,
					),
				),
			)
		);

		foreach ( array( 'list', 'classic' ) as $name ) {
			$asked = $result['requests'][ $name ];

			$this->assertStringContainsString( 'class="nav-tab-wrapper cp-admin-tabs"', $asked['tabs'], $name );
			$this->assertStringContainsString( 'Series', $asked['tabs'], $name );
			$this->assertStringNotContainsString( 'cp-admin-tabs--editor', $asked['tabs'], $name );
			$this->assertStringNotContainsString( 'cp-has-editor-tabs', $asked['body_class'], $name );
			$this->assertSame( 'common', $asked['styles'][0][0], $name );
			$this->assertStringContainsString( '.cp-admin-tabs{', $asked['styles'][0][1], $name );
		}

		$block = $result['requests']['block'];

		$this->assertSame( '', $block['tabs'] );
		$this->assertSame( 'wp-admin', $block['body_class'] );
		$this->assertSame( array(), $block['styles'] );
	}
}
