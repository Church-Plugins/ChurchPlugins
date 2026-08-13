<?php

namespace ChurchPlugins\Setup\Tables;

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

	/**
	 * Get things started
	 *
	 * @since  1.0.0
	*/
	public function __construct() {
		parent::__construct();

		$this->table_name = $this->prefix . 'cp_log';
		$this->version    = '1.1';
	}

	/**
	 * The composite index reporting queries depend on.
	 *
	 * @var string
	 * @since 1.1.18
	 */
	const ACTION_CREATED_INDEX = 'idx_action_created';

	/**
	 * Create the table
	 *
	 * @since   1.0.0
	*/
	public function get_sql() {

		return "CREATE TABLE " . $this->table_name . " (
			`id` bigint NOT NULL AUTO_INCREMENT,
			`object_type` varchar(255),
			`object_id` bigint,
			`action` varchar(255),
			`data` longtext,
			`user_id` bigint,
			`created` DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (`id`),
			KEY `idx_object_type` (`object_type`),
			KEY `idx_object_id` (`object_id`),
			KEY `idx_action` (`action`),
			KEY `idx_user_id` (`user_id`),
			KEY `idx_action_created` (`action`, `created`)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";
	}

	/**
	 * Add the (action, created) index to tables created before 1.1.18.
	 *
	 * Reporting reads this table as "rows with action X since date Y" — see the
	 * analytics queries in CP Sermons. `idx_action` alone cannot serve that:
	 * `action` has only a handful of distinct values and the common ones cover
	 * most of the table, so the range on `created` was resolved by scanning.
	 *
	 * @since 1.1.18
	 */
	public function maybe_update() {
		global $wpdb;

		if ( ! $this->has_index( self::ACTION_CREATED_INDEX ) ) {
			$index = self::ACTION_CREATED_INDEX;

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers, not values.
			$wpdb->query( "ALTER TABLE {$this->table_name} ADD INDEX `{$index}` (`action`, `created`)" );
		}

		/*
		 * Only record the new version once the index is actually present. This
		 * runs from update_install() on an admin request, and on a log table
		 * with millions of rows the ALTER can outlast the request — recording
		 * unconditionally would mark the table current with no index on it, and
		 * nothing would ever try again.
		 */
		if ( $this->has_index( self::ACTION_CREATED_INDEX ) ) {
			$this->updated_table();
		}
	}

	/**
	 * Whether an index exists on this table.
	 *
	 * @param string $name The index name.
	 * @return bool
	 * @since 1.1.18
	 */
	protected function has_index( $name ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from $wpdb->prefix.
		$sql = $wpdb->prepare( "SHOW INDEX FROM {$this->table_name} WHERE Key_name = %s", $name );

		return (bool) $wpdb->get_var( $sql );
	}

}
