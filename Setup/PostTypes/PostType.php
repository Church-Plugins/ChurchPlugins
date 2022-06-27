<?php
namespace ChurchPlugins\Setup\PostTypes;

// Exit if accessed directly
use ChurchPlugins\Exception;
use ChurchPlugins\Setup\Tables\Table;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Setup for custom post types
 *
 * @author costmo
 */
abstract class PostType {

	/**
	 * @var self
	 */
	protected static $_instance;

	/**
	 * Single label for CPT
	 * @var string
	 */
	public $single_label = '';

	/**
	 * Plural label for CPT
	 * @var string
	 */
	public $plural_label = '';

	/**
	 * An array of metabox definitions
	 *
	 * @var array
	 * @author costmo
	 */
	public $metaboxes = null;

	/**
	 * Convenience variable for children to set the post typer
	 *
	 * @var string
	 * @author costmo
	 */
	public $post_type = null;

	/**
	 * Gets the model for the called class
	 *
	 * @var null
	 */
	public $model = null;

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
	 * Get things started
	 *
	 * @since   1.0
	 */
	protected function __construct( $namespace = 'ChurchPlugins' ) {
		/** @var \ChurchPlugins\Models\Table model */
		$class = explode('\\', get_called_class() );
        $namespace = array_shift($class );
        $class = array_pop($class );
		$class = $namespace . '\Models\\' . $class;

		if ( class_exists( $class ) ) {
			$this->model = new $class;
		}
	}

	/**
	 * Registers a custom post type using child-configured args
	 *
	 * @return void
	 * @throws Exception
	 * @author costmo
	 */
	public function register_post_type() {
		$cpt_args = apply_filters( "{$this->post_type}_args", $this->get_args(), $this );

		if( empty( $cpt_args ) || !is_array( $cpt_args ) ) {
			throw new Exception( "No configuration present for this CPT" );
			return;
		}

		register_post_type( $this->post_type, $cpt_args );
	}

	// This is currently not being fired
	public function rest_request_limit( $params ) {

		if( !empty( $params )  && is_array( $params ) && !empty( $params[ 'per_page' ] ) ) {
			$params[ 'per_page' ][ 'maximum' ] = 9999;
			$params[ 'per_page' ][ 'minimum' ] = -1;
		}
		return $params;

	}

	/**
	 * Custom meta keys for this post type
	 *
	 * @return array
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function meta_keys () {
		return [];
	}

	/**
	 * Register metaboxes for Item admin
	 *
	 * Children should provide their own metaboxes
	 *
	 * @return void
	 * @author costmo
	 */
	abstract public function register_metaboxes();

	/**
	 * Get arguments for this post type
	 * @since  1.0.0
	 *
	 * @return array
	 * @author Tanner Moushey
	 */
	public function get_args() {
		$plural = $this->plural_label;
		$single = $this->single_label;
		$slug   = apply_filters( "{$this->post_type}_slug", strtolower( sanitize_title( $plural ) ) );

		return [
			'public'       => true,
			'show_in_menu' => $this->show_in_menu(),
			'show_in_rest' => true,
			'has_archive'  => $slug,
			'hierarchical' => false,
			'label'        => $single,
			'rewrite'      => [
				'slug' => $slug
			],
			'supports'     => [ 'title', 'editor', 'thumbnail' ],
			'labels'       => [
				'name'               => $plural,
				'singular_name'      => $single,
				'add_new'            => 'Add New',
				'add_new_item'       => 'Add New ' . $single,
				'edit'               => 'Edit',
				'edit_item'          => 'Edit ' . $single,
				'new_item'           => 'New ' . $single,
				'view'               => 'View',
				'view_item'          => 'View ' . $single,
				'search_items'       => 'Search ' . $plural,
				'not_found'          => 'No ' . $plural . ' found',
				'not_found_in_trash' => 'No ' . $plural . ' found in Trash',
				'parent'             => 'Parent ' . $single
			]
		];
	}

	public function show_in_menu() {
		return apply_filters( "{$this->post_type}_show_in_menu", true );
	}

