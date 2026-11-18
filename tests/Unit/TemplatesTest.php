<?php
/**
 * Template handling: legacy deferral, the handoff decision, body classes and
 * taxonomy template labels.
 *
 * @package ChurchPlugins
 */

namespace ChurchPlugins\Tests;

use ChurchPlugins\Templates;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/stubs/templates.php';
require_once dirname( __DIR__, 2 ) . '/Templates.php';

/**
 * Stand-in for the main WP_Query: answers the conditionals Templates asks.
 */
class Templates_Query_Double {

	/**
	 * @var array
	 */
	public $is = array();

	/**
	 * @var array
	 */
	public $vars = array();

	/**
	 * @var object|null
	 */
	public $queried_object = null;

	/**
	 * @param array       $is             Conditional => post types / taxonomies it is true for.
	 * @param object|null $queried_object Queried object.
	 * @param array       $vars           Query vars.
	 */
	public function __construct( $is = array(), $queried_object = null, $vars = array() ) {
		$this->is             = $is;
		$this->queried_object = $queried_object;
		$this->vars           = $vars;
	}

	/**
	 * @param string $conditional Conditional name.
	 * @param mixed  $names       Post types or taxonomies asked about.
	 * @return bool
	 */
	protected function matches( $conditional, $names ) {
		return isset( $this->is[ $conditional ] ) && (bool) array_intersect( (array) $this->is[ $conditional ], (array) $names );
	}

	/**
	 * @param mixed $types Post types.
	 * @return bool
	 */
	public function is_singular( $types = '' ) {
		return $this->matches( 'singular', $types );
	}

	/**
	 * @param mixed $types Post types.
	 * @return bool
	 */
	public function is_post_type_archive( $types = '' ) {
		return $this->matches( 'archive', $types );
	}

	/**
	 * @param mixed $taxonomies Taxonomies.
	 * @return bool
	 */
	public function is_tax( $taxonomies = '' ) {
		return $this->matches( 'tax', $taxonomies );
	}

	/**
	 * @return bool
	 */
	public function is_search() {
		return ! empty( $this->is['search'] );
	}

	/**
	 * @param string $name Query var.
	 * @return mixed
	 */
	public function get( $name ) {
		return isset( $this->vars[ $name ] ) ? $this->vars[ $name ] : '';
	}

	/**
	 * @return object|null
	 */
	public function get_queried_object() {
		return $this->queried_object;
	}
}

/**
 * Templates subclass that skips hook registration and exposes the protected
 * methods under test.
 */
class Templates_Harness extends Templates {

	/**
	 * @var Templates_Query_Double|null
	 */
	public $query = null;

	/**
	 * @var bool
	 */
	public $block_templates = true;

	/**
	 * @var string|false
	 */
	public $default_template = '/plugins/cp-test/templates/default-template.php';

	/**
	 * Skip the parent constructor.
	 */
	public function __construct() {}

	/**
	 * @return string[]
	 */
	public function get_post_types() {
		return array( 'cp_group' );
	}

	/**
	 * @return string[]
	 */
	public function get_taxonomies() {
		return array( 'cp_group_type' );
	}

	/**
	 * @return string
	 */
	public function get_plugin_path() {
		return '/plugins/cp-test';
	}

	/**
	 * @return string
	 */
	public function get_plugin_id() {
		return 'cp-test';
	}

	/**
	 * @return Templates_Query_Double|null
	 */
	public function get_global_query_object() {
		return $this->query;
	}

	/**
	 * @return bool
	 */
	public function supports_block_templates() {
		return $this->block_templates;
	}

	/**
	 * @param string $template Template name.
	 * @param array  $args     Unused.
	 * @return string|false
	 */
	public function get_template_hierarchy( $template, $args = array() ) {
		return 'default-template' === $template ? $this->default_template : false;
	}

	/**
	 * @param string $template Template path.
	 * @return bool
	 */
	public function overrides( $template ) {
		return $this->resolved_block_template_overrides( $template );
	}

