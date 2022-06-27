<?php

namespace ChurchPlugins\Setup\Tables;

/**
 * SourceMeta DB Class
 *
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SourceMeta Class
 *
 * @since 1.0.0
 */
class SourceMeta extends Table  {

	/**
	 * Get things started
	 *
	 * @since  1.0.0
	*/
	public function __construct() {
		global $wpdb;

		$this->table_name = $wpdb->base_prefix . 'cp_source_meta';
		$this->version    = '1.0';

		parent::__construct();
	}

	/**
	 * Keys for key column
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public static function get_keys() {
		return apply_filters( 'cp_source_meta_keys_enum', [ 'name', 'title', 'url', 'source_type', 'source_item' ] );
	}

	/**
	 * SQL to update ENUM values for meta keys
	 *
	 * @return string
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function update_enum_sql() {
		$keys = "'" . implode( "', '", self::get_keys() ) . "'";
		return "ALTER TABLE " . $this->table_name . " MODIFY COLUMN key ENUM( $keys );";
	}

	/**
	 *
	 * @return null
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function maybe_update() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$sql = $this->update_enum_sql();

		$wpdb->query( $sql );
//		dbDelta( $sql );

		$this->updated_table();
	}

	/**
	 * Create the table
	 *
	 * @since   1.0.0
	*/
	public function get_sql() {

		$keys = "'" . implode( "', '", self::get_keys() ) . "'";

		return "CREATE TABLE " . $this->table_name . " (
			`id` bigint NOT NULL AUTO_INCREMENT,
			`key` ENUM( $keys ),
			`value` longtext,
			`source_id` bigint,
			`source_type_id` bigint,
			`item_id` bigint,
			`order` bigint,
			`published` datetime NOT NULL,
			`updated` datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY `idx_key` (`key`),
			KEY `idx_source_id` (`source_id`),
			KEY `idx_source_type_id` (`source_type_id`),
			KEY `idx_item_id` (`item_id`)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";


	}

}
