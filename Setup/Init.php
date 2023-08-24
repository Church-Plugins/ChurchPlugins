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
	 * @var Customizer\_Init
	 */
	public $customizer;

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
	protected function includes() {
		require_once( CHURCHPLUGINS_DIR . 'Setup/Admin/License.php' );
		require_once( CHURCHPLUGINS_DIR . 'Setup/Admin/Updater.php' );

		require_once( CHURCHPLUGINS_DIR . 'Setup/Customizer/_Init.php' );
		require_once( CHURCHPLUGINS_DIR . 'Setup/PostTypes/PostType.php' );

		require_once( CHURCHPLUGINS_DIR . 'Setup/Tables/Table.php' );
		require_once( CHURCHPLUGINS_DIR . 'Setup/Tables/Log.php' );
		require_once( CHURCHPLUGINS_DIR . 'Setup/Tables/Source.php' );
		require_once( CHURCHPLUGINS_DIR . 'Setup/Tables/SourceMeta.php' );
		require_once( CHURCHPLUGINS_DIR . 'Setup/Tables/SourceType.php' );

		require_once( CHURCHPLUGINS_DIR . 'Setup/Taxonomies/Taxonomy.php' );

		$this->customizer = Customizer\_Init::get_instance();
	}

	protected function actions() {
		add_action( 'admin_init', [ $this, 'update_install' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_scripts'], 5 );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_icons' ], -99 );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_icons' ], -99 );
		add_action( 'script_loader_tag', [ $this, 'defer_scripts' ], 10, 3 );
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

	public function register_scripts() {
		wp_register_script( 'cp-admin-tools-import', CHURCHPLUGINS_URL . 'assets/js/admin/tools/import.js', array( 'jquery', 'jquery-form', 'underscore' ), CHURCHPLUGINS_VERSION  );
	}

	/**
  	 * Registers icons scripts and stylesheets to make them available to plugins that use ChurchPlugins core
	 */
	public function register_icons() {
		wp_register_style( 'material-icons', 'https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined' );
		wp_register_script( 'feather-icons-external', 'https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.js' );
		wp_register_script( 'feather-icons', CHURCHPLUGINS_URL . 'assets/js/feather-icons.js', array( 'feather-icons-external' ) );
	}


	/**
	 * Adds defer attribute to scripts
	 */
	public function defer_scripts( $tag, $handle, $src ) {
		/**
		 * Script handles whose resulting script tags will have a defer attribute
		 * 
		 * @param array $scripts list of script handles
		 */
		$scripts = apply_filters( 'cp_defer_scripts', array( 'feather-icons' ) );

		if( in_array( $handle, $scripts ) ) {
			return sprintf( '<script src="%1$s" defer type="text/javascript"></script>', $src );
		}

		return $tag;
	}

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

		$needs_check = apply_filters( 'cp_table_needs_check', CHURCHPLUGINS_VERSION !== $table_check );

		if ( $needs_check ) {

			foreach( $tables as $table ) {
				if ( ! @$table->installed() ) {
					// Create the source database (this ensures it creates it on multisite instances where it is network activated)
					@$table->create_table();
					$installed = true;
				}
			}

			if ( $installed ) {
				do_action( 'cp_resources_after_table_created' );
			}

			update_option( 'cp_table_check', CHURCHPLUGINS_VERSION );
		}

		foreach ( $tables as $table ) {
			if ( @$table->needs_update() ) {
				@$table->maybe_update();
			}
		}

	}

}
