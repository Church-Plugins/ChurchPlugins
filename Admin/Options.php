<?php
/**
 * Handles getting and setting options via the WordPress rest API.
 *
 * @package ChurchPlugins
 */

namespace ChurchPlugins\Admin;

/**
 * Global options handler.
 *
 * @since 1.0.23
 */
class Options {
	/**
	 * Namespaces for the options.
	 *
	 * @var string[] 
	 */
	protected static $namespaces = [];

	/**
	 * Option prefix.
	 *
	 * @var string
	 */
	protected $prefix;

	/**
	 * Instance namespace.
	 *
	 * @var string
	 */
	protected $namespace;

	/**
	 * Class constructor.
	 *
	 * @param string $namespace The namespace for the rest route.
	 * @param string $option_prefix The prefix for the options.
	 */
	public function __construct( $namespace, $option_prefix ) {
		$this->namespace = $namespace;
		$this->prefix    = $option_prefix;
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the rest routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/options/(?P<group>[a-zA-Z0-9_-]+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_option_group' ],
				'permission_callback' => function() {
					return true; // current_user_can( 'manage_options' );
				},
			]
		);

		register_rest_route(
			$this->namespace,
			'/options/(?P<group>[a-zA-Z0-9_-]+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'set_option_group' ],
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			]
		);
	}

	/**
	 * Get the options.
	 *
	 * @param \WP_REST_Request $request The rest request.
	 *
	 * @return array
	 */
	public function get_option_group( $request ) {
		$option_group = $request->get_param( 'group' );

		return rest_ensure_response( get_option( $this->prefix . $option_group, [] ) );
	}

	/**
	 * Set the options.
	 *
	 * @param \WP_REST_Request $request The rest request.
	 *
	 * @return array
	 */
	public function set_option_group( $request ) {
		$option_group = $request->get_param( 'group' );
		$options      = $request->get_json_params();

		update_option( $this->prefix . $option_group, $options );

		return rest_ensure_response( get_option( $this->prefix . $option_group, [] ) );
	}

	/**
	 * Register rest route for the options.
	 */
	public static function register_rest_route( $namespace, $option_prefix ) {
		self::$namespaces[ $namespace ] = new self( $namespace, $option_prefix );
	}
}
