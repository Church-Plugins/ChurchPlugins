<?php

if ( ! class_exists( 'ChurchPlugins', false ) ) {

	/**
	 * Handles checking for and loading the newest version of ChurchPlugins
	 *
	 * @since  1.0.0
	 *
	 * @category  WordPress_Plugin
	 * @package   ChurchPlugins
	 * @license   GPL-2.0+
	 */
	class ChurchPlugins {

		/**
		 * Current version number
		 *
		 * @var   string
		 * @since 1.0.0
		 */
		const VERSION = '1.0.2';

		/**
		 * Current version hook priority.
		 * Will decrement with each release
		 *
		 * @var   int
		 * @since 1.0.0
		 */
		const PRIORITY = 9999;

		/**
		 * Single instance of the ChurchPlugins object
		 *
		 * @var ChurchPlugins
		 */
		public static $single_instance = null;

		/**
		 * Creates/returns the single instance ChurchPlugins object
		 *
		 * @since  1.0.0
		 * @return ChurchPlugins Single instance object
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

			if ( ! defined( 'CHURCHPLUGINS_LOADED' ) ) {
				define( 'CHURCHPLUGINS_LOADED', self::PRIORITY );
			}

			if ( ! function_exists( 'add_action' ) ) {
				// We are running outside of the context of WordPress.
				return;
			}

			$this->include();
		}

		/**
		 * A final check if CMB2 exists before kicking off our CMB2 loading.
		 * CMB2_VERSION and CMB2_DIR constants are set at this point.
		 *
		 * @since  2.0.0
		 */
		public function include() {

			if ( ! defined( 'CHURCHPLUGINS_DIR' ) ) {
				define( 'CHURCHPLUGINS_DIR', trailingslashit( dirname( __FILE__ ) ) );
			}

			require_once( CHURCHPLUGINS_DIR . 'vendor/autoload.php' );
			require_once( CHURCHPLUGINS_DIR . 'CMB2/init.php' );
			require_once( CHURCHPLUGINS_DIR . 'CMB2/includes/CMB2_Utils.php' );
			require_once( CHURCHPLUGINS_DIR . 'Helpers.php' );

			if ( ! defined( 'CHURCHPLUGINS_URL' ) ) {
				define( 'CHURCHPLUGINS_URL', trailingslashit( CMB2_Utils::get_url_from_dir( CHURCHPLUGINS_DIR ) ) );
			}

			$this->l10ni18n();

			ChurchPlugins\Setup\Init::get_instance();

			ChurchPlugins\Integrations\CMB2\Init::get_instance();
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

	}

	// Make it so...
	ChurchPlugins::initiate();

}// End if().
