<?php

namespace ChurchPlugins\Models;

use ChurchPlugins\Exception;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Table base class
 *
 * @author Tanner Moushey
 * @since  1.0
*/
abstract class Table {

	/**
	 * The prefix to the database table
	 *
	 * @since   1.0
	 */
	protected $prefix;

	/**
	 * The name of our database table
	 *
	 * @since   1.0
	 */
	protected $table_name;

	/**
	 * The name of the meta database table
	 *
	 * @var string
	 */
	protected $meta_table_name;

	/**
	 * The name of the primary column
	 *
	 * @since   1.0
	 */
	protected $primary_key;

	/**
	 * Unique string to identify this data type
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * The post type associated with this object
	 *
	 * @var
	 */
	protected $post_type;

	/**
	 * ID of the cache group to use
	 *
	 * @var string
	 */
	protected $cache_group;

	/**
	 * ID of the cache group to use for the origin_id cache
	 *
	 * @var string
	 */
	protected $cache_group_origin;

	/**
	 * ID of the current post
	 *
	 * @var
	 */
	public $id = null;
	public $origin_id = null;

	public function init() {
		global $wpdb;

		$this->prefix = apply_filters( 'cp_table_prefix', $wpdb->base_prefix, $this );
		$this->cache_group = $this->post_type;
		$this->cache_group_origin = $this->cache_group . '_origin';
		$this->table_name  = $this->prefix . 'cp_' . $this->type;
		$this->meta_table_name  = $this->prefix . 'cp_' . $this->type . "_meta";
		$this->primary_key = 'id';
	}

	public static function get_prop( $var ) {
		$class = get_called_class();
		$instance = new $class();

		if ( property_exists( $instance, $var ) ) {
			return $instance->$var;
		}

		return '';
	}

	/**
	 * Setup instance using an origin id
	 * @param $origin_id
	 *
	 * @return bool | static self
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public static function get_instance_from_origin( $origin_id ) {
		global $wpdb;

		$origin_id = apply_filters( 'cp_origin_id', absint( $origin_id ) );

		if ( ! $origin_id ) {
			return false;
		}

		if ( ! get_post( $origin_id ) ) {
			throw new Exception( 'That post does not exist.' );
		}

		if ( static::get_prop('post_type' ) !== get_post_type( $origin_id ) ) {
			throw new Exception( 'The post type for the provided ID is not correct.' );
		}

		$post_status = get_post_status( $origin_id );
		if ( 'auto-draft' == $post_status ) {
			throw new Exception( 'No instance retrieved for auto-draft' );
		}

		$object = wp_cache_get( $origin_id, static::get_prop( 'cache_group_origin' ) );

		if ( ! $object ) {

			/**
			 * Allow filtering the used ID in case we need to set it to another site on a multisite
			 *
			 * Warning: if this filter is used, then the provided ID will belong to another blog and will not provide
			 * the correct data if the post is accessed without switching to that blog
			 */
			$queried_id = apply_filters( 'cp_origin_id_sql', $origin_id );

			$sql = apply_filters( 'cp_instance_from_origin_sql', $wpdb->prepare( "SELECT * FROM " . static::get_prop( 'table_name' ) . " WHERE origin_id = %s LIMIT 1;", $queried_id ), $queried_id, $origin_id, get_called_class() );
			$object = $wpdb->get_row( $sql );

			// if object does not exist, create it
			if ( ! $object ) {
				$data   = [ 'origin_id' => $queried_id, 'status' => $post_status ];
				$object = static::insert( $data );
			}

