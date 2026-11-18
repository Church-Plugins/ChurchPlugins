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
