<?php

namespace ChurchPlugins\Setup;

use ChurchPlugins;
use ChurchPlugins\Setup\Tables\Log;
use ChurchPlugins\Setup\Tables\Source;
use ChurchPlugins\Setup\Tables\SourceMeta;
use ChurchPlugins\Setup\Tables\SourceType;

/**
 * Setup plugin initialization
 */
class Init {

	/**
	 * @var Init
	 */
	protected static $_instance;

	/**
	 * @var Tables\Init
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public $tables;

	/**
	 * @var PostTypes\Init;
	 */
	public $post_types;

	/**
	 * @var Taxonomies\Init;
	 */
	public $taxonomies;

	/**
	 * Only make one instance of Init
	 *
	 * @return Init
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Init ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 *
	 */
	protected function __construct() {
		$this->includes();
		$this->actions();
	}

	/**
	 * Admin init includes
	 *
	 * @return void
	 */
	protected function includes() {}

	protected function actions() {
		add_action( 'admin_init', [ $this, 'update_install' ] );
	}

	/**
	 * Get the tables registered with Church Plugins
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	protected function get_registered_tables() {
		return apply_filters( 'cp_registered_tables', [ Log::get_instance(), Source::get_instance(), SourceMeta::get_instance(), SourceType::get_instance() ] );
	}

	/** Actions ***************************************************/

	/**
	 * Determine if needed tables are installed
	 *
	 * @return bool
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function is_installed() {
		$tables = $this->get_registered_tables();

		foreach ( $tables as $table ) {
			if ( ! @$table->installed() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Post-install actions
	 *
	 * - Make sure database is installed and up-to-date
	 *
	 * @return void
	 * @author tanner
	 */
	public function update_install() {

		if ( ! is_admin() ) {
			return;
		}

		$table_check = get_option( 'cp_table_check' );
		$installed   = false;
		$tables      = $this->get_registered_tables();

		$needs_check = apply_filters( 'cp_table_needs_check', ChurchPlugins::VERSION !== $table_check );

		if ( $needs_check ) {

			foreach( $tables as $table ) {
				if ( ! @$table->installed() ) {
					// Create the source database (this ensures it creates it on multisite instances where it is network activated)
					@$table->create_table();
					$installed = true;
				}
			}

			if ( $installed ) {
				do_action( 'cpl_after_table_created' );
			}

			update_option( 'cp_table_check', ChurchPlugins::VERSION );
		}

		foreach ( $tables as $table ) {
			if ( @$table->needs_update() ) {
				@$table->maybe_update();
			}
		}

	}

}