	/**
	 * @param object $taxonomy Taxonomy object.
	 * @return string[]
	 */
	public function taxonomy_labels( $taxonomy ) {
		return $this->get_taxonomy_template_labels( $taxonomy );
	}

	/**
	 * @param string|false $template Value for the shared static.
	 */
	public static function set_current_template( $template ) {
		self::$template = $template;
	}

	/**
	 * @return string|false
	 */
	public static function current_template() {
		return self::$template;
	}
}

/**
 * Overrides template_body_class() and reads self::$template itself, without
 * redeclaring the static: it reads the framework's copy.
 */
class Templates_Shared_Static_Harness extends Templates_Harness {

	/**
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function template_body_class( $classes ) {
		$classes = parent::template_body_class( $classes );

		$classes[] = 'default-template.php' === basename( (string) self::$template ) ? 'cpx-page-template' : 'cpx-other-template';

		return $classes;
	}
}

/**
 * CP Sermons' Templates class as shipped (cp-library includes/Templates.php):
 * it redeclares the static, so the override below reads a copy the framework
 * never writes.
 */
class Templates_Library_Harness extends Templates_Harness {

	/**
	 * Shadows \ChurchPlugins\Templates::$template, as in CP Sermons.
	 *
	 * @var string|false
	 */
	protected static $template = false;

	/**
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function template_body_class( $classes ) {
		$classes = parent::template_body_class( $classes );

		$template_filename = basename( self::$template );

		$classes[] = 'cpl-template';

		if ( 'default-template.php' == $template_filename ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- As shipped.
			$classes[] = 'cpl-page-template';
		} else {
			$classes[] = 'page-template-' . sanitize_title( $template_filename );
		}

		return $classes;
	}
}

/**
 * @covers \ChurchPlugins\Templates
 */
class TemplatesTest extends TestCase {

	const CANVAS = '/var/www/wp-includes/template-canvas.php';

	const VIEW_BLOCK = '<!-- wp:churchplugins/view /-->';

	/**
	 * Reset the stand-in WordPress state before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['cp_test_filters'] = array();
		$GLOBALS['cp_test_wp']      = array(
			'did_action' => array( 'wp' => 1 ),
		);

		unset( $GLOBALS['_wp_current_template_id'] );

		Templates_Harness::set_current_template( false );
	}

	/**
	 * Run the callbacks template_include() hooked to `body_class`, in the
	 * order it added them, as core would.
	 *
	 * @param array $classes Classes core starts with.
	 * @return array
	 */
	protected function printed_body_classes( $classes = array() ) {
		foreach ( $this->hooked( 'body_class' ) as $callback ) {
			$classes = call_user_func( $callback, $classes );
		}

		return $classes;
	}

	/**
	 * @param string $hook Hook name.
	 * @return array Callbacks added to the hook since setUp().
	 */
	protected function hooked( $hook ) {
		return isset( $GLOBALS['cp_test_wp']['added_filters'][ $hook ] ) ? $GLOBALS['cp_test_wp']['added_filters'][ $hook ] : array();
	}

	/**
	 * @param Templates_Harness $harness Instance template_include() ran on.
	 * @return string[] Its methods hooked to `body_class`, in order.
	 */
	protected function hooked_body_class_methods( $harness ) {
		$methods = array();

		foreach ( $this->hooked( 'body_class' ) as $callback ) {
			$this->assertSame( $harness, $callback[0] );
			$methods[] = $callback[1];
		}

		return $methods;
	}

