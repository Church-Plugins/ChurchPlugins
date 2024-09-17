<?php
/**
 * Base class for all ChurchPlugins plugins
 *
 * @since 1.1.1
 * @package ChurchPlugins
 */

namespace ChurchPlugins\Setup;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Plugin Class
 *
 * Base class for all ChurchPlugins plugins. Sets up ChurchPlugins plugin loading.
 *
 * @since 1.1.1
 */
abstract class Plugin {
	/**
	 * @var Plugin
	 */
	protected static $_instance;

	/**
	 * @var Customizer\_Init
	 */
	public $customizer;

	/**
	 * A plugin-contextual logger
	 *
	 * @var \ChurchPlugins\Logging
	 * @since 1.1.3
	 */
	public $logging;

	/**
	 * Only make one instance of Plugin
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( ! self::$_instance ) {
			self::$_instance = new static();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor
	 */
	protected function __construct() {
		add_action( 'cp_core_loaded', [ $this, 'maybe_setup' ], -9999 );
	}

	/**
	 * Plugin setup entry hub
	 *
	 * @return void
	 */
	public function maybe_setup() {
		churchplugins()->register_plugin( $this );
		$this->includes();
		$this->actions();
	}

	/**
	 * Include other APIs
	 */
	protected function includes() {
		$this->logging = new \ChurchPlugins\Logging( $this->get_id(), $this->is_debug_mode() );
	}

	/**
	 * Register hooks
	 */
	protected function actions() {}

	/**
	 * Get plugin directory. Must include trailing slash.
	 *
	 * @return string
	 */
	abstract public function get_plugin_dir();

	/**
	 * Get plugin URL. Must include trailing slash.
	 *
	 * @return string
	 */
	abstract public function get_plugin_url();

	/**
	 * Get plugin ID. This can be implemented by the plugins, but returns the plugin directory name by default.
	 *
	 * @return string
	 * @since 1.1.3
	 */
	public function get_id() {
		$path_sections = array_filter( explode( '/', $this->get_plugin_dir() ) );
		return array_pop( $path_sections );
	}

	/**
	 * Enqueue an asset bundled by wp-scripts.
	 *
	 * @param string $name The asset name.
	 * @param array  $extra_deps Extra dependencies to add to the asset.
	 * @param string $version The version number to use.
	 * @param bool   $is_style Whether the asset is a stylesheet.
	 * @param bool   $in_footer Whether the asset should be enqueued in the footer.
	 * @return array The asset details.
	 * @author Jonathan Roley
	 * @since  1.0.22
	 */
	public function enqueue_asset( $name, $extra_deps = array(), $version = null, $is_style = false, $in_footer = false ) {
		$asset_dir  = trailingslashit( $this->get_plugin_dir() ) . 'build/src/';
		$asset_url  = trailingslashit( $this->get_plugin_url() ) . 'build/src/';
		$asset_file = $asset_dir . $name . '.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$path_sections = array_filter( explode( '/', $this->get_plugin_dir() ) );
		$plugin_folder = array_pop( $path_sections );

		$assets = include( $asset_file );

		if ( ! $version ) {
			$version = $assets['version'];
		}

		$handle = "$plugin_folder-$name";

		if ( $is_style ) {
			$url = $asset_url . $name . '.css';

			wp_enqueue_style( $handle, $url, $extra_deps, $version );

			return array(
				'handle'  => $handle,
				'url'     => $url,
				'version' => $version,
				'deps'    => $assets['dependencies'],
				'type'    => 'style',
			);
		} else {
			$full_deps = array_merge( $assets['dependencies'], $extra_deps );
			$url       = $asset_url . $name . '.js';

			wp_enqueue_script( $handle, $url, $full_deps, $version, $in_footer );

			return array(
				'handle'  => $handle,
				'url'     => $url,
				'version' => $version,
				'deps'    => $full_deps,
				'type'    => 'script',
			);
		}
	}

	/**
	 * Whether the plugin is in debug mode
	 *
	 * @return bool
	 */
	public function is_debug_mode() {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}
}
