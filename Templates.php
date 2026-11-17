<?php
/**
 * Templating functionality
 */

namespace ChurchPlugins;

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}


/**
 * Handle views and template files.
 */
abstract class Templates {

	/**
	 * Framework version every active plugin must bundle for the block template
	 * handoff to be on by default.
	 *
	 * @since 1.1.21
	 */
	const BLOCK_TEMPLATES_VERSION = '1.1.21';

	/**
	 * @var self
	 */
	protected static $_instance;

	/**
	 * @var bool Is wp_head complete?
	 */
	public static $wpHeadComplete = false;

	/**
	 * @var bool Is this the main loop?
	 */
	public static $isMainLoop = false;

	/**
	 * The template name currently being used
	 */
	protected static $template = false;

	/**
	 * @var bool Did the shortcode run?
	 */
	protected static $did_shortcode = false;

	/*
	 * List of templates which have compatibility fixes
	 */
	public static $themes_with_compatibility_fixes = [];

	/**
	 * Every constructed Templates instance, keyed by class name.
	 *
	 * @since 1.1.21
	 * @var self[]
	 */
	protected static $instances = [];

	/**
	 * Whether the current request is handed off to the block template hierarchy.
	 * Null until decided.
	 *
	 * @since 1.1.21
	 * @var bool|null
	 */
	protected $block_template_handoff = null;

	/**
	 * The plugin's human-readable name. Null until read.
	 *
	 * @since 1.1.21
	 * @var string|null
	 */
	protected $plugin_name = null;

	/**
	 * Only make one instance of PostType
	 *
	 * @return self
	 */
	public static function get_instance() {
		$class = get_called_class();

		if ( ! self::$_instance instanceof $class ) {
			self::$_instance = new $class();
		}

		return self::$_instance;
	}

	/**
	 * Initialize the Template Yumminess!
	 */
	protected function __construct() {
		self::$instances[ get_called_class() ] = $this;

		// Choose the wordpress theme template to use
		add_filter( 'template_include', [ $this, 'template_include' ] );

		// Block themes: let the template hierarchy pick our templates.
		add_action( 'init', [ $this, 'register_block_templates' ], 99 );
		add_filter( 'get_block_templates', [ $this, 'filter_block_templates' ], 10, 3 );

		// don't query the database for the spoofed post
		wp_cache_set( $this->spoofed_post()->ID, $this->spoofed_post(), 'posts' );
		wp_cache_set( $this->spoofed_post()->ID, [ true ], 'post_meta' );

		add_action( 'wp_head', __CLASS__ . '::wpHeadFinished', 999 );

		// add the theme name to the body class when needed
		if ( $this->needs_compatibility_fix() ) {
			add_filter( 'body_class', __CLASS__ . '::theme_body_class' );
		}

		add_shortcode( 'cp-template', [ $this, 'template_shortcode' ] );
		add_action( 'cp_do_header', __CLASS__ . '::do_header' );
		add_action( 'cp_do_footer', __CLASS__ . '::do_footer' );

		// pagination
		add_action( 'cp_after_archive', 'the_posts_pagination' );
	}