	/**
	 * Clear the globals the tests set.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['_wp_current_template_id'], $GLOBALS['cp_test_wp'] );
		$GLOBALS['cp_test_filters'] = array();

		Templates_Harness::set_current_template( false );

		parent::tearDown();
	}

	/**
	 * @param string $slug   Template slug.
	 * @param string $source  theme, custom or plugin.
	 * @param string $content Template content.
	 * @return string The template id core would have recorded.
	 */
	protected function resolve_template( $slug, $source = 'theme', $content = '' ) {
		$id = 'theme//' . $slug;

		$GLOBALS['cp_test_wp']['block_templates'][ $id ] = (object) array(
			'id'      => $id,
			'slug'    => $slug,
			'source'  => $source,
			'content' => $content,
		);

		$GLOBALS['_wp_current_template_id'] = $id;

		return $id;
	}

	/**
	 * @param array $props Post properties.
	 * @return \WP_Post
	 */
	protected function post( $props = array() ) {
		$post            = new \WP_Post();
		$post->post_type = 'cp_group';
		$post->post_name = 'alpha';

		foreach ( $props as $key => $value ) {
			$post->$key = $value;
		}

		return $post;
	}

	/**
	 * @param array $is     Conditionals.
	 * @param mixed $object Queried object.
	 * @param array $vars   Query vars.
	 * @return Templates_Harness
	 */
	protected function harness( $is, $object = null, $vars = array() ) {
		$harness        = new Templates_Harness();
		$harness->query = new Templates_Query_Double( $is, $object, $vars );

		return $harness;
	}

	/**
	 * @return Templates_Harness A group archive request.
	 */
	protected function archive_harness() {
		return $this->harness( array( 'archive' => array( 'cp_group' ) ), null, array( 'post_type' => 'cp_group' ) );
	}

	/**
	 * A classic theme template is never deferred to, and the filter stays out of it.
	 */
	public function test_classic_template_never_defers() {
		$GLOBALS['cp_test_filters']['cp_template_block_template_overrides'] = static function () {
			return true;
		};
		$this->resolve_template( 'archive-cp_group' );

		$this->assertFalse( $this->archive_harness()->overrides( '/themes/astra/archive.php' ) );
	}

	/**
	 * The theme's generic templates do not qualify.
	 *
	 * @dataProvider generic_slugs
	 *
	 * @param string $slug Template slug.
	 */
	public function test_generic_theme_template_does_not_defer( $slug ) {
		$this->resolve_template( $slug );

		$this->assertFalse( $this->archive_harness()->overrides( self::CANVAS ) );
	}

	/**
	 * @return array
	 */
	public function generic_slugs() {
		return array(
			array( 'archive' ),
			array( 'index' ),
			array( 'search' ),
			array( 'archive-cp_staff' ),
		);
	}

	/**
	 * A theme file or customization for the post type's archive defers; the
	 * plugin's own registered template does not.
	 */
	public function test_archive_template_defers_by_source() {
		$this->resolve_template( 'archive-cp_group', 'theme' );
		$this->assertTrue( $this->archive_harness()->overrides( self::CANVAS ) );

		$this->resolve_template( 'archive-cp_group', 'custom' );
		$this->assertTrue( $this->archive_harness()->overrides( self::CANVAS ) );

		$this->resolve_template( 'archive-cp_group', 'plugin' );
		$this->assertFalse( $this->archive_harness()->overrides( self::CANVAS ) );
	}

	/**
	 * Singular: single-{type}, single-{type}-{name} and the template assigned
	 * to the post defer; the theme's generic single does not.
	 */
	public function test_singular_template_slugs() {
		$singular = array( 'singular' => array( 'cp_group' ) );

		$this->resolve_template( 'single-cp_group' );
		$this->assertTrue( $this->harness( $singular, $this->post() )->overrides( self::CANVAS ) );

		$this->resolve_template( 'single-cp_group-alpha' );
		$this->assertTrue( $this->harness( $singular, $this->post() )->overrides( self::CANVAS ) );

		$this->resolve_template( 'single' );
		$this->assertFalse( $this->harness( $singular, $this->post() )->overrides( self::CANVAS ) );

		// A custom template that is not the one assigned to the post never qualifies.
		$this->resolve_template( 'wide-layout', 'custom', self::VIEW_BLOCK );
		$this->assertFalse( $this->harness( $singular, $this->post() )->overrides( self::CANVAS ) );
	}

