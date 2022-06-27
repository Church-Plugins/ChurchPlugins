<?php

namespace ChurchPlugins\Models;

use ChurchPlugins\Exception;

/**
 * Log DB Class
 *
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Log Class
 *
 * @since 1.0.0
 */
class Log extends Table  {

	public function init() {
		$this->type = 'log';

		parent::init();

		$this->table_name  = $this->prefix . 'cp_' . $this->type;
	}

	/**
	 * query the Log table
	 *
	 * @param $args
	 *
	 * @return array|object|\stdClass[]|null
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public static function query( $args = [] ) {
		global $wpdb;

		$where = ' WHERE 1 = 1 ';

		foreach( [ 'object_type', 'object_id', 'action', 'user_id' ] as $condition ) {
			if ( isset( $args[ $condition ] ) ) {
				$where .= sprintf( ' AND %s = "%s" ', $condition, $args[ $condition ] );
			}
		}

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . static::get_prop( 'table_name' ) . $where ) );
	}

	public static function count_by_action( $args ) {
		global $wpdb;

		$where = ' WHERE 1 = 1 ';

		foreach( [ 'object_type', 'object_id', 'action', 'user_id' ] as $condition ) {
			if ( isset( $args[ $condition ] ) ) {
				$where .= sprintf( ' AND %s = "%s" ', $condition, $args[ $condition ] );
			}
		}

		return $wpdb->get_results( $wpdb->prepare( "SELECT action, Count(*) as count FROM " . static::get_prop( 'table_name' ) . $where . ' Group By action' ) );

	}

	/**
	 * @return bool|void
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function delete() {
		parent::delete();
	}

	/**
	 * Get columns and formats
	 *
	 * @since   1.0
	*/
	public static function get_columns() {
		return array(
			'id'          => '%d',
			'object_type' => '%s',
			'object_id'   => '%d',
			'action'      => '%s',
			'data'        => '%s',
			'user_id'     => '%d',
			'created'     => '%s',
		);
	}

	/**
	 * Get default column values
	 *
	 * @since   1.0
	*/
	public static function get_column_defaults() {
		return array(
			'id'          => 0,
			'object_type' => '',
			'object_id'   => 0,
			'action'      => '',
			'data'        => '',
			'user_id'     => 0,
			'created'     => date( 'Y-m-d H:i:s' ),
		);
	}

}