	/**
	 * Default action-adder for this CPT-descendants of this class
	 *
	 * @return void
	 * @author costmo
	 */
	public function add_actions() {
		add_action( 'cmb2_admin_init', [ $this, 'register_metaboxes' ] );

		add_action( 'rest_cp_item_query', [ $this, 'rest_request_limit' ], 10, 1 );
		add_action( "save_post", [ $this, 'maybe_save_post' ], 50 );
		add_filter( 'cmb2_override_meta_save', [ $this, 'meta_save_override' ], 10, 4 );
		add_filter( 'cmb2_override_meta_remove', [ $this, 'meta_save_override' ], 10, 4 );

		if ( empty( $_GET['cpl-recovery'] ) ) {
			add_filter( 'cmb2_override_meta_value', [ $this, 'meta_get_override' ], 10, 4 );
		}

		add_action( "deleted_post", [ $this, 'delete_post' ] );
		add_action( 'cp_register_post_types', [ $this, 'register_post_type' ] );

		// legacy from CP Library
		add_filter( 'cpl_app_vars', [ $this, 'app_vars' ] );
	}

	/**
	 * Add vars for app localization
	 *
	 * @param $vars
	 *
	 * @return mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function app_vars( $vars ) {
		$type = get_post_type_object( $this->post_type );
		$key  = str_replace( CP_LIBRARY_UPREFIX . '_', '', $this->post_type );
		$vars[ $key ] = [
			'labelSingular' => $type->labels->singular_name,
			'labelPlural'   => $type->labels->name,
			'slug'          => $type->rewrite['slug'],
		];

		return $vars;
	}

	/**
	 * Wrapper instead of using "save_post_{post_type}" so we can hook into "save_post" before this in other places
	 *
	 * @param $post_id
	 *
	 * @return bool|\ChurchPlugins\Models\Item|\ChurchPlugins\Models\ItemType|\ChurchPlugins\Models\Source
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function maybe_save_post( $post_id ) {
		if ( get_post_type( $post_id ) !== $this->post_type ) {
			return false;
		}

		return $this->save_post( $post_id );
	}

	/**
	 * Save post to our custom table
	 *
	 * @param $post_id
	 *
	 * @return bool|\ChurchPlugins\Models\Item|\ChurchPlugins\Models\ItemType|\ChurchPlugins\Models\Source
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function save_post( $post_id ) {

		if ( 'auto-draft' == get_post_status( $post_id ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! $this->model ) {
			return false;
		}

		try {
			// this will save the item to our custom table if it does not already exist
			$model = $this->model::get_instance_from_origin( $post_id );
			$model->update( [ 'title' => get_post( $post_id )->post_title ] );
		} catch( Exception $e ) {
			error_log( $e->getMessage() );
			return false;
		}

		return $model;
	}

	/**
	 * @param $post_id
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function delete_post( $post_id ) {
		if ( ! $this->model ) {
			return;
		}

		if ( get_post_type( $post_id ) !== $this->post_type ) {
			return;
		}

		try {
			$model = $this->model::get_instance_from_origin( $post_id );
			$model->delete();
		} catch ( Exception $e ) {
			error_log( $e );
		}
	}

	/**
	 * Hijack the meta save filter to save our meta to our tables
	 *
	 * Currently will also save to postmeta
	 *
	 * @param $return
	 * @param $data_args
	 * @param $field_args
	 * @param $field
	 *
	 * @return mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function meta_save_override( $return, $data_args, $field_args, $field ) {

		$post_id = $data_args['id'];

		// break early if this is not our  post type
		if  ( get_post_type( $post_id ) !== $this->post_type  || ! $this->model ) {
			return $return;
		}

		// only hijack meta keys that we control
		if ( ! in_array( $data_args['field_id'], $this->meta_keys() ) ) {
			return $return;
		}

		try {
			$model = $this->model::get_instance_from_origin( $post_id );

			// @todo at some point update the return value to prevent saving in meta table and our table
			// for now, we'll save to both places
			if ( isset( $data_args['value'] ) ) {
//				$return = '';
				$model->update_meta_value( $data_args['field_id'], $data_args['value'] );
			} else {
//				$return = '';
				$model->delete_meta( $data_args['field_id'] );
			}
		} catch ( Exception $e ) {
			error_log( $e->getMessage() );
		}

		return $return;
	}

	/**
	 * Get our meta from our table
	 *
	 * @param $data
	 * @param $object_id
	 * @param $data_args
	 * @param $field
	 *
	 * @return mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function meta_get_override( $data, $object_id, $data_args, $field ) {

		// break early if this is not our  post type
		if  ( get_post_type( $object_id) !== $this->post_type || ! $this->model ) {
			return $data;
		}

		// only hijack meta keys that we control
		if ( ! in_array( $data_args['field_id'], $this->meta_keys() ) ) {
			return $data;
		}

		try {
			$model = $this->model::get_instance_from_origin( $object_id );
			$data = $model->get_meta_value( $data_args['field_id'] );
		} catch ( Exception $e ) {
			error_log( $e->getMessage() );
		}

		return $data;

	}


}
