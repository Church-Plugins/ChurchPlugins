<?php

if ( ! class_exists( 'ChurchPlugins_1_1_6', false ) ) {

	/**
	 * Handles checking for and loading the newest version of ChurchPlugins
	 *
	 * @since  1.0.0
	 *
	 * @category  WordPress_Plugin
	 * @package   ChurchPlugins
	 * @license   GPL-2.0+
	 */
	class ChurchPlugins_1_1_6 {

		/**
		 * Registered plugins
		 *
		 * @since 1.1.1
		 * @var \ChurchPlugins\Setup\Plugin[]
		 */
		public $registered_plugins = [];

		/**
		 * Current version number
		 *
		 * @var   string
		 * @since 1.0.0
		 */
		const VERSION = '1.1.6';

		/**
		 * Current version hook priority.
		 * Will decrement with each release
		 *
		 * @var   int
		 * @since 1.0.0
		 */
		const PRIORITY = 9968;

		/**
		 * Single instance of the ChurchPlugins object
		 *
		 * @var ChurchPlugins_1_1_6
		 */
		public static $single_instance = null;

		/**
		 * @var ChurchPlugins\Setup\Init
		 */
		public $setup;

		/**
		 * @var ChurchPlugins\Admin\_Init
		 */
		public $admin;

		/**
		 * Creates/returns the single instance ChurchPlugins object
		 *
		 * @since  1.0.0
		 * @return ChurchPlugins_1_1_6 Single instance object
		 */
		public static function initiate() {
			if ( null === self::$single_instance ) {
				self::$single_instance = new self();
			}
			return self::$single_instance;
		}

		/**
		 * @since 1.0.0
		 */
		private function __construct() {
			if ( ! function_exists( 'add_action' ) ) {
				// We are running outside of the context of WordPress.
				return;
			}

			add_action( 'plugins_loaded', [ $this, 'include_churchplugins' ], self::PRIORITY );
		}

		/**
		 * A final check if Church Plugins exists before kicking off our Church Plugins loading.
		 *
		 * @since  2.0.0
		 */
		public function include_churchplugins() {

			// this has already been loaded somewhere else
			if ( defined( 'CHURCHPLUGINS_VERSION' ) ) {
				return;
			}

			define( 'CHURCHPLUGINS_VERSION', self::VERSION );

			if ( ! defined( 'CHURCHPLUGINS_LOADED' ) ) {
				define( 'CHURCHPLUGINS_LOADED', self::PRIORITY );
			}

			if ( ! defined( 'CHURCHPLUGINS_DIR' ) ) {
				define( 'CHURCHPLUGINS_DIR', trailingslashit( dirname( __FILE__ ) ) );
			}

			require_once( CHURCHPLUGINS_DIR . 'Exception.php' );
			require_once( CHURCHPLUGINS_DIR . 'Setup/Init.php' );
			require_once( CHURCHPLUGINS_DIR . 'Admin/_Init.php' );
			require_once( CHURCHPLUGINS_DIR . 'CMB2/init.php' );
			require_once( CHURCHPLUGINS_DIR . 'CMB2/includes/CMB2_Utils.php' );
			require_once( CHURCHPLUGINS_DIR . 'Integrations/CMB2/Init.php' );
			require_once( CHURCHPLUGINS_DIR . 'Helpers.php' );
			require_once( CHURCHPLUGINS_DIR . 'Logging.php' );
			require_once( CHURCHPLUGINS_DIR . 'Templates.php' );
			require_once( CHURCHPLUGINS_DIR . 'functions.php' );

			require_once( CHURCHPLUGINS_DIR . 'Models/Table.php' );
			require_once( CHURCHPLUGINS_DIR . 'Models/Log.php' );
			require_once( CHURCHPLUGINS_DIR . 'Models/Source.php' );
			require_once( CHURCHPLUGINS_DIR . 'Models/SourceType.php' );

			require_once( CHURCHPLUGINS_DIR . 'Controllers/Controller.php' );

			require_once( CHURCHPLUGINS_DIR . 'RequestAPI/Base.php' );

			if ( ! defined( 'CHURCHPLUGINS_URL' ) ) {
				define( 'CHURCHPLUGINS_URL', trailingslashit( CMB2_Utils::get_url_from_dir( CHURCHPLUGINS_DIR ) ) );
			}

			$this->l10ni18n();

			$this->setup = ChurchPlugins\Setup\Init::get_instance();
			$this->admin = ChurchPlugins\Admin\_Init::get_instance();

			ChurchPlugins\Integrations\CMB2\Init::get_instance();

			do_action( 'cp_core_loaded' );
		}

		/**
		 * Registers CMB2 text domain path
		 *
		 * @since  2.0.0
		 */
		public function l10ni18n() {

			$loaded = load_plugin_textdomain( 'churchplugins', false, '/languages/' );

			if ( ! $loaded ) {
				$loaded = load_muplugin_textdomain( 'churchplugins', '/languages/' );
			}

			if ( ! $loaded ) {
				$loaded = load_theme_textdomain( 'churchplugins', get_stylesheet_directory() . '/languages/' );
			}

			if ( ! $loaded ) {
				$locale = apply_filters( 'plugin_locale', function_exists( 'determine_locale' ) ? determine_locale() : get_locale(), 'churchplugins' );
				$mofile = dirname( __FILE__ ) . '/languages/churchplugins-' . $locale . '.mo';
				load_textdomain( 'churchplugins', $mofile );
			}

		}

		/**
		 * Register a plugin.
		 *
		 * @param ChurchPlugins\Setup\Plugin $plugin
		 * @since 1.1.1
		 * @return void
		 */
		public function register_plugin( $plugin ) {
			$this->registered_plugins[ $plugin->get_id() ] = $plugin;
		}
	}

	return ChurchPlugins_1_1_6::initiate();

}// End if().