			wp_cache_add( $object->id, $object, static::get_prop( 'cache_group' ) );
			wp_cache_add( $origin_id, $object, static::get_prop( 'cache_group_origin' ) );
		}

		$class = get_called_class();
		return new $class( $object );
	}

	/**
	 * Setup instance using the primary id
	 * @param $id integer
	 *
	 * @return static
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public static function get_instance( $id = 0 ) {
		global $wpdb;

		$id = absint( $id );
		$class = get_called_class();

		if ( ! $id ) {
			return new $class();
		}

		$object = wp_cache_get( $id, static::get_prop( 'cache_group' ) );

		if ( ! $object ) {
			$object = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . static::get_prop( 'table_name' ) . " WHERE id = %s LIMIT 1;", $id ) );

			if ( ! $object ) {
				throw new Exception( 'Could not find object.' );
			}

			wp_cache_add( $id, $object, static::get_prop( 'cache_group' ) );

			if ( property_exists( $object, 'origin_id' ) ) {
				wp_cache_add( $object->origin_id, $object, static::get_prop( 'cache_group_origin' ) );
			}
		}

		return new $class( $object );
	}

	public static function search( $column, $value, $soundex = false ) {
		global $wpdb;

		if ( $soundex ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . static::get_prop('table_name' ) . " WHERE soundex($column) LIKE soundex(%s);", "%" . $value . "%" ) );
		} else {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . static::get_prop('table_name' ) . " WHERE $column LIKE %s;", "%" . $value . "%" ) );
		}
	}

	/**
	 * Get things started
	 *
	 * @since   1.0
	 */
	public function __construct( $object = false ) {
		$this->init();

		if ( ! $object ) {
			return;
		}

		foreach( get_object_vars( $object ) as $key => $value ) {
			$this->$key = $value;
		}

	}

	/**
	 * Whitelist of columns
	 *
	 * @since   1.0
	 * @return  array
	 */
	public static function get_columns() {
		return array();
	}

	/**
	 * Default column values
	 *
	 * @since   1.0
	 * @return  array
	 */
	public static function get_column_defaults() {
		return array();
	}

	/**
	 * Whitelist of meta columns
	 *
	 * @since   1.0
	 * @return  array
	 */
	public static function get_meta_columns() {
		return array();
	}

	/**
	 * Default meta column values
	 *
	 * @since   1.0
	 * @return  array
	 */
	public function get_meta_column_defaults() {
		return array(
			$this->type . '_id' => $this->id,
			'updated' => date( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Insert a new row
	 *
	 * @param        $data
	 *
	 * @return bool | object
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public static function insert( $data ) {
		global $wpdb;

		/**
		 * @var static
		 */
		$data = apply_filters( 'cp_pre_insert', $data );

		// Set default values
		$data = wp_parse_args( $data, static::get_column_defaults() );

		// Initialise column format array
		$column_formats = static::get_columns();

		// Force fields to lower case
		$data = array_change_key_case( $data );

		// White list columns
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data
		$data_keys = array_keys( $data );
		$column_formats = array_merge( array_flip( $data_keys ), $column_formats );

		$wpdb->insert( static::get_prop('table_name' ), $data, $column_formats );

		if ( ! $wpdb_insert_id = $wpdb->insert_id ) {
			throw new Exception( 'Could not insert data.' );
		}

		static::set_last_changed();

		do_action( 'cp_post_insert', $wpdb_insert_id, $data );

		return static::get_instance( $wpdb_insert_id );
	}

	/**
	 * @param array  $data
	 * @param string $where
	 *
	 *
	 * @return bool
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function update( $data = array() ) {

		global $wpdb;

		if ( empty( $data['updated'] ) ) {
			$data['updated'] = date( 'Y-m-d H:i:s' );
		}

		$data = apply_filters( 'cp_pre_update', $data, $this );

		// Row ID must be positive integer
		$row_id = absint( $this->id );

		if ( empty( $row_id ) ) {
			throw new Exception( 'No row id provided.' );
		}

		if ( empty( $where ) ) {
			$where = static::get_prop('primary_key' );
		}

		// Initialise column format array
		$column_formats = static::get_columns();

		// Force fields to lower case
		$data = array_change_key_case( $data );

		// White list columns
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data
		$data_keys = array_keys( $data );
		$column_formats = array_merge( array_flip( $data_keys ), $column_formats );

		if ( false === $wpdb->update( static::get_prop('table_name' ), $data, array( $where => $row_id ), $column_formats ) ) {
			throw new Exception( sprintf( 'The row (%d) was not updated.', absint( $row_id ) ) );
		}

		$this->delete_cache();
		static::set_last_changed();

		do_action( 'cp_post_update', $data, $this );

		return true;
	}

	/**
	 * Insert or update new meta
	 *
	 * @param $key
	 * @param $value
	 *
	 * @return false|int
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function update_meta_value( $key, $value ) {
		$data = [ 'key' => $key, 'value' => $value, $this->type . '_id' => $this->id ];
		return $this->update_meta( $data );
	}

	public function update_meta( $data, $unique = true ) {
		global $wpdb;

		$data = apply_filters( 'cp_pre_update_meta', $data, $this );

		// Initialise column format array
		$column_formats = static::get_meta_columns();

		// Force fields to lower case
		$data = array_change_key_case( $data );

		// White list columns
		$data = array_intersect_key( $data, $column_formats );

		// Reorder $column_formats to match the order of columns given in $data
		$data_keys = array_keys( $data );
		$column_formats = array_merge( array_flip( $data_keys ), $column_formats );

		if ( $this->get_meta_value( $data['key'] ) && $unique ) {
			$result = $wpdb->update( static::get_prop('meta_table_name' ), $data, array(
				$this->type . '_id' => $this->id,
				'key'     => $data['key']
			), $column_formats );
		} else {
			// set default values
			$data = wp_parse_args( $data, $this->get_meta_column_defaults() );
			$wpdb->insert( static::get_prop('meta_table_name' ), $data, $column_formats );
			$result = $wpdb->insert_id;
		}

		wp_cache_delete( $this->id, static::get_prop( 'cache_group' ) . '_meta' );

		static::set_last_changed();

		return $result;
	}

	/**
	 * Get meta value for object
	 *
	 * @param $key
	 * @param $default
	 *
	 * @return mixed|string|void|null
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_meta_value( $key, $default = '' ) {

		if ( $check = apply_filters( "get_" . static::get_prop( 'cache_group' ) . "_metadata", null, $key, $this ) ) {
			return $check;
		}

		$meta_cache = wp_cache_get( $this->id, static::get_prop( 'cache_group' ) . '_meta' );

		if ( ! $meta_cache ) {
			global $wpdb;

			$meta_list = $wpdb->get_results( $wpdb->prepare( "SELECT id, `key`, `value` FROM " . static::get_prop( 'meta_table_name' ) . " WHERE {$this->type}_id = %d;", $this->id ) );

			$meta_cache = [];
			foreach( $meta_list as $meta ) {
				$meta_cache[ $meta->key ] = $meta->value;
			}

			wp_cache_add( $this->id, $meta_cache, static::get_prop( 'cache_group' ) . '_meta' );
		}

		if ( isset( $meta_cache[ $key ] ) ) {
			return maybe_unserialize( $meta_cache[ $key ] );
		}

		return $default;
	}

	/**
	 * @param $value
	 * @param $column string
	 *
	 * @return bool
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function delete_meta( $value, $column = 'key' ) {
		global $wpdb;


		if ( false === $wpdb->query( $wpdb->prepare( "DELETE FROM " . $this->meta_table_name . " WHERE `{$this->type}_id` = %d AND `{$column}` = %s", $this->id, $value ) ) ) {
			throw new Exception( sprintf( 'The row (%d) was not deleted.', absint( $this->id ) ) );
		}

		wp_cache_delete( $this->id, static::get_prop( 'cache_group' ) . '_meta' );

		return true;
	}

	/**
	 * Delete all meta from a table where the value matches the column
	 *
	 * @param $value
	 * @param $column
	 *
	 * @return bool
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function delete_all_meta( $value, $column ) {
		global $wpdb;

		wp_cache_delete( $this->id, static::get_prop( 'cache_group' ) . '_meta' );

		return $wpdb->query( $wpdb->prepare( "DELETE FROM " . $this->meta_table_name . " WHERE `{$column}` = %s", $value ) );
	}

	/**
	 * @return bool
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function delete() {

		global $wpdb;

		do_action( 'cp_post_delete_before', $this );

		if ( false === $wpdb->query( $wpdb->prepare( "DELETE FROM " . static::get_prop('table_name' ) . " WHERE " . static::get_prop('primary_key' ) . " = %d", $this->id ) ) ) {
			throw new Exception( sprintf( 'The row (%d) was not deleted.', absint( $this->id ) ) );
		}


		$this->delete_cache();
		wp_cache_delete( $this->id, static::get_prop( 'cache_group' ) . '_meta' );

		do_action( 'cp_post_delete_after', $this );

		return true;
	}

	/**
	 * Delete the cache for this object
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function delete_cache() {
		wp_cache_delete( $this->id, static::get_prop( 'cache_group' ) );

		if ( property_exists( $this, 'origin_id' ) ) {
			wp_cache_delete( $this->origin_id, static::get_prop( 'cache_group_origin' ) );
		}
	}

	/**
	 * Update the cache for this object
	 *
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function update_cache() {
		$this->delete_cache();

		wp_cache_add( $this->id, $this, static::get_prop( 'cache_group' ) );

		if ( property_exists( $this, 'origin_id' ) ) {
			wp_cache_add( $this->origin_id, $this, static::get_prop( 'cache_group_origin' ) );
		}
	}

	/**
	 * Sets the last_changed cache key for customers.
	 *
	 * @since  1.0
	 */
	public static function set_last_changed() {
		wp_cache_set( 'last_changed', microtime(), static::get_prop('cache_group' ) );
	}

	/**
	 * Retrieves the value of the last_changed cache key for customers.
	 *
	 * @since  1.0.0
	 */
	public static function get_last_changed() {
		if ( function_exists( 'wp_cache_get_last_changed' ) ) {
			return wp_cache_get_last_changed( static::get_prop('cache_group' ) );
		}

		$last_changed = wp_cache_get( 'last_changed', static::get_prop('cache_group' ) );
		if ( ! $last_changed ) {
			$last_changed = microtime();
			wp_cache_set( 'last_changed', $last_changed, static::get_prop('cache_group' ) );
		}

		return $last_changed;
	}

}
