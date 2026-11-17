<?php
/**
 * The churchplugins/view block.
 *
 * @package ChurchPlugins
 * @since   1.1.21
 */

namespace ChurchPlugins\Blocks;

use ChurchPlugins\Templates;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Server-rendered block that prints a plugin's PHP view inside a block template.
 *
 * One block serves every ChurchPlugins plugin; the `plugin` attribute holds the
 * plugin id whose Templates instance renders the view. The newest framework copy
 * on a site serves all plugins, so a single framework block works for plugin
 * versions that know nothing about it.
 *
 * @since 1.1.21
 */
class ViewBlock {

	/**
	 * Block name.
	 *
	 * @var string
	 */
	const NAME = 'churchplugins/view';

	/**
	 * Single instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Whether a view is being rendered.
	 *
	 * @var bool
	 */
	protected static $rendering = false;

	/**
	 * Get the single instance.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	protected function __construct() {
		add_action( 'init', [ $this, 'register' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'editor_settings' ] );
	}

	/**
	 * Register the block type.
	 *
	 * @return void
	 */
	public function register() {
		register_block_type(
			__DIR__ . '/view',
			[
				'render_callback' => [ $this, 'render' ],
			]
		);
	}

	/**
	 * Pass the plugins that can render a view, and whether the block may be
	 * inserted, to the editor script.
	 *
	 * Only plugins that own post types or taxonomies on this request are
	 * listed, with those types, so the editor can offer the ones that match
	 * the template being edited. A plugin without any has no view to show.
	 *
	 * @since 1.1.21
	 * @since 1.1.22 Passes the `inserter` flag; lists only plugins with post
	 *               types or taxonomies, with those types.
	 *
	 * @return void
	 */
	public function editor_settings() {
		$plugins = [];

		foreach ( Templates::get_registered_instances() as $templates ) {
			$post_types = array_values( array_filter( (array) $templates->get_post_types() ) );
			$taxonomies = array_values( array_filter( (array) $templates->get_taxonomies() ) );

			if ( ! $post_types && ! $taxonomies ) {
				continue;
			}

			$plugins[] = [
				'value'      => $templates->get_plugin_id(),
				'label'      => $templates->get_plugin_name(),
				'postTypes'  => $post_types,
				'taxonomies' => $taxonomies,
			];
		}

		wp_add_inline_script(
			generate_block_asset_handle( self::NAME, 'editorScript' ),
			'window.cpViewBlock = ' . wp_json_encode(
				[
					'plugins'  => $plugins,
					'inserter' => $this->can_insert(),
				]
			) . ';',
			'before'
		);
	}

	/**
	 * Whether the block is offered in the inserter of the current editor.
	 *
	 * The block renders only as part of a template for a matching request, so
	 * it is offered only in the Site Editor, where templates and template parts
	 * are edited. Elsewhere (post editor, widgets, Customizer) it stays
	 * registered, so content that already contains it still loads, but it
	 * cannot be inserted.
	 *
	 * @since 1.1.22
	 *
	 * @return bool
	 */
	protected function can_insert() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$insert = $screen && 'site-editor' === $screen->id;

		/**
		 * Filters whether the ChurchPlugins view block is offered in the inserter.
		 *
		 * @since 1.1.22
		 *
		 * @param bool            $insert Whether the block can be inserted.
		 * @param \WP_Screen|null $screen The current admin screen.
		 */
		return (bool) apply_filters( 'cp_view_block_inserter', $insert, $screen );
	}

	/**
	 * Render the plugin view for the current request.
	 *
	 * The output is the plugin's default template without the site header and
	 * footer; the block wrapper takes the id and classes of that template's
	 * <main>, so the legacy wrapper CSS and class filters keep applying.
	 *
	 * The block renders only as part of a template: inside post content (the
	 * `the_content` filter, which the views themselves call) it prints nothing,
	 * so a block placed in a post or a pattern cannot render the view, which
	 * renders the content, which renders the block again. A re-entrancy guard
	 * covers any other nesting.
	 *
	 * The view runs its own loop over the main query and expects to start
	 * before the first post, as it did when it rendered ahead of the block
	 * template. Core renders singular block templates inside a the_post()
	 * loop, so the query is rewound for the view, and the loop position and
	 * the global post are restored afterwards; otherwise the view's rewind
	 * would restart core's loop and the template would render again.
	 *
	 * @param array $attributes Block attributes.
	 * @return string View markup, or an empty string when the request is not for this plugin.
	 */
	public function render( $attributes ) {
		global $wp_query, $post;

		if ( self::$rendering || doing_filter( 'the_content' ) ) {
			return '';
		}

		$plugin_id = isset( $attributes['plugin'] ) ? (string) $attributes['plugin'] : '';
		$templates = Templates::get_plugin_instance( $plugin_id );

		if ( ! $templates || ! $templates->is_cp_query() ) {
			return '';
		}

		$previous_post = $post;
		$current_post  = $wp_query->current_post;
		$query_post    = $wp_query->post;
		$in_the_loop   = $wp_query->in_the_loop;
		$buffer_level  = ob_get_level();
		$parts         = null;

		self::$rendering = true;

		try {
			$wp_query->rewind_posts();

			$parts = $templates->get_block_view_parts();
		} finally {
			// Close the view's buffer, and any it left open, even when it throws.
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}

			$wp_query->current_post = $current_post;
			$wp_query->post         = $query_post;
			$wp_query->in_the_loop  = $in_the_loop;

			if ( $previous_post instanceof \WP_Post ) {
				$post = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the post the view's loop moved.
				setup_postdata( $post );
			} else {
				wp_reset_postdata();
			}

			self::$rendering = false;
		}

		if ( '' === trim( implode( '', [ $parts['before'], $parts['content'], $parts['after'] ] ) ) ) {
			return '';
		}

		return $parts['before']
			. sprintf( '<div %1$s>%2$s</div>', get_block_wrapper_attributes( $parts['attributes'] ), $parts['content'] )
			. $parts['after'];
	}
}