	public static function do_header() {
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			echo apply_filters( 'cp_template_block_header','<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->' );;
		} else {
			get_header();
		}
	}

	public static function do_footer() {
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			echo apply_filters( 'cp_template_block_footer','<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->' );
		} else {
			get_footer();
		}
	}

	/**
	 * @return \WP_Query|null
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_global_query_object() {
		global $wp_query;
		global $wp_the_query;

		if ( ! empty( $wp_query ) ) {
			return $wp_query;
		}

		if ( ! empty( $wp_the_query ) ) {
			return $wp_the_query;
		}

		return null;
	}

	/**
	 * Return the post types for this plugin
	 *
	 * @since  1.0.11
	 *
	 * @return mixed
	 * @author Tanner Moushey, 5/11/23
	 */
	abstract function get_post_types();

	/**
	 * Return the taxonomies for this plugin
	 *
	 * @since  1.0.11
	 *
	 * @return mixed
	 * @author Tanner Moushey, 5/11/23
	 */
	abstract function get_taxonomies();

	/**
	 * Return the plugin path for the current plugin
	 *
	 * @since  1.0.11
	 *
	 * @return mixed
	 * @author Tanner Moushey, 5/11/23
	 */
	abstract function get_plugin_path();

	/**
	 * Get the slug / id for the current plugin
	 *
	 * @since  1.0.11
	 *
	 * @return mixed
	 * @author Tanner Moushey, 5/11/23
	 */
	abstract function get_plugin_id();

	/**
	 * Check if the main query is for a cpl item
	 *
	 * @return bool|mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function is_cp_query() {
		if ( ! $this->get_global_query_object() ) {
			return false;
		}

		return apply_filters( 'cp_template_is_query', $this->matches_query(), $this );
	}

	/**
	 * Whether the main query is for one of this plugin's post types or
	 * taxonomies, before the `cp_template_is_query` filter.
	 *
	 * A plugin that registers its types only in some cases returns empty
	 * lists otherwise. WP_Query reads an empty list as "any", so empty lists
	 * are skipped here instead of passed on.
	 *
	 * @since 1.1.21
	 *
	 * @return bool
	 */
	public function matches_query() {
		$wp_query = $this->get_global_query_object();

		if ( ! $wp_query ) {
			return false;
		}

		$types      = (array) $this->get_post_types();
		$taxonomies = (array) $this->get_taxonomies();

		return ( $types && ( $wp_query->is_singular( $types ) || $wp_query->is_post_type_archive( $types ) ) )
			|| ( $taxonomies && $wp_query->is_tax( $taxonomies ) );
	}

	/**
	 * Get the type of the current post
	 *
	 * @param $type
	 *
	 * @return array|false|mixed|string|string[]
	 * @since  1.1.11
	 *
	 * @author Tanner Moushey, 3/1/25
	 */
	public static function get_type( $type = false ) {
		if ( ! $type ) {
			$type = get_post_type();
		}

		return str_replace( [ 'cp_', '_' ], [ '', '-' ], $type );
	}

	/**
	 * Pick the correct template to include
	 *
	 * @param string $template Path to template
	 *
	 * @return string Path to template
	 */
	public function template_include( $template ) {
		global $_wp_current_template_content;

		$original_template = $template;
		do_action( 'cp_template_chooser', $template, $this );

		if ( ! $this->is_cp_query() ) {
			return $template;
		}

		// Core has already resolved one of our block templates (or a theme / user override of it).
		if ( $this->uses_block_template_handoff() ) {
			// The view block renders the default template's shell; keep its body classes.
			self::$template = $this->get_template_hierarchy( 'default-template' );
			add_filter( 'body_class', [ $this, 'theme_body_class' ] );
			add_filter( 'body_class', [ $this, 'template_body_class' ] );

			return $template;
		}

		// The theme or a Site Editor customization supplies a block template for this request; use it.
		if ( $this->resolved_block_template_overrides( $template ) ) {
			add_filter( 'body_class', [ $this, 'theme_body_class' ] );
			add_filter( 'body_class', [ $this, 'template_body_class' ] );

			return $template;
		}

		// if it's a single 404
		if ( is_single() && is_404() ) {
			return get_404_template();
		}

		// add the theme slug to the body class
		add_filter( 'body_class', [ $this, 'theme_body_class' ] );

		// add the template name to the body class
		add_filter( 'body_class', [ $this, 'template_body_class' ] );

		// user has selected a page/custom page template
		if ( $default_template = apply_filters( 'cp_default_template', false, $this ) ) {
			if ( ! is_single() || ! post_password_required() ) {
				add_action( 'loop_start', [ $this, 'setup_cp_template' ] );
			}

			$template = $default_template !== 'default'
				? locate_template( $default_template )
				: get_page_template();

			if ( $template == '' ) {
				$template = get_index_template();
			}

		} else {
			$template = $this->get_template_hierarchy( 'default-template' );
		}

		self::$template = $template;

		// check if post content has 'cp-template' shortcode
		if ( apply_filters( 'cp_disable_template', has_shortcode( get_the_content(), 'cp-template' ), $template, $this ) ) {
			return $original_template;
		}

		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {

			ob_start(); // Start output buffering
			include( $template ); // Load the original template
			$content = ob_get_clean(); // Capture the template content and stop buffering

			$_wp_current_template_content = $content;

			return $original_template;
		} else {
			return $template;
		}
	}

	/**
	 * Whether the block template core resolved for this request is one the
	 * theme or a Site Editor customization provides specifically for this
	 * plugin's content, so the legacy path should leave it in place.
	 *
	 * Used only on the legacy path (the block template handoff is off). The
	 * decision is made from the template core actually resolved, not from a
	 * guessed slug: a block theme's generic archive, single or index template
	 * never qualifies, and neither does a customization saved for another
	 * theme. A qualifying template is kept whether or not it contains the view
	 * block, since a customer may have replaced the view with core blocks.
	 *
	 * Qualifying slugs, for a request this plugin matches natively:
	 * single-{post_type}, single-{post_type}-{post_name}, the custom template
	 * assigned to the post, archive-{post_type}, taxonomy-{taxonomy} and
	 * taxonomy-{taxonomy}-{term}.
	 *
	 * @since 1.1.22
	 *
	 * @param string $template Path of the template core chose.
	 * @return bool
	 */
	protected function resolved_block_template_overrides( $template ) {
		global $_wp_current_template_id;

		if ( ! str_ends_with( $template, '/wp-includes/template-canvas.php' ) || empty( $_wp_current_template_id ) ) {
			return false;
		}

		$block_template = get_block_template( $_wp_current_template_id );

		if ( ! $block_template || ! in_array( $block_template->source, [ 'custom', 'theme' ], true ) ) {
			return false;
		}

		$slug     = $block_template->slug;
		$wp_query = $this->get_global_query_object();
		$object   = $wp_query->get_queried_object();

		$types      = (array) $this->get_post_types();
		$taxonomies = (array) $this->get_taxonomies();

		if ( $types && $wp_query->is_singular( $types ) && $object instanceof \WP_Post ) {
			$assigned = get_page_template_slug( $object );

			return 'single-' . $object->post_type === $slug
				|| 0 === strpos( $slug, 'single-' . $object->post_type . '-' )
				|| ( $assigned && preg_replace( '/\.php$/', '', $assigned ) === $slug );
		}

		if ( $types && $wp_query->is_post_type_archive( $types ) ) {
			foreach ( array_filter( (array) $wp_query->get( 'post_type' ) ) as $post_type ) {
				if ( 'archive-' . $post_type === $slug ) {
					return true;
				}
			}

			return false;
		}

		if ( $taxonomies && $wp_query->is_tax( $taxonomies ) && $object instanceof \WP_Term ) {
			return 'taxonomy-' . $object->taxonomy === $slug
				|| 0 === strpos( $slug, 'taxonomy-' . $object->taxonomy . '-' );
		}

		return false;
	}

	/**
	 * Get the plugin's human-readable name, as shown to site admins in the
	 * view block's dropdown and the Site Editor's template titles.
	 *
	 * Defaults to the Plugin Name header of {plugin-path}/{plugin-id}.php,
	 * which is how ChurchPlugins plugins name their main file, or the id. A
	 * plugin whose product name differs from its header overrides this method
	 * in its Templates subclass (returning a translated string), or anyone can
	 * use the `cp_template_plugin_name` filter. Template titles are registered
	 * on `init` (priority 99), so either must be in place before then.
	 *
	 * @since 1.1.21
	 * @since 1.1.22 Added the `cp_template_plugin_name` filter.
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		if ( null === $this->plugin_name ) {
			$file = trailingslashit( $this->get_plugin_path() ) . $this->get_plugin_id() . '.php';
			$data = is_readable( $file ) ? get_file_data( $file, [ 'name' => 'Plugin Name' ] ) : [];

			$this->plugin_name = ! empty( $data['name'] ) ? $data['name'] : $this->get_plugin_id();
		}

		/**
		 * Filters the plugin name shown to site admins for a ChurchPlugins plugin.
		 *
		 * @since 1.1.22
		 *
		 * @param string    $name      Plugin name; the plugin header's by default.
		 * @param Templates $templates The plugin's Templates instance.
		 */
		return (string) apply_filters( 'cp_template_plugin_name', $this->plugin_name, $this );
	}

	/**
	 * Get every constructed Templates instance.
	 *
	 * @since 1.1.21
	 *
	 * @return self[]
	 */
	public static function get_registered_instances() {
		return array_values( self::$instances );
	}

	/**
	 * Get the Templates instance for a plugin.
	 *
	 * @since 1.1.21
	 *
	 * @param string $plugin_id Plugin id, as returned by get_plugin_id().
	 * @return self|null
	 */
	public static function get_plugin_instance( $plugin_id ) {
		foreach ( self::$instances as $instance ) {
			if ( $plugin_id && $instance->get_plugin_id() === $plugin_id ) {
				return $instance;
			}
		}

		return null;
	}

	/**
	 * Lowest ChurchPlugins framework version bundled by the active plugins.
	 *
	 * Each active plugin loads its bundled copy's versioned loader class, so
	 * the declared ChurchPlugins_* classes cover every copy that could serve
	 * the site if the plugins carrying newer copies were deactivated.
	 *
	 * @since 1.1.21
	 *
	 * @return string
	 */
	public static function get_lowest_framework_version() {
		static $lowest = null;

		if ( null !== $lowest ) {
			return $lowest;
		}

		$lowest = CHURCHPLUGINS_VERSION;

		foreach ( get_declared_classes() as $class ) {
			if ( 0 !== strpos( $class, 'ChurchPlugins_' ) || ! defined( $class . '::VERSION' ) ) {
				continue;
			}

			$version = (string) constant( $class . '::VERSION' );

			if ( version_compare( $version, $lowest, '<' ) ) {
				$lowest = $version;
			}
		}

		return $lowest;
	}

	/**
	 * Whether this plugin's pages are rendered through block templates.
	 *
	 * On by default on block themes (WordPress 6.7+) only when every active
	 * ChurchPlugins plugin bundles a framework that supports the handoff. A
	 * template customized in the Site Editor stores the churchplugins/view
	 * block, which older framework copies do not register; if such a copy
	 * took over the site, the customized template would render empty.
	 *
	 * Also off when the theme overrides the plugin's default-template.php: an
	 * override was written to replace the whole block template, and may print
	 * block markup that only the legacy path parses.
	 *
	 * @since 1.1.21
	 *
	 * @return bool
	 */
	public function supports_block_templates() {
		if ( ! function_exists( 'register_block_template' ) || ! wp_is_block_theme() ) {
			return false;
		}

		$enabled = version_compare( self::get_lowest_framework_version(), self::BLOCK_TEMPLATES_VERSION, '>=' )
			&& ! locate_template( $this->get_plugin_id() . '/default-template.php' );

		/**
		 * Whether to render this plugin's pages through the block template hierarchy.
		 *
		 * Return false to keep the legacy behavior on block themes: no registered
		 * block templates, and the plugin's default-template.php replaces the
		 * resolved block template. Return true to opt in while an active plugin
		 * still bundles an older framework, accepting that templates customized
		 * in the Site Editor render empty if that older copy ever serves the site.
		 *
		 * @since 1.1.21
		 *
		 * @param bool      $enabled   Whether the handoff is enabled.
		 * @param Templates $templates The plugin's Templates instance.
		 */
		return (bool) apply_filters( 'cp_block_template_handoff', $enabled, $this );
	}

	/**
	 * Register block templates for this plugin's post types and taxonomies.
	 *
	 * Registers {plugin-id}//single-{post_type}, {plugin-id}//archive-{post_type}
	 * and {plugin-id}//taxonomy-{taxonomy}. A theme file or a Site Editor
	 * customization with the same slug takes precedence in core.
	 *
	 * @since 1.1.21
	 *
	 * @return void
	 */
	public function register_block_templates() {
		if ( ! $this->supports_block_templates() ) {
			return;
		}

		$templates = [];

		foreach ( (array) $this->get_post_types() as $post_type ) {
			$object = get_post_type_object( $post_type );

			if ( ! $object || ! is_post_type_viewable( $object ) ) {
				continue;
			}

			$templates[ 'single-' . $post_type ] = [
				/* translators: %s: Post type singular name, e.g. "Sermon". */
				sprintf( __( 'Single %s', 'churchplugins' ), $object->labels->singular_name ),
				/* translators: %s: Post type singular name, e.g. "Sermon". */
				sprintf( __( 'Controls the page for each %s.', 'churchplugins' ), $object->labels->singular_name ),
			];

			if ( $object->has_archive ) {
				$templates[ 'archive-' . $post_type ] = [
					/* translators: %s: Post type plural name, e.g. "Sermons". */
					sprintf( __( '%s Archive', 'churchplugins' ), $object->labels->name ),
					/* translators: %s: Post type plural name, e.g. "Sermons". */
					sprintf( __( 'Controls the page that lists all %s.', 'churchplugins' ), $object->labels->name ),
				];
			}
		}

		foreach ( (array) $this->get_taxonomies() as $taxonomy ) {
			$object = get_taxonomy( $taxonomy );

			if ( ! $object || ! is_taxonomy_viewable( $object ) ) {
				continue;
			}

			$templates[ 'taxonomy-' . $taxonomy ] = $this->get_taxonomy_template_labels( $object );
		}

		$content     = $this->get_block_template_content();
		$plugin_name = $this->get_plugin_name();

		foreach ( $templates as $slug => list( $title, $description ) ) {
			$name = $this->get_plugin_id() . '//' . $slug;

			if ( \WP_Block_Templates_Registry::get_instance()->is_registered( $name ) ) {
				continue;
			}

			register_block_template(
				$name,
				[
					/* translators: 1: Plugin name, e.g. "CP Sermons", 2: Template title, e.g. "Single Sermon". */
					'title'       => sprintf( __( '%1$s: %2$s', 'churchplugins' ), $plugin_name, $title ),
					'description' => $description,
					'content'     => $content,
				]
			);
		}
	}

	/**
	 * Title and description of a taxonomy's registered template.
	 *
	 * Taxonomy labels are often generic ("Type", "Category"), so the title
	 * names the post type the taxonomy belongs to when its label does not
	 * already ("Group Type Archive"), and the description says what the page
	 * lists ("Controls the page that lists the Groups in one Type.").
	 *
	 * @since 1.1.22
	 *
	 * @param \WP_Taxonomy $taxonomy Taxonomy object.
	 * @return string[] Title and description.
	 */
	protected function get_taxonomy_template_labels( $taxonomy ) {
		$singular   = $taxonomy->labels->singular_name;
		$post_types = array_values( array_intersect( (array) $taxonomy->object_type, (array) $this->get_post_types() ) );
		$post_types = array_filter( array_map( 'get_post_type_object', $post_types ? $post_types : (array) $taxonomy->object_type ) );
		$owner      = 1 === count( $post_types ) ? reset( $post_types ) : null;

		if ( ! $owner ) {
			$listed = $post_types
				? wp_sprintf( '%l', wp_list_pluck( $post_types, 'label' ) )
				: __( 'items', 'churchplugins' );

			return [
				/* translators: %s: Taxonomy singular name, e.g. "Topic". */
				sprintf( __( '%s Archive', 'churchplugins' ), $singular ),
				/* translators: 1: Post type plural names, e.g. "Sermons and Series", 2: Taxonomy singular name, e.g. "Topic". */
				sprintf( __( 'Controls the page that lists the %1$s in one %2$s.', 'churchplugins' ), $listed, $singular ),
			];
		}

		$title = false !== stripos( $singular, $owner->labels->singular_name )
			/* translators: %s: Taxonomy singular name, e.g. "Group Type". */
			? sprintf( __( '%s Archive', 'churchplugins' ), $singular )
			/* translators: 1: Post type singular name, e.g. "Group", 2: Taxonomy singular name, e.g. "Type". */
			: sprintf( __( '%1$s %2$s Archive', 'churchplugins' ), $owner->labels->singular_name, $singular );

		return [
			$title,
			/* translators: 1: Post type plural name, e.g. "Groups", 2: Taxonomy singular name, e.g. "Type". */
			sprintf( __( 'Controls the page that lists the %1$s in one %2$s.', 'churchplugins' ), $owner->labels->name, $singular ),
		];
	}

	/**
	 * Block markup shared by this plugin's registered templates.
	 *
	 * @since 1.1.21
	 *
	 * @return string
	 */
	protected function get_block_template_content() {
		$view = sprintf(
			'<!-- wp:churchplugins/view %s /-->',
			wp_json_encode(
				[
					'plugin' => $this->get_plugin_id(),
					'align'  => 'wide',
				]
			)
		);

		return implode(
			"\n\n",
			[
				apply_filters( 'cp_template_block_header', '<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->' ),
				'<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->',
				'<main class="wp-block-group">' . $view . '</main>',
				'<!-- /wp:group -->',
				apply_filters( 'cp_template_block_footer', '<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->' ),
			]
		);
	}

	/**
	 * Whether the current request is left to the block template hierarchy.
	 *
	 * True only for a request that is natively for this plugin's post types or
	 * taxonomies, because only then does core resolve this plugin's templates.
	 * False on classic themes, when the handoff is disabled, when
	 * `cp_template_is_query` adds or removes the request, and when the request
	 * uses one of the legacy modes that need the PHP templates: a page template
	 * chosen with `cp_default_template`, or a [cp-template] shortcode /
	 * `cp_disable_template`. Decided once per request.
	 *
	 * @since 1.1.21
	 *
	 * @return bool
	 */
	public function uses_block_template_handoff() {
		if ( null !== $this->block_template_handoff ) {
			return $this->block_template_handoff;
		}

		$handoff = $this->matches_query()
			&& $this->is_cp_query()
			&& $this->supports_block_templates()
			&& ! apply_filters( 'cp_default_template', false, $this )
			&& ! apply_filters( 'cp_disable_template', has_shortcode( get_the_content(), 'cp-template' ), $this->get_template_hierarchy( 'default-template' ), $this );

		$this->block_template_handoff = $handoff;

		return $handoff;
	}

	/**
	 * Hide this plugin's registered templates from a front-end request that
	 * stays on the legacy path, so core resolves the same template it did
	 * before the templates were registered.
	 *
	 * Site Editor customizations (source "custom") and theme files are kept.
	 *
	 * @since 1.1.21
	 *
	 * @param \WP_Block_Template[] $templates     Found block templates.
	 * @param array                $query         Template query.
	 * @param string               $template_type wp_template or wp_template_part.
	 * @return \WP_Block_Template[]
	 */
	public function filter_block_templates( $templates, $query, $template_type ) {
		if ( 'wp_template' !== $template_type || ! did_action( 'template_redirect' ) || ! $this->matches_query() ) {
			return $templates;
		}

		if ( $this->uses_block_template_handoff() ) {
			return $templates;
		}

		$plugin_id = $this->get_plugin_id();

		return array_values(
			array_filter(
				$templates,
				static function ( $template ) use ( $plugin_id ) {
					return ! ( 'plugin' === $template->source && $plugin_id === $template->plugin );
				}
			)
		);
	}

	/**
	 * Render the plugin's default template for the view block.
	 *
	 * Runs default-template.php without the framework's site header and footer,
	 * as the [cp-template] shortcode does, so the template's class filters and
	 * before/after hooks still run. The template's <main> element is split off
	 * so the block can carry its id and classes without printing a second
	 * <main> inside the block template's own.
	 *
	 * A plugin whose templates print their own header and footer overrides
	 * this method, leaves those out while it renders, and passes the output
	 * to split_view_html().
	 *
	 * @since 1.1.21
	 *
	 * @return array See split_view_html().
	 */
	public function get_block_view_parts() {
		$file = $this->get_template_hierarchy( 'default-template' );

		if ( ! $file ) {
			ob_start();
			$this->get_view();

			return [
				'before'     => '',
				'attributes' => [],
				'content'    => ob_get_clean(),
				'after'      => '',
			];
		}

		$restore_header = remove_action( 'cp_do_header', __CLASS__ . '::do_header' );
		$restore_footer = remove_action( 'cp_do_footer', __CLASS__ . '::do_footer' );

		try {
			ob_start();
			include $file;
			$html = ob_get_clean();
		} finally {
			if ( $restore_header ) {
				add_action( 'cp_do_header', __CLASS__ . '::do_header' );
			}

			if ( $restore_footer ) {
				add_action( 'cp_do_footer', __CLASS__ . '::do_footer' );
			}
		}

		return self::split_view_html( $html );
	}

	/**
	 * Split a rendered view around its <main> element.
	 *
	 * Markup without a <main> element is returned whole as the content.
	 *
	 * @since 1.1.21
	 *
	 * @param string $html Rendered view markup.
	 * @return array {
	 *     @type string $before     Output printed before <main>.
	 *     @type array  $attributes The <main> element's id and class.
	 *     @type string $content    Output inside <main>.
	 *     @type string $after      Output printed after </main>.
	 * }
	 */
	protected static function split_view_html( $html ) {
		$html  = (string) $html;
		$parts = [
			'before'     => '',
			'attributes' => [],
			'content'    => $html,
			'after'      => '',
		];

		$close = strripos( $html, '</main>' );

		if ( false === $close || ! preg_match( '/<main\b[^>]*>/i', $html, $open, PREG_OFFSET_CAPTURE ) || $open[0][1] > $close ) {
			return $parts;
		}

		$open_end = $open[0][1] + strlen( $open[0][0] );
		$tag      = new \WP_HTML_Tag_Processor( $open[0][0] );
		$tag->next_tag();

		foreach ( [ 'id', 'class' ] as $name ) {
			$value = $tag->get_attribute( $name );

			if ( is_string( $value ) && '' !== $value ) {
				$parts['attributes'][ $name ] = $value;
			}
		}

		$parts['before']  = substr( $html, 0, $open[0][1] );
		$parts['content'] = substr( $html, $open_end, $close - $open_end );
		$parts['after']   = substr( $html, $close + strlen( '</main>' ) );

		return $parts;
	}

	/**
	 * Add shortcode to output the current template
	 *
	 * @return mixed|string|null
	 * @since  1.1.10
	 *
	 * @author Tanner Moushey, 2/9/25
	 */
	public function template_shortcode() {
		global $wp_filter;

		if ( ! self::$template || self::$did_shortcode ) {
			return '';
		}

		// remove the header and footer
		remove_action( 'cp_do_header', __CLASS__ . '::do_header' );
		remove_action( 'cp_do_footer', __CLASS__ . '::do_footer' );

		// save all the_content filters and remove so that page builders don't interfere
		$saved_filters = isset($wp_filter['the_content']) ? clone( $wp_filter['the_content'] ) : null;
		remove_all_filters( 'the_content' );

		self::$did_shortcode = true;

		ob_start();
		include self::$template;
		$content = ob_get_clean();

		// restore the_content filters
		if ( $saved_filters ) {
			$wp_filter['the_content'] = $saved_filters;
		}

		return apply_filters( 'cp_template_shortcode', $content, self::$template, $this );
	}

	/**
	 * Include page template body class
	 *
	 * @param array $classes List of classes to filter
	 *
	 * @return mixed
	 */
	public function template_body_class( $classes ) {

		// A theme or customized block template leaves self::$template unset, so the
		// [cp-template] shortcode stays inert there; the view is the default template's.
		$template_filename = basename( self::$template ? self::$template : (string) $this->get_template_hierarchy( 'default-template' ) );

		$classes[] = 'cp-template';

		if ( $template_filename == 'default-template.php' ) {
			$classes[] = 'cp-page-template';
		} else {
			$classes[] = 'page-template-' . sanitize_title( $template_filename );
		}

		return array_unique( $classes );
	}

	/**
	 * Add the theme to the body class
	 *
	 * @return array $classes
	 **/
	public static function theme_body_class( $classes ) {
		$child_theme  = get_option( 'stylesheet' );
		$parent_theme = get_option( 'template' );

		// if the 2 options are the same, then there is no child theme
		if ( $child_theme == $parent_theme ) {
			$child_theme = false;
		}

		if ( $child_theme ) {
			$theme_classes = "cp-theme-parent-$parent_theme cpl-theme-child-$child_theme";
		} else {
			$theme_classes = "cp-theme-$parent_theme";
		}

		$classes[] = $theme_classes;

		return array_unique( $classes );
	}


	/**
	 * Checks if theme needs a compatibility fix
	 *
	 * @param string $theme Name of template from WP_Theme->Template, defaults to current active template
	 *
	 * @return mixed
	 */
	public function needs_compatibility_fix( $theme = null ) {
		// Defaults to current active theme
		if ( $theme === null ) {
			$theme = get_stylesheet();
		}

		$theme_compatibility_list = apply_filters( 'cp_themes_compatibility_fixes', self::$themes_with_compatibility_fixes, $this );

		return in_array( $theme, $theme_compatibility_list );
	}


	/**
	 * Determine when wp_head has been triggered.
	 */
	public static function wpHeadFinished() {
		self::$wpHeadComplete = true;
	}

	/**
	 * This is where the magic happens where we run some ninja code that hooks the query to resolve to a ChurchPlugins template.
	 *
	 * @param \WP_Query $query
	 */
	public function setup_cp_template( $query ) {

		if ( $query->is_main_query() && self::$wpHeadComplete ) {
			// on loop start, unset the global post so that template tags don't work before the_content()
			add_action( 'the_post', [ $this, 'spoof_the_post' ] );

			// on the_content, load our template
			add_filter( 'the_content', [ $this, 'load_cp_into_page_template' ] );

			// only do this once
			remove_action( 'loop_start', [ $this, 'setup_cp_template' ] );
		}
	}

	/**
	 * Spoof the global post just once
	 *
	 **/
	public function spoof_the_post() {
		$GLOBALS['post'] = $this->spoofed_post();
		remove_action( 'the_post', [ $this, 'spoof_the_post' ] );
	}

	/**
	 * Return the correct view template
	 *
	 * @param bool $view
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_view( $view = false ) {
		do_action( 'cp_pre_get_view', $this );

		if ( $view ) {
			$template_file = $this->get_template_hierarchy( $view, [ 'disable_view_check' => true ] );
		} else {
			$template_file = $this->get_current_page_template();
		}

		if ( file_exists( $template_file ) ) {
			do_action( 'cp_before_view', $template_file, $this );
			include( $template_file );
			do_action( 'cp_after_view', $template_file, $this );
		}

	}

	/**
	 * Get the correct internal page template
	 *
	 * @return string Template path
	 */
	public function get_current_page_template() {

		$template = '';


		$wp_query = $this->get_global_query_object();

		$types     = $this->get_post_types();
		$taxonomies = $this->get_taxonomies();

		if ( $wp_query->is_tax( $taxonomies ) ) {
			$template = $this->get_template_hierarchy( 'archive-tax', [ 'disable_view_check' => true ] );
		}

		if ( $wp_query->is_post_type_archive( $types ) ) {
			$template = $this->get_template_hierarchy( 'archive', [ 'disable_view_check' => true ] );
		}

		if ( $wp_query->is_singular( $types ) ) {
			$template = $this->get_template_hierarchy( 'single', [ 'disable_view_check' => true ] );
		}

		// apply filters
		return apply_filters( 'cp_current_view_template', $template, $this );

	}

	/**
	 * Loads the contents into the page template
	 *
	 * @return string Page content
	 */
	public function load_cp_into_page_template( $contents = '' ) {
		// only run once!!!
		remove_filter( 'the_content', [ $this, 'load_cp_into_page_template' ] );

		ob_start();

		echo apply_filters( 'cp_default_template_before_content', '', $this );
		$this->get_view();
		echo apply_filters( 'cp_default_template_after_content', '', $this );

		$contents = ob_get_clean();

		// make sure the loop ends after our template is included
		if ( ! is_404() ) {
			$this->endQuery();
		}

		return $contents;
	}

	public function get_template_part( $template, $args = [] ) {
		if ( ! $template = $this->get_template_hierarchy( $template ) ) {
			return;
		}

		include( $template );
	}

	/**
	 * Loads theme files in appropriate hierarchy: 1) child theme,
	 * 2) parent template, 3) plugin. Will look in the {plugin-id}
	 * directory in a theme and the templates/ directory in the plugin
	 *
	 * @param string $template template file to search for
	 * @param array  $args     additional arguments to affect the template path
	 *                         - namespace
	 *                         - plugin_path
	 *                         - disable_view_check - bypass the check to see if the view is enabled
	 *
	 * @return template path
	 **/
	public function get_template_hierarchy( $template, $args = [] ) {
		if ( ! is_array( $args ) ) {
			$passed        = func_get_args();
			$args          = [];
			$backwards_map = [ 'namespace', 'plugin_path' ];
			$count         = count( $passed );

			if ( $count > 1 ) {
				for ( $i = 1; $i < $count; $i ++ ) {
					$args[ $backwards_map[ $i - 1 ] ] = $passed[ $i ];
				}
			}
		}

		$args = wp_parse_args(
			$args, [
				'namespace'          => '/',
				'plugin_path'        => '',
				'disable_view_check' => false,
			]
		);

		/**
		 * @var string $namespace
		 * @var string $plugin_path
		 * @var bool   $disable_view_check
		 */
		extract( $args );

		// append .php to file name
		if ( substr( $template, - 4 ) != '.php' && false === strpos( $template, '.json' ) && false === strpos( $template, 'svg' ) ) {
			$template .= '.php';
		}

		// Allow base path for templates to be filtered
		$template_base_paths = apply_filters( 'cp_template_paths', ( array ) $this->get_plugin_path(), $this );

		// backwards compatibility if $plugin_path arg is used
		if ( $plugin_path && ! in_array( $plugin_path, $template_base_paths ) ) {
			array_unshift( $template_base_paths, $plugin_path );
		}

		$file = false;

		/* potential scenarios:

		- the user has no template overrides
			-> we can just look in our plugin dirs, for the specific path requested, don't need to worry about the namespace
		- the user created template overrides without the namespace, which reference non-overrides without the namespace and, their own other overrides without the namespace
			-> we need to look in their theme for the specific path requested
			-> if not found, we need to look in our plugin views for the file by adding the namespace
		- the user has template overrides using the namespace
			-> we should look in the theme dir, then the plugin dir for the specific path requested, don't need to worry about the namespace

		*/

		// check if there are overrides at all
		if ( locate_template( [ $this->get_plugin_id() . '/' ] ) ) {
			$overrides_exist = true;
		} else {
			$overrides_exist = false;
		}

		if ( $overrides_exist ) {
			// check the theme for specific file requested
			$file = locate_template( [ $this->get_plugin_id() . '/' . $template ], false, false );
			if ( ! $file ) {
				// if not found, it could be our plugin requesting the file with the namespace,
				// so check the theme for the path without the namespace
				$files = [];
				foreach ( array_keys( $template_base_paths ) as $namespace ) {
					if ( ! empty( $namespace ) && ! is_numeric( $namespace ) ) {
						$files[] = $this->get_plugin_id() . str_replace( $namespace, '', $template );
					}
				}
				$file = locate_template( $files, false, false );
				if ( $file ) {
					_deprecated_function( sprintf( esc_html__( 'Template overrides should be moved to the correct subdirectory: %s', 'cp-resources' ), str_replace( get_stylesheet_directory() . '/cp-resources/', '', $file ) ), '3.2', $template );
				}
			} else {
				$file = apply_filters( 'cp_template', $file, $template, $this );
			}
		}

		// if the theme file wasn't found, check our plugins views dirs
		if ( ! $file ) {

			foreach ( $template_base_paths as $template_base_path ) {

				// make sure directories are trailingslashed
				$template_base_path = ! empty( $template_base_path ) ? trailingslashit( $template_base_path ) : $template_base_path;

				$file = $template_base_path . 'templates/' . $template;

				$file = apply_filters( 'cp_template', $file, $template, $this );

				// return the first one found
				if ( file_exists( $file ) ) {
					break;
				} else {
					$file = false;
				}
			}
		}

		return apply_filters( 'cp_template_' . $template, $file, $this );
	}

	/**
	 * Query is complete: stop the loop from repeating.
	 */
	private function endQuery() {
		$wp_query = $this->get_global_query_object();

		$wp_query->current_post = - 1;
		$wp_query->post_count   = 0;
	}


	/**
	 * Spoof the query so that we can operate independently of what has been queried.
	 *
	 * @return object
	 */
	private function spoofed_post() {
		return (object) [
			'ID'                    => 0,
			'post_status'           => 'draft',
			'post_author'           => 0,
			'post_parent'           => 0,
			'post_type'             => 'page',
			'post_date'             => 0,
			'post_date_gmt'         => 0,
			'post_modified'         => 0,
			'post_modified_gmt'     => 0,
			'post_content'          => '',
			'post_title'            => '',
			'post_excerpt'          => '',
			'post_content_filtered' => '',
			'post_mime_type'        => '',
			'post_password'         => '',
			'post_name'             => '',
			'guid'                  => '',
			'menu_order'            => 0,
			'pinged'                => '',
			'to_ping'               => '',
			'ping_status'           => '',
			'comment_status'        => 'closed',
			'comment_count'         => 0,
			'is_404'                => false,
			'is_page'               => false,
			'is_single'             => false,
			'is_archive'            => false,
			'is_tax'                => false,
		];
	}

}