	/**
	 * The custom template assigned to the post defers only when its content
	 * has the view block; without it the plugin's view takes over, as on
	 * 1.1.21 (the block cannot be added outside the Site Editor).
	 *
	 * @dataProvider assigned_templates
	 *
	 * @param string $content  Template content.
	 * @param bool   $expected Whether the legacy path defers.
	 */
	public function test_assigned_template_defers_only_with_the_view_block( $content, $expected ) {
		$singular = array( 'singular' => array( 'cp_group' ) );
		$post     = $this->post( array( 'page_template' => 'wide-layout' ) );

		foreach ( array( 'theme', 'custom' ) as $source ) {
			$this->resolve_template( 'wide-layout', $source, $content );
			$this->assertSame( $expected, $this->harness( $singular, $post )->overrides( self::CANVAS ), $source );
		}

		// A classic theme's PHP page template is stored with its extension.
		$this->resolve_template( 'wide-layout', 'theme', $content );
		$this->assertSame( $expected, $this->harness( $singular, $this->post( array( 'page_template' => 'wide-layout.php' ) ) )->overrides( self::CANVAS ) );
	}

	/**
	 * @return array
	 */
	public function assigned_templates() {
		return array(
			'view block'                  => array( '<!-- wp:group --><div>' . self::VIEW_BLOCK . '</div><!-- /wp:group -->', true ),
			'view block with attributes'  => array( '<!-- wp:churchplugins/view {"plugin":"cp-test"} /-->', true ),
			'no view block'               => array( '<!-- wp:post-title /--><!-- wp:post-content /-->', false ),
			'no blocks at all'            => array( '<p>churchplugins/view</p>', false ),
			'empty content'               => array( '', false ),
			'another churchplugins block' => array( '<!-- wp:churchplugins/viewer /-->', false ),
			// Documented limit: a view block inside a referenced part or pattern is not seen.
			'view only in a template part' => array( '<!-- wp:template-part {"slug":"cp-view"} /-->', false ),
		);
	}

	/**
	 * The filter can still defer to an assigned template without the block,
	 * and can be asked on a classic theme (theme.json): it gets the template.
	 */
	public function test_filter_can_defer_to_an_assigned_template_without_the_block() {
		$this->resolve_template( 'wide-layout', 'theme', '<!-- wp:post-content /-->' );
		$seen = array();

		$GLOBALS['cp_test_filters']['cp_template_block_template_overrides'] = function ( $overrides, $block_template ) use ( &$seen ) {
			$seen = func_get_args();

			return true;
		};

		$harness = $this->harness( array( 'singular' => array( 'cp_group' ) ), $this->post( array( 'page_template' => 'wide-layout' ) ) );

		$this->assertTrue( $harness->overrides( self::CANVAS ) );
		$this->assertFalse( $seen[0] );
		$this->assertSame( 'wide-layout', $seen[1]->slug );
	}

	/**
	 * A recorded template that cannot be loaded (6.3+) does not defer and
	 * reaches the filter as null.
	 */
	public function test_unloadable_resolved_template_does_not_defer() {
		$GLOBALS['_wp_current_template_id'] = 'theme//gone';
		$seen                               = array( 'unset' );

		$GLOBALS['cp_test_filters']['cp_template_block_template_overrides'] = function ( $overrides, $block_template ) use ( &$seen ) {
			$seen = func_get_args();

			return $overrides;
		};

		$this->assertFalse( $this->archive_harness()->overrides( self::CANVAS ) );
		$this->assertFalse( $seen[0] );
		$this->assertNull( $seen[1] );
	}

