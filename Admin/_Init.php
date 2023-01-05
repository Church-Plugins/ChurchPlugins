<?php

namespace ChurchPlugins\Admin;

/**
 * Setup plugin _Initialization
 */
class _Init {

	/**
	 * @var _Init
	 */
	protected static $_instance;

	/**
	 * @var
	 */
	public $import;

	/**
	 * Only make one instance of _Init
	 *
	 * @return _Init
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof _Init ) {
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
	 * Admin _Init includes
	 *
	 * @return void
	 */
	protected function includes() {
		$this->import = Import\_Init::get_instance();
	}

	protected function actions() {
		add_action( 'init', [ $this, 'request_actions' ] );
	}


	/** Actions ***************************************************/

	/**
	 * Handle actions submitted through $_GET and $_POST requests
	 *
	 * @since  1.0.6
	 *
	 * @author Tanner Moushey
	 */
	public function request_actions() {
		$key = ! empty( $_REQUEST['cp_action'] ) ? sanitize_key( $_REQUEST['cp_action'] ) : false;

		if ( empty( $key ) ) {
			return;
		}

		$vars = isset( $_POST['cp_action'] ) ? $_POST : $_GET;

		do_action( "{$key}", $vars );
	}


}
