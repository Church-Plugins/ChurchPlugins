<?php

namespace ChurchPlugins\Setup\Admin;

/**
 * Plugin licensing class
 */
class License {

	/**
	 * @var License
	 */
	protected static $_instance;

	protected $id;

	protected $store_url;

	protected $plugin_file;

	protected $edd_id;

	protected $settings_url;

	protected $constant_aap = 'CHURCHPLUGINS_LICENSE';

	protected $constant = null;

	/**
	 * Class constructor
	 */
	public function __construct( $id, $edd_id, $store_url, $plugin_file, $settings_url ) {
		$this->id           = $id;
		$this->edd_id       = $edd_id;
		$this->store_url    = $store_url;
		$this->plugin_file   = $plugin_file;
		$this->settings_url = $settings_url;
		$this->constant     = strtoupper( $this->id ) . '_LICENSE';

		add_action ( 'admin_init', array( $this, 'check_license_constant' ), 3 );
		add_action( 'admin_init', array( $this, 'check_license' ) );
		add_action( 'admin_init', array( $this, 'activate_license' ) );
		add_action( 'admin_init', array( $this, 'deactivate_license' ) );
		add_action( 'admin_init', array( $this, 'plugin_updater' ), 5 );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Handle activation of license key from a constant
	 *
	 * @since  1.1.9
	 *
	 * @author Tanner Moushey, 12/24/24
	 */
	public function check_license_constant() {

		// bale early if we already have an active license
		if ( $this->is_active() ) {
			return;
		}

		if ( ! $const = $this->get_constant_license() ) {
			return;
		}

		$license = $this->get( 'license' );

		if ( $license == $const ) {
			return;
		}

		$this->update( 'license', $const );

		try {
			$this->maybe_activate_license( $this->get( 'license' ) );
		} catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
	}

	/**
	 * Get the license from a constant
	 *
	 * @return false|mixed
	 * @since  1.1.9
	 *
	 * @author Tanner Moushey, 12/31/24
	 */
	protected function get_constant_license() {
		if ( defined( $this->constant ) ) {
			return constant( $this->constant );
		}

		if ( defined( $this->constant_aap ) ) {
			return constant( $this->constant_aap );
		}

		return false;
	}

	/**
	 * Register REST routes
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'churchplugins/v1',
			"/license/$this->id",
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'rest_activate_license' ],
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
				'args' => [
					'license' => [
						'required' => true,
						'type'     => 'string',
					],
				],
			]
		);

		register_rest_route(
			'churchplugins/v1',
			"/license/$this->id",
			[
				'methods'  => 'DELETE',
				'callback' => [ $this, 'rest_deactivate_license' ],
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			]
		);

		register_rest_route(
			'churchplugins/v1',
			"/license/$this->id/status",
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'rest_license_status' ],
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			]
		);
	}

	/**
	 * REST License Status
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_license_status() {
		$license = $this->get( 'license' );

		return new \WP_REST_Response(
			[
				'license' => $license,
				'status'  => $this->get( 'status' )
			],
			200
		);
	}

	/**
	 * REST Activate License
	 *
	 * @param  \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function rest_activate_license( \WP_REST_Request $request ) {
		$license = $request->get_param( 'license' );

		try {
			$this->activate_license( $license );
			return new \WP_REST_Response(
				[
					'success' => true,
					'message' => __( 'License Activated', 'churchplugins' ),
					'status'  => $this->get( 'status' ),
				],
				200
			);
		} catch ( \Exception $e ) {
			return new \WP_REST_Response( [ 'success' => false, 'message' => $e->getMessage() ], 400 );
		}
	}

	/**
	 * REST Deactivate License
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_deactivate_license() {
		try {
			$this->deactivate_license();
			return new \WP_REST_Response(
				[
					'success' => true,
					'message' => __( 'License Deactivated', 'churchplugins' ),
					'status'  => $this->get( 'status' ),
				],
				200
			);
		} catch ( \Exception $e ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => $e->getMessage(),
				],
				400
			);
		}
	}

	/**
	 * Checks if the license is active.
	 */
	public function is_active() {
		return 'valid' == $this->get( 'status' );
	}

	/**
	 * Get a license option.
	 *
	 * @param string $key The option key.
	 * @param string $default The default value.
	 * @return string
	 */
	public function get( $key = false, $default = '' ) {
		$data = get_option( $this->id, [] );

		if ( empty( $key ) ) {
			return $data;
		}

		return trim( $data[ $key ] ?? $default );
	}

	/**
	 * Update a license option.
	 *
	 * @param string $key The option key.
	 * @param string $value The option value.
	 * @return void
	 */
	public function update( $key, $value ) {
		$data = $this->get();

		$data[ $key ] = $value;
		update_option( $this->id, $data, false );
	}

	public function get_license_check_slug() {
		return $this->id . '_license_check';
	}

	public function get_activate_slug() {
		return $this->id . '_license_activate';
	}

	public function get_deactivate_slug() {
		return $this->id . '_license_deactivate';
	}

	public function get_nonce_slug() {
		return $this->id . '_nonce';
	}

	public function license_field( $cmb ) {
		$args = [
			'name'        => __( 'License Key', 'Church Plugins' ),
			'id'          => 'license',
			'type'        => 'license',
			'nonce'       => false,
			'button_name' => '',
			'button_type' => 'secondary',
			'button_text' => '',
			'is_active'   => $this->is_active(),
			'attributes'  => [],
		];

		if ( $this->get_constant_license() ) {
			$args['desc'] = __( 'This license key is set by a constant and cannot be changed.', 'Church Plugins' );
		}

		if ( $this->get( 'license' ) ) {
			$args['nonce'] = wp_nonce_field( $this->get_nonce_slug(), $this->get_nonce_slug(), true, false );

			if ( $this->is_active() ) {
				$args['button_name'] = $this->get_deactivate_slug();
				$args['button_text'] = __( 'Deactivate License', 'Church Plugins' );

				$args['attributes']['disabled'] = 'disabled';
				$args['save_field'] = false; // disable saving of if we have the textarea disabled
			} else {
				$args['button_name'] = $this->get_activate_slug();
				$args['button_type'] = 'primary';
				$args['button_text'] = __( 'Activate License', 'Church Plugins' );
			}
		}

		$cmb->add_field( $args );
		$cmb->add_field( [
			'name' => __( 'Enable Beta Updates', 'Church Plugins' ),
			'id'   => 'beta',
			'type' => 'checkbox',
			'desc' => __( 'Check this box to enable beta updates.', 'Church Plugins' ),
		] );
	}

	/**
	 * Handle License activation
	 *
	 * @param string $license The license key.
	 * @return void
	 * @throws \Exception If an error occurs.
	 * @since 1.0.23
	 */
	public function maybe_activate_license( $license ) {
		// data to send in our API request
		$api_params = array(
			'edd_action'  => 'activate_license',
			'license'     => $license,
			'item_id'     => urlencode( $this->edd_id ), // the name of our product in EDD
			'url'         => home_url(),
			'environment' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
		);

		// Call the custom API.
		$response = wp_remote_post(
			$this->store_url,
			array(
				'timeout'   => 15,
				'sslverify' => false,
				'body'      => $api_params,
			)
		);

		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = __( 'An error occurred, please try again.' );
			}

			throw new \Exception( $message );
		} else {
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( false === $license_data->success ) {
				switch ( $license_data->error ) {

					case 'expired':
						$message = sprintf(
						/* translators: the license key expiration date */
							__( 'Your license key expired on %s.', 'ChurchPlugins' ),
							date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
						);
						break;

					case 'disabled':
					case 'revoked':
						$message = __( 'Your license key has been disabled.', 'ChurchPlugins' );
						break;

					case 'missing':
						$message = __( 'Invalid license.', 'ChurchPlugins' );
						break;

					case 'invalid':
					case 'site_inactive':
						$message = __( 'Your license is not active for this URL.', 'ChurchPlugins' );
						break;

					case 'item_name_mismatch':
						/* translators: the plugin name */
						$message = sprintf( __( 'This appears to be an invalid license key for %s.', 'ChurchPlugins' ), EDD_SAMPLE_ITEM_NAME );
						break;

					case 'no_activations_left':
						$message = __( 'Your license key has reached its activation limit.', 'ChurchPlugins' );
						break;

					default:
						$message = __( 'An error occurred, please try again.', 'ChurchPlugins' );
						break;
				}

				throw new \Exception( $message );
			}

			$this->update( 'status', $license_data->license );
		}
	}

	/**
	 * Handle License deactivation
	 *
	 * @return object The api response.
	 * @throws \Exception If an error occurs.
	 * @since 1.0.23
	 */
	public function maybe_deactivate_license() {
		// retrieve the license from the database
		$license = $this->get( 'license' );

		// data to send in our API request
		$api_params = array(
			'edd_action' => 'deactivate_license',
			'license'    => $license,
			'item_id'    => rawurlencode( $this->edd_id ), // the name of our product in EDD
			'url'        => home_url(),
		);

		// Call the custom API.
		$response = wp_remote_post(
			$this->store_url,
			array(
				'timeout'   => 15,
				'sslverify' => false,
				'body'      => $api_params,
			)
		);

		// make sure the response came back okay
		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// $license_data->license will be either "deactivated" or "failed"
		if ( 'deactivated' === $license_data->license ) {
			$this->update( 'status', 'deactivated' );
		}

		return $license_data;
	}

	/**
	 * Handle License activation
	 *
	 * @return void
	 */
	public function activate_license() {

		// listen for our activate button to be clicked
		if ( ! isset( $_POST[ $this->get_activate_slug() ], $_POST[ $this->get_nonce_slug() ] ) ) {
			return;
		}

		// run a quick security check
		if ( ! check_admin_referer( $this->get_nonce_slug(), $this->get_nonce_slug() ) ) {
			return;
		} // get out if we didn't click the Activate button

		// retrieve the license from the database
		$license = $this->get( 'license' );

		try {
			$this->maybe_activate_license( $license );
		} catch ( \Exception $e ) {
			$message = $e->getMessage();
		}

		// Check if anything passed on a message constituting a failure
		if ( ! empty( $message ) ) {
			$redirect = add_query_arg(
				array(
					'sl_activation' => 'false',
					'message'       => rawurlencode( $message ),
				),
				$this->settings_url
			);

			wp_safe_redirect( $redirect );
			exit();
		}

		delete_transient( $this->get_license_check_slug() );
	}

	/**
	 * Handle License deactivation
	 *
	 * @return void
	 */
	public function deactivate_license() {

		// listen for our activate button to be clicked
		if ( ! isset( $_POST[ $this->get_deactivate_slug() ], $_POST[ $this->get_nonce_slug() ] ) ) {
			return;
		}

		// run a quick security check
		if ( ! check_admin_referer( $this->get_nonce_slug(), $this->get_nonce_slug() ) ) {
			return;
		}

		try {
			$result = $this->maybe_deactivate_license();
				// $license_data->license will be either "deactivated" or "failed"
			if ( 'deactivated' === $result->license ) {
				delete_transient( $this->get_license_check_slug() );
			}
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Check license
	 *
	 * @return void
	 * @since       1.0.0
	 */
	public function check_license() {

		// Don't fire when saving settings
		if ( ! empty( $_POST[ $this->get_nonce_slug() ] ) ) {
			return;
		}

		$license = $this->get( 'license' );
		$status  = get_transient( $this->get_license_check_slug() );

		if ( $status === false && $license ) {

			$api_params = array(
				'edd_action' => 'check_license',
				'license'    => trim( $license ),
				'item_id'    => urlencode( $this->edd_id ),
				'url'        => home_url()
			);

			$response = wp_remote_post( $this->store_url, array( 'timeout' => 35, 'sslverify' => false, 'body' => $api_params ) );

			// make sure the response came back okay
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

				if ( is_wp_error( $response ) ) {
					$message = $response->get_error_message();
				} else {
					$message = __( 'An error occurred, please try again.' );
				}

				set_transient( $this->get_license_check_slug(), 'failed:' . $message, DAY_IN_SECONDS );
				return;
			}

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			$status = $license_data->license;

			$this->update( 'status', $status );

			set_transient( $this->get_license_check_slug(), $license_data->license, DAY_IN_SECONDS );
		}

	}

	/**
	 * Plugin Updater
	 *
	 * @return void
	 */
	public function plugin_updater() {
		$data = get_plugin_data( $this->plugin_file );

		// setup the updater
		new Updater( $this->store_url, $this->plugin_file, array(
				'version' => $data['Version'],    // current version number
				'license' => $this->get( 'license' ),     // license key (used get_option above to retrieve from DB)
				'item_id' => urlencode( $this->edd_id ), // the name of our product in EDD
				'author'  => $data['AuthorName'],  // author of this plugin
				'beta'    => boolval( $this->get( 'beta', false ) ),
			)
		);

	}

	/**
	 * This is a means of catching errors from the activation method above and displaying it to the customer
	 */
	function admin_notices() {
		if ( isset( $_GET['sl_activation'] ) && ! empty( $_GET['message'] ) ) {

			switch ( $_GET['sl_activation'] ) {

				case 'false':
					$message = urldecode( $_GET['message'] );
					?>
					<div class="error">
						<p><?php echo wp_kses_post( $message ); ?></p>
					</div>
					<?php
					break;

				case 'true':
				default:
					// Developers can put a custom success message here for when activation is successful if they way.
					break;

			}
		}
	}
}