	/**
	 * Taxonomy: taxonomy-{taxonomy} and taxonomy-{taxonomy}-{term} defer.
	 */
	public function test_taxonomy_template_slugs() {
		$term           = new \WP_Term();
		$term->taxonomy = 'cp_group_type';
		$term->slug     = 'men';
		$is             = array( 'tax' => array( 'cp_group_type' ) );

		$this->resolve_template( 'taxonomy-cp_group_type', 'custom' );
		$this->assertTrue( $this->harness( $is, $term )->overrides( self::CANVAS ) );

		$this->resolve_template( 'taxonomy-cp_group_type-men' );
		$this->assertTrue( $this->harness( $is, $term )->overrides( self::CANVAS ) );

		$this->resolve_template( 'archive-cp_group', 'custom' );
		$this->assertFalse( $this->harness( $is, $term )->overrides( self::CANVAS ) );
	}

	/**
	 * A request added only by `cp_template_is_query` (none of the plugin's own
	 * types) never defers.
	 */
	public function test_request_added_by_filter_does_not_defer() {
		$this->resolve_template( 'archive-tribe_events', 'custom' );

		$harness = $this->harness( array( 'archive' => array( 'tribe_events' ) ), null, array( 'post_type' => 'tribe_events' ) );

		$this->assertFalse( $harness->overrides( self::CANVAS ) );
	}

	/**
	 * `cp_template_block_template_overrides` can turn a deferral off and
	 * receives the resolved template.
	 */
	public function test_filter_can_opt_out_of_deferral() {
		$id   = $this->resolve_template( 'archive-cp_group' );
		$seen = array();

		$GLOBALS['cp_test_filters']['cp_template_block_template_overrides'] = function ( $overrides, $block_template, $templates, $template ) use ( &$seen ) {
			$seen = func_get_args();

			return false;
		};

		$harness = $this->archive_harness();

		$this->assertFalse( $harness->overrides( self::CANVAS ) );
		$this->assertTrue( $seen[0] );
		$this->assertSame( $id, $seen[1]->id );
		$this->assertSame( $harness, $seen[2] );
		$this->assertSame( self::CANVAS, $seen[3] );
	}

	/**
	 * WordPress 6.0 to 6.2 never sets $_wp_current_template_id: fall back to
	 * the slug guess used up to 1.1.21.
	 */
	public function test_unknown_resolved_template_falls_back_to_slug_guess() {
		$GLOBALS['cp_test_wp']['is_archive'] = true;
		$GLOBALS['cp_test_wp']['post_type']  = 'cp_group';

		$this->assertFalse( $this->archive_harness()->overrides( self::CANVAS ) );
		$this->assertSame( 'archive-cp_group', $GLOBALS['cp_test_wp']['get_posts_args'][0]['name'] );
		$this->assertSame( 'wp_template', $GLOBALS['cp_test_wp']['get_posts_args'][0]['post_type'] );

		$GLOBALS['cp_test_wp']['template_posts']['archive-cp_group'] = true;
		$this->assertTrue( $this->archive_harness()->overrides( self::CANVAS ) );

		$GLOBALS['cp_test_wp']['is_archive'] = false;
		$this->assertFalse( $this->archive_harness()->overrides( self::CANVAS ) );
		$this->assertSame( 'single-cp_group', end( $GLOBALS['cp_test_wp']['get_posts_args'] )['name'] );
	}

	/**
	 * A plain archive request for the plugin's post type is handed off.
	 */
	public function test_archive_request_uses_handoff() {
		$this->assertTrue( $this->archive_harness()->uses_block_template_handoff() );
	}

	/**
	 * A search scoped to the plugin's post type stays on the legacy path, and
	 * the legacy path does not defer to the theme's search template.
	 */
	public function test_search_request_does_not_use_handoff() {
		$harness = $this->harness(
			array(
				'archive' => array( 'cp_group' ),
				'search'  => true,
			),
			null,
			array( 'post_type' => 'cp_group' )
		);

		$this->assertTrue( $harness->is_cp_query() );
		$this->assertFalse( $harness->uses_block_template_handoff() );

		$this->resolve_template( 'search' );
		$this->assertFalse( $harness->overrides( self::CANVAS ) );
	}

