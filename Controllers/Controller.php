<?php

namespace ChurchPlugins\Controllers;

use ChurchPlugins\Exception;

class Controller {

	/**
	 * @var bool| \ChurchPlugins\Models\Table
	 */
	public $model;

	/**
	 * @var array|\WP_Post|null
	 */
	public $post;

	/**
	 * constructor.
	 *
	 * @param $id
	 * @param bool $use_origin whether or not to use the origin id
	 *
	 * @throws Exception
	 */
	public function __construct( $id, $use_origin = true ) {
		$classname = explode( '\\', get_called_class() );
		$namespace = array_shift( $classname );
		$classname = array_pop( $classname );
		$classname = $namespace . '\Models\\' . $classname;

		/** @var $class \ChurchPlugins\Models\Table */
		$class = $classname;

		if ( class_exists( $classname ) ) {
			$this->model = $use_origin ? $class::get_instance_from_origin( $id ) : $class::get_instance( $id );
		}

		// allow for filtering the associated post in case we are on a subsite or some other reason
		$post = apply_filters( 'cp_controller_post', false, $this->model->origin_id, $use_origin, $this );

		if ( $post ) {
			$this->post = $post;
		} else {
			$this->post = get_post( $this->model->origin_id );
		}
	}

	/**
	 * This is a global filter that is used to filter the content of all the functions
	 *
	 * @param $value
	 * @param $function
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	protected function filter( $value, $function ) {
		return apply_filters( $this->model::get_prop('post_type') . '_' . $function, $value, $this );
	}

}
