<?php
/**
 * Base class for all ChurchPlugins plugins
 *
 * @since 1.1.1
 * @package ChurchPlugins
 */

namespace ChurchPlugins\Setup;

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
	 * Plugin setup entry hub
	 *
	 * @return void
	 */
	protected function includes() {}

	/**
	 * Plugin setup entry hub
	 *
	 * @return void
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
		$asset_dir  = $this->get_plugin_dir() . 'build/src/';
		$asset_url  = $this->get_plugin_url() . 'build/src/';
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
}