	/**
	 * An answer asked for before the main query ran is not kept.
	 */
	public function test_handoff_is_not_memoized_before_wp() {
		$GLOBALS['cp_test_wp']['did_action'] = array();

		$harness        = new Templates_Harness();
		$harness->query = new Templates_Query_Double();

		$this->assertFalse( $harness->uses_block_template_handoff() );

		$harness->query                      = new Templates_Query_Double( array( 'archive' => array( 'cp_group' ) ) );
		$GLOBALS['cp_test_wp']['did_action'] = array( 'wp' => 1 );

		$this->assertTrue( $harness->uses_block_template_handoff() );

		// Once decided after `wp`, the answer holds for the request.
		$harness->query = new Templates_Query_Double();
		$this->assertTrue( $harness->uses_block_template_handoff() );
	}

	/**
	 * A request that is not the plugin's is left alone: nothing hooked.
	 */
	public function test_template_include_ignores_other_requests() {
		$harness = $this->harness( array() );

		$this->assertSame( '/themes/test/page.php', $harness->template_include( '/themes/test/page.php' ) );
		$this->assertSame( array(), $this->hooked( 'body_class' ) );
	}

	/**
	 * @return array[] Harness class and the body classes it prints when the
	 *                 view is the default template.
	 */
	public function body_class_subclasses() {
		return array(
			'framework only'             => array( Templates_Harness::class, array( 'cp-template', 'cp-page-template' ) ),
			'override, framework static' => array( Templates_Shared_Static_Harness::class, array( 'cp-template', 'cp-page-template', 'cpx-page-template' ) ),
			// CP Sermons as shipped: no cpl-page-template, and no bare page-template-.
			'override, own static (CPL)' => array( Templates_Library_Harness::class, array( 'cp-template', 'cp-page-template', 'cpl-template' ) ),
		);
	}

	/**
	 * Handoff: the resolved template stays, the default template is recorded
	 * for the view block, and the body classes come from
	 * default_template_body_class().
	 *
	 * @dataProvider body_class_subclasses
	 *
	 * @param string   $class    Harness class.
	 * @param string[] $expected Classes printed.
	 */
	public function test_template_include_on_the_handoff_path( $class, $expected ) {
		$harness        = new $class();
		$harness->query = new Templates_Query_Double( array( 'archive' => array( 'cp_group' ) ), null, array( 'post_type' => 'cp_group' ) );

		$GLOBALS['cp_test_wp']['options'] = array(
			'stylesheet' => 'twentytwentyfive',
			'template'   => 'twentytwentyfive',
		);

		$this->assertSame( self::CANVAS, $harness->template_include( self::CANVAS ) );
		$this->assertSame( $harness->default_template, Templates_Harness::current_template() );
		$this->assertSame( array( 'theme_body_class', 'default_template_body_class' ), $this->hooked_body_class_methods( $harness ) );
		$this->assertSame(
			array_merge( array( 'archive', 'cp-theme-twentytwentyfive' ), $expected ),
			$this->printed_body_classes( array( 'archive' ) )
		);
	}

	/**
	 * Legacy path deferring to a theme / customized block template: the
	 * resolved template stays, self::$template stays unset (the shortcode is
	 * inert), and the body classes come from default_template_body_class().
	 *
	 * @dataProvider body_class_subclasses
	 *
	 * @param string   $class    Harness class.
	 * @param string[] $expected Classes printed.
	 */
	public function test_template_include_on_the_deferral_path( $class, $expected ) {
		$harness                  = new $class();
		$harness->query           = new Templates_Query_Double( array( 'archive' => array( 'cp_group' ) ), null, array( 'post_type' => 'cp_group' ) );
		$harness->block_templates = false;

		$this->resolve_template( 'archive-cp_group', 'custom' );

		$this->assertSame( self::CANVAS, $harness->template_include( self::CANVAS ) );
		$this->assertFalse( Templates_Harness::current_template() );
		$this->assertSame( array( 'theme_body_class', 'default_template_body_class' ), $this->hooked_body_class_methods( $harness ) );
		$this->assertSame( array(), $this->hooked( 'loop_start' ) );

		$classes = $this->printed_body_classes( array( 'archive' ) );

		$this->assertSame( array_merge( array( 'archive', 'cp-theme-' ), $expected ), $classes );
		$this->assertFalse( Templates_Harness::current_template(), 'The static is restored after the classes are built.' );
	}

