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
		global $wpdb;

		$this->table_name = $wpdb->prefix . 'cp_log';
		$this->version    = '1.0';

		parent::__construct();
	}

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
			KEY `idx_user_id` (`user_id`)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";
	}

}
