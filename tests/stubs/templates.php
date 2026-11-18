<?php
/**
 * WordPress stand-ins for the Templates unit tests.
 *
 * Loaded by tests/Unit/TemplatesTest.php after tests/bootstrap.php. Each
 * function reads its answer from $GLOBALS['cp_test_wp'], which a test resets
 * in setUp().
 *
 * @package ChurchPlugins
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.VariableComment.Missing

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! class_exists( 'WP_Post', false ) ) {
	class WP_Post {
		public $ID            = 0;
		public $post_type     = 'post';
		public $post_name     = '';
		public $page_template = '';
	}
}

if ( ! class_exists( 'WP_Term', false ) ) {
	class WP_Term {
		public $term_id  = 0;
		public $taxonomy = '';
		public $slug     = '';
	}
}

if ( ! function_exists( 'did_action' ) ) {
	function did_action( $hook ) {
		return isset( $GLOBALS['cp_test_wp']['did_action'][ $hook ] ) ? (int) $GLOBALS['cp_test_wp']['did_action'][ $hook ] : 0;
	}
}

if ( ! function_exists( 'get_block_template' ) ) {
	function get_block_template( $id ) {
		return isset( $GLOBALS['cp_test_wp']['block_templates'][ $id ] ) ? $GLOBALS['cp_test_wp']['block_templates'][ $id ] : null;
	}
}

if ( ! function_exists( 'get_page_template_slug' ) ) {
	function get_page_template_slug( $post = null ) {
		return ( $post instanceof WP_Post ) ? $post->page_template : '';
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		$GLOBALS['cp_test_wp']['get_posts_args'][] = $args;

		$name = isset( $args['name'] ) ? $args['name'] : '';

		return isset( $GLOBALS['cp_test_wp']['template_posts'][ $name ] ) ? array( (object) array( 'post_name' => $name ) ) : array();
	}
}

if ( ! function_exists( 'is_archive' ) ) {
	function is_archive() {
		return ! empty( $GLOBALS['cp_test_wp']['is_archive'] );
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type() {
		return isset( $GLOBALS['cp_test_wp']['post_type'] ) ? $GLOBALS['cp_test_wp']['post_type'] : false;
	}
}

if ( ! function_exists( 'get_post_type_object' ) ) {
	function get_post_type_object( $post_type ) {
		return isset( $GLOBALS['cp_test_wp']['post_type_objects'][ $post_type ] ) ? $GLOBALS['cp_test_wp']['post_type_objects'][ $post_type ] : null;
	}
}

if ( ! function_exists( 'get_the_content' ) ) {
	function get_the_content() {
		return isset( $GLOBALS['cp_test_wp']['content'] ) ? $GLOBALS['cp_test_wp']['content'] : '';
	}
}

if ( ! function_exists( 'has_shortcode' ) ) {
	function has_shortcode( $content, $tag ) {
		return false !== strpos( (string) $content, '[' . $tag );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $title ) ), '-' );
	}
}

if ( ! function_exists( 'wp_list_pluck' ) ) {
	function wp_list_pluck( $input_list, $field ) {
		$values = array();

		foreach ( $input_list as $key => $item ) {
			$values[ $key ] = is_object( $item ) ? $item->$field : $item[ $field ];
		}

		return $values;
	}
}

if ( ! function_exists( 'wp_sprintf' ) ) {
	/**
	 * Only the `%l` list form the framework uses.
	 */
	function wp_sprintf( $pattern, $items ) {
		$items = array_values( (array) $items );
		$last  = array_pop( $items );

		return $items ? implode( ', ', $items ) . ' and ' . $last : (string) $last;
	}
}

if ( ! function_exists( 'has_block' ) ) {
	/**
	 * Core's has_block() for a content string.
	 */
	function has_block( $block_name, $post = null ) {
		$content = (string) $post;

		if ( false === strpos( $content, '<!-- wp:' ) ) {
			return false;
		}

		if ( false === strpos( $block_name, '/' ) ) {
			$block_name = 'core/' . $block_name;
		}

		if ( 0 === strpos( $block_name, 'core/' ) ) {
			$block_name = substr( $block_name, 5 );
		}

		return false !== strpos( $content, '<!-- wp:' . $block_name . ' ' );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Records the callback; cp_test_apply_added_filters() runs them in order.
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['cp_test_wp']['added_filters'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default_value = false ) {
		return isset( $GLOBALS['cp_test_wp']['options'][ $option ] ) ? $GLOBALS['cp_test_wp']['options'][ $option ] : $default_value;
	}
}

if ( ! function_exists( 'wp_is_block_theme' ) ) {
	function wp_is_block_theme() {
		return ! empty( $GLOBALS['cp_test_wp']['block_theme'] );
	}
}

if ( ! function_exists( 'is_single' ) ) {
	function is_single() {
		return ! empty( $GLOBALS['cp_test_wp']['is_single'] );
	}
}

if ( ! function_exists( 'is_404' ) ) {
	function is_404() {
		return ! empty( $GLOBALS['cp_test_wp']['is_404'] );
	}
}

if ( ! function_exists( 'get_404_template' ) ) {
	function get_404_template() {
		return '/themes/test/404.php';
	}
}