	/**
	 * Legacy takeover (nothing to defer to): the plugin's default template
	 * replaces the resolved one and template_body_class() is hooked, followed
	 * by the filter that drops a bare `page-template-`.
	 *
	 * @dataProvider body_class_subclasses
	 *
	 * @param string   $class    Harness class.
	 * @param string[] $expected Classes printed.
	 */
	public function test_template_include_on_the_legacy_takeover_path( $class, $expected ) {
		$harness                  = new $class();
		$harness->query           = new Templates_Query_Double( array( 'archive' => array( 'cp_group' ) ), null, array( 'post_type' => 'cp_group' ) );
		$harness->block_templates = false;

		// The theme's generic archive template: not one to defer to.
		$this->resolve_template( 'archive' );

		$this->assertSame( $harness->default_template, $harness->template_include( self::CANVAS ) );
		$this->assertSame( $harness->default_template, Templates_Harness::current_template() );
		$this->assertSame(
			array( 'theme_body_class', 'template_body_class', 'strip_empty_template_class' ),
			$this->hooked_body_class_methods( $harness )
		);
		$this->assertSame( array_merge( array( 'archive', 'cp-theme-' ), $expected ), $this->printed_body_classes( array( 'archive' ) ) );
	}

	/**
	 * A classic theme (PHP template) always takes the legacy takeover path.
	 */
	public function test_template_include_replaces_a_classic_template() {
		$harness                  = $this->archive_harness();
		$harness->block_templates = false;

		$this->resolve_template( 'archive-cp_group', 'custom' );

		$this->assertSame( $harness->default_template, $harness->template_include( '/themes/astra/archive.php' ) );
		$this->assertContains( 'template_body_class', $this->hooked_body_class_methods( $harness ) );
	}

	/**
	 * What CP Sermons' override returns by itself: the bare `page-template-`
	 * is there until strip_empty_template_class() runs, and
	 * `cpl-page-template` is absent whatever the framework's static holds.
	 */
	public function test_library_override_never_sees_the_frameworks_template() {
		$library = new Templates_Library_Harness();

		Templates_Harness::set_current_template( $library->default_template );

		$classes = $library->template_body_class( array() );

		$this->assertSame( array( 'cp-template', 'cp-page-template', 'cpl-template', 'page-template-' ), $classes );
		$this->assertSame( array( 'cp-template', 'cp-page-template', 'cpl-template' ), $library->strip_empty_template_class( $classes ) );
	}

	/**
	 * strip_empty_template_class() removes only the bare class and reindexes.
	 */
	public function test_strip_empty_template_class_keeps_named_classes() {
		$this->assertSame(
			array( 'page-template', 'page-template-wide-php', 'x' ),
			( new Templates_Harness() )->strip_empty_template_class( array( 'page-template', 'page-template-', 'page-template-wide-php', 'x' ) )
		);
	}

	/**
	 * On the deferral path self::$template is unset: an override that reads
	 * the framework's static sees the default template for the call, and the
	 * static is restored afterwards.
	 */
	public function test_deferred_body_class_serves_overrides_that_read_the_template() {
		$harness = new Templates_Shared_Static_Harness();

		$classes = $harness->default_template_body_class( array( 'archive' ) );

		$this->assertSame( array( 'archive', 'cp-template', 'cp-page-template', 'cpx-page-template' ), $classes );
		$this->assertFalse( Templates_Harness::current_template() );
	}

	/**
	 * A template already chosen (the handoff sets it) is left as it is.
	 */
	public function test_deferred_body_class_keeps_a_template_already_set() {
		Templates_Harness::set_current_template( '/themes/x/page-wide.php' );

		$classes = ( new Templates_Shared_Static_Harness() )->default_template_body_class( array() );

		$this->assertSame( array( 'cp-template', 'page-template-page-wide-php', 'cpx-other-template' ), $classes );
		$this->assertSame( '/themes/x/page-wide.php', Templates_Harness::current_template() );
	}

	/**
	 * A plugin with no default template gets no empty `page-template-` class,
	 * from the framework or from an override.
	 */
	public function test_no_default_template_emits_no_empty_class() {
		$base                   = new Templates_Harness();
		$base->default_template = false;

		$this->assertSame( array( 'cp-template' ), $base->template_body_class( array() ) );

		$library                   = new Templates_Library_Harness();
		$library->default_template = false;

		$this->assertSame( array( 'cp-template', 'cpl-template' ), $library->default_template_body_class( array() ) );
		$this->assertFalse( Templates_Harness::current_template() );
	}

	/**
	 * @param string   $singular    Taxonomy singular label.
	 * @param string[] $object_type Post types the taxonomy is attached to.
	 * @return object
	 */
	protected function taxonomy( $singular, $object_type ) {
		return (object) array(
			'labels'      => (object) array( 'singular_name' => $singular ),
			'object_type' => $object_type,
		);
	}

	/**
	 * @param string $post_type Post type name.
	 * @param string $singular  Singular label.
	 * @param string $plural    Plural label.
	 */
	protected function register_post_type( $post_type, $singular, $plural ) {
		$GLOBALS['cp_test_wp']['post_type_objects'][ $post_type ] = (object) array(
			'name'   => $post_type,
			'label'  => $plural,
			'labels' => (object) array(
				'singular_name' => $singular,
				'name'          => $plural,
			),
		);
	}

	/**
	 * A generic taxonomy label gets the owning post type's name in front.
	 */
	public function test_taxonomy_labels_prepend_the_owner() {
		$this->register_post_type( 'cp_group', 'Group', 'Groups' );

		$labels = ( new Templates_Harness() )->taxonomy_labels( $this->taxonomy( 'Type', array( 'cp_group' ) ) );

		$this->assertSame( 'Group Type Archive', $labels[0] );
		$this->assertSame( 'Controls the page that lists the Groups in one Type.', $labels[1] );
	}

	/**
	 * A label that already names the post type is not doubled.
	 */
	public function test_taxonomy_labels_do_not_repeat_the_owner() {
		$this->register_post_type( 'cp_group', 'Group', 'Groups' );

		$labels = ( new Templates_Harness() )->taxonomy_labels( $this->taxonomy( 'Group Type', array( 'cp_group' ) ) );

		$this->assertSame( 'Group Type Archive', $labels[0] );
	}

	/**
	 * A taxonomy shared by several post types, none of them the plugin's,
	 * names them all in the description.
	 */
	public function test_taxonomy_labels_for_several_post_types() {
		$this->register_post_type( 'cpl_item', 'Sermon', 'Sermons' );
		$this->register_post_type( 'cpl_item_type', 'Series', 'Series' );

		$labels = ( new Templates_Harness() )->taxonomy_labels( $this->taxonomy( 'Topic', array( 'cpl_item', 'cpl_item_type' ) ) );

		$this->assertSame( 'Topic Archive', $labels[0] );
		$this->assertSame( 'Controls the page that lists the Sermons and Series in one Topic.', $labels[1] );
	}

	/**
	 * A taxonomy attached to no known post type falls back to "items".
	 */
	public function test_taxonomy_labels_without_post_types() {
		$labels = ( new Templates_Harness() )->taxonomy_labels( $this->taxonomy( 'Topic', array( 'gone' ) ) );

		$this->assertSame( 'Controls the page that lists the items in one Topic.', $labels[1] );
	}
}
