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
		require_once( CHURCHPLUGINS_DIR . 'Admin/Import/_Init.php' );
		require_once( CHURCHPLUGINS_DIR . 'Admin/Options.php' );
		require_once( CHURCHPLUGINS_DIR . 'Admin/Menu.php' );
		$this->import = Import\_Init::get_instance();
	}

	protected function actions() {
		add_action( 'init', [ $this, 'request_actions' ] );
	}


	/** Actions ***************************************************/

	/**
	 * Query argument that carries the nonce for an allowlisted cp_action request.
	 *
	 * @since 1.1.19
	 * @var string
	 */
	const REQUEST_ACTION_NONCE_ARG = 'cp_action_nonce';

	/**
	 * Handle actions submitted through $_GET and $_POST requests.
	 *
	 * An allowlisted action runs only for a logged-in user who has the required
	 * capability and a valid nonce from {@see self::request_action_nonce_field()}
	 * or {@see self::request_action_nonce_url()}. Any other hook name is ignored.
	 *
	 * Names on `cp_legacy_request_actions` run for that same logged-in user
	 * when the request carries a valid core nonce for the action, or a
	 * `_wpnonce` created for the action name, on any request method. Without
	 * either nonce the request must be POST and same-site. Same-site means the
	 * Origin header, or the Referer when Origin is absent, has the same
	 * scheme, host, and port as {@see home_url()} or {@see admin_url()}. A GET
	 * or HEAD, missing headers, or a different scheme, host, or port does not
	 * run. Registering one of those names on `cp_request_actions` opts it
	 * into the core nonce check and does not fall through to the legacy list.
	 *
	 * A refused request for a registered name fires `cp_request_action_refused`.
	 * A JSON request also gets a 403 JSON error. Unregistered names are ignored
	 * without either.
	 *
	 * A name registered on `cp_public_request_actions` is the opt-in for a
	 * front-end form. That callback must verify its own nonce. Core does not
	 * apply the capability check or the cp_action nonce to those names.
	 *
	 * @since 1.0.6
	 * @since 1.1.19 Restricts request actions to authorized users with a nonce.
	 *
	 * @author Tanner Moushey
	 */
	public function request_actions() {
		$action = self::requested_action();

		if ( '' === $action ) {
			return;
		}

		// Already inside this hook (for example `init`, which calls this method).
		// Dispatching it again would recurse.
		if ( doing_action( $action ) ) {
			return;
		}

		$vars = self::request_action_vars();

		if ( self::is_public_request_action( $action ) ) {
			do_action( $action, $vars );
			return;
		}

		// A name on the allowlist always requires the core nonce, including
		// when that name is also on the legacy list.
		$is_allowed = self::is_allowed_request_action( $action );

		if ( ! $is_allowed && ! self::is_legacy_request_action( $action ) ) {
			return;
		}

		if ( ! self::user_can_request_action( $action ) ) {
			self::refuse_request_action( $action, 'capability' );
			return;
		}

		if ( $is_allowed ) {
			if ( ! self::verify_request_action_nonce( $action ) ) {
				self::refuse_request_action( $action, 'nonce' );
				return;
			}

			do_action( $action, $vars );
			return;
		}

		if ( ! self::legacy_request_may_run( $action ) ) {
			self::refuse_request_action( $action, 'same_site' );
			return;
		}

		do_action( $action, $vars );
	}

	/**
	 * Report a refused request for a registered action name.
	 *
	 * Without this the request ends with an empty response, which the admin
	 * buttons that post `cp_action` cannot tell apart from a broken handler.
	 *
	 * @since 1.1.19
	 *
	 * @param string $action Requested action name.
	 * @param string $reason `capability`, `nonce`, or `same_site`.
	 * @return void
	 */
	protected static function refuse_request_action( $action, $reason ) {
		/**
		 * Fires when a request for a registered cp_action name is refused.
		 *
		 * @since 1.1.19
		 *
		 * @param string $action Requested action name.
		 * @param string $reason `capability`, `nonce`, or `same_site`.
		 */
		do_action( 'cp_request_action_refused', $action, $reason );

		if ( ! self::request_wants_json() ) {
			return;
		}

		// `error` matches the shape the CP Library settings buttons read.
		wp_send_json_error(
			array(
				'error' => __( 'You are not allowed to do that, or the page has expired. Reload the page and try again.', 'church-plugins' ),
			),
			403
		);
	}

	/**
	 * Whether the request was sent as JSON or asks for a JSON response.
	 *
	 * @since 1.1.19
	 *
	 * @return bool
	 */
	protected static function request_wants_json() {
		foreach ( array( 'CONTENT_TYPE', 'HTTP_ACCEPT' ) as $header ) {
			if ( isset( $_SERVER[ $header ] ) && is_string( $_SERVER[ $header ] ) && false !== stripos( $_SERVER[ $header ], 'application/json' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only searched for a fixed substring.
				return true;
			}
		}

		return false;
	}

	/**
	 * Nonce action string for a cp_action name.
	 *
	 * The nonce is bound to the action name, so a nonce minted for one action
	 * does not authorize a different one.
	 *
	 * @since 1.1.19
	 *
	 * @param string $action Action name the nonce authorizes.
	 * @return string
	 */
	public static function request_action_nonce_action( $action ) {
		return 'cp_action_' . sanitize_key( $action );
	}

	/**
	 * Hidden nonce field for a cp_action form.
	 *
	 * Returns the markup instead of printing it. Echo it inside the form that
	 * posts `cp_action`.
	 *
	 * @since 1.1.19
	 *
	 * @param string $action Action name the nonce authorizes.
	 * @param bool   $referer Whether to include the referer field. Default true.
	 * @return string
	 */
	public static function request_action_nonce_field( $action, $referer = true ) {
		return wp_nonce_field( self::request_action_nonce_action( $action ), self::REQUEST_ACTION_NONCE_ARG, $referer, false );
	}

	/**
	 * Append the cp_action nonce query argument to a URL.
	 *
	 * The nonce authorizes that action on its own, including a GET. A legacy
	 * name that arrives without this nonce is accepted only on POST, and only
	 * when the request is same-site.
	 *
	 * The return value is escaped for HTML, the same way {@see wp_nonce_url()}
	 * escapes its result. Echo it in HTML as returned.
	 *
	 * @since 1.1.19
	 *
	 * @param string $url    URL to modify.
	 * @param string $action Action name the nonce authorizes.
	 * @return string Escaped URL.
	 */
	public static function request_action_nonce_url( $url, $action ) {
		$url = str_replace( '&amp;', '&', $url );

		return esc_html(
			add_query_arg(
				self::REQUEST_ACTION_NONCE_ARG,
				wp_create_nonce( self::request_action_nonce_action( $action ) ),
				$url
			)
		);
	}

	/**
	 * Action name from the current request, or an empty string.
	 *
	 * @since 1.1.19
	 *
	 * @return string
	 */
	protected static function requested_action() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in request_actions() for allowlisted actions. Public actions verify their own nonce.
		if ( ! isset( $_REQUEST['cp_action'] ) || ! is_string( $_REQUEST['cp_action'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return sanitize_key( wp_unslash( $_REQUEST['cp_action'] ) );
	}

	/**
	 * Request array passed to the action callback.
	 *
	 * A POST body wins when it contains cp_action; otherwise the query string
	 * is passed through. Callbacks sanitize the fields they read.
	 *
	 * @since 1.1.19
	 *
	 * @return array
	 */
	protected static function request_action_vars() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed through for the callback, which sanitizes the fields it reads.
		if ( isset( $_POST['cp_action'] ) ) {
			return $_POST;
		}

		return $_GET;
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Whether the current user may run an allowlisted request action.
	 *
	 * @since 1.1.19
	 *
	 * @param string $action Requested action name.
	 * @return bool
	 */
	protected static function user_can_request_action( $action ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		/**
		 * Capability required to dispatch an allowlisted or legacy cp_action request.
		 *
		 * The default is `manage_options`, except `cpl_import_transcript`, which
		 * defaults to `edit_post` checked against the requested `post_id`. An
		 * author can import a transcript for their own sermon and not for
		 * anyone else's. CP Library releases before 1.8.0 do no check of
		 * their own, so the per-sermon check has to happen here.
		 *
		 * @since 1.1.19
		 *
		 * @param string $capability Capability required for this action.
		 * @param string $action     Requested action name.
		 */
		$capability = apply_filters( 'cp_request_action_capability', self::default_request_action_capability( $action ), $action );

		if ( ! is_string( $capability ) || '' === $capability ) {
			return false;
		}

		if ( 'cpl_import_transcript' !== $action ) {
			return current_user_can( $capability );
		}

		// The transcript import writes to one sermon, so the check is always
		// made against that sermon, including for a filtered capability.
		$post_id = self::requested_post_id();

		if ( ! $post_id ) {
			return false;
		}

		return current_user_can( $capability, $post_id );
	}

	/**
	 * Default capability for a request action before filters run.
	 *
	 * @since 1.1.19
	 *
	 * @param string $action Requested action name.
	 * @return string
	 */
	protected static function default_request_action_capability( $action ) {
		if ( 'cpl_import_transcript' === $action ) {
			return 'edit_post';
		}

		return 'manage_options';
	}

	/**
	 * Post ID from the current request, or 0.
	 *
	 * The callback may read `post_id` from `$_REQUEST` or from the array it is
	 * passed, which is `$_POST` or `$_GET` (see {@see self::request_action_vars()}).
	 * When those sources disagree, the ID checked here could differ from the
	 * one the callback acts on, so the request gets 0 and is refused.
	 *
	 * @since 1.1.19
	 *
	 * @return int
	 */
	protected static function requested_post_id() {
		$ids = array();

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Read for the capability check. The nonce is checked separately.
		foreach ( array( $_GET, $_POST, $_REQUEST ) as $source ) {
			if ( ! isset( $source['post_id'] ) ) {
				continue;
			}

			if ( ! is_scalar( $source['post_id'] ) ) {
				return 0;
			}

			$ids[] = absint( wp_unslash( $source['post_id'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

		$ids = array_unique( $ids );

		return 1 === count( $ids ) ? reset( $ids ) : 0;
	}

	/**
	 * Whether the request carries a valid nonce for this action name.
	 *
	 * @since 1.1.19
	 *
	 * @param string $action Requested action name.
	 * @return bool
	 */
	protected static function verify_request_action_nonce( $action ) {
		$nonce = '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This method is the nonce check.
		if ( isset( $_REQUEST[ self::REQUEST_ACTION_NONCE_ARG ] ) && is_string( $_REQUEST[ self::REQUEST_ACTION_NONCE_ARG ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST[ self::REQUEST_ACTION_NONCE_ARG ] ) );
		}

		return (bool) wp_verify_nonce( $nonce, self::request_action_nonce_action( $action ) );
	}

	/**
	 * Whether the request carries a `_wpnonce` created for the bare action name.
	 *
	 * CP Library 1.8.0 and CP Sync send `wp_create_nonce( $action )` as
	 * `_wpnonce` with their own buttons. Accepting it lets those requests run
	 * without relying on the Origin or Referer header, which browsers omit
	 * under `Referrer-Policy: no-referrer`. Only legacy names use this.
	 *
	 * @since 1.1.19
	 *
	 * @param string $action Requested action name.
	 * @return bool
	 */
	protected static function verify_legacy_wpnonce( $action ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This method is the nonce check.
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! is_string( $_REQUEST['_wpnonce'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );

		return (bool) wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Whether plugins have registered this action for authorized dispatch.
	 *
	 * @since 1.1.19
	 *
	 * @param string $action Requested action name.
	 * @return bool
	 */
	protected static function is_allowed_request_action( $action ) {
		/**
		 * Action names that may be dispatched for an authorized user.
		 *
		 * Plugins append the hook name their callback is registered on.
		 * Unregistered names are ignored.
		 *
		 *     add_filter( 'cp_request_actions', function( $actions ) {
		 *         $actions[] = 'cp_export_items';
		 *         return $actions;
		 *     } );
		 *
		 * @since 1.1.19
		 *
		 * @param string[] $actions Registered action names. Default empty.
		 */
		$actions = apply_filters( 'cp_request_actions', array() );

		return in_array( $action, self::normalize_action_list( $actions ), true );
	}

	/**
	 * Whether this action is on the legacy list.
	 *
	 * These names dispatch for a logged-in user with the required capability
	 * when {@see self::legacy_request_may_run()} is true. A name that is also
	 * registered on `cp_request_actions` is handled there and does require the
	 * core nonce.
	 *
	 * @since 1.1.19
	 *
	 * @param string $action Requested action name.
	 * @return bool
	 */
	protected static function is_legacy_request_action( $action ) {
		/**
		 * Action names that dispatch for an authorized user when the request
		 * carries a valid core nonce or a `_wpnonce` for the action name, or is
		 * a same-site POST.
		 *
		 * Defaults cover the CP Library admin screens that post `cp_action`
		 * directly, including `cp_upload_import_file` for the CSV import on
		 * older CP Library releases. Plugins can remove a name here once they
		 * register it on `cp_request_actions` and send
		 * {@see self::request_action_nonce_field()} or
		 * {@see self::request_action_nonce_url()}.
		 *
		 * @since 1.1.19
		 *
		 * @param string[] $actions Legacy action names.
		 */
		$actions = apply_filters(
			'cp_legacy_request_actions',
			array(
				'cp_export_items',
				'cpl_adapter_import_sermon_audio',
				'cpl_adapter_pull_sermon_audio',
				'cpl_import_transcript',
				'cp_upload_import_file',
			)
		);

		return in_array( $action, self::normalize_action_list( $actions ), true );
	}

	/**
	 * Whether a legacy action may run for this request.
	 *
	 * A valid core nonce for the action, or a `_wpnonce` created for the
	 * action name, is enough on its own, for any request method. Without
	 * either nonce the request must be POST, and then same-site.
	 * Same-site means the Origin header, or the Referer when Origin is absent,
	 * has the same scheme, host, and port as {@see home_url()} or
	 * {@see admin_url()}. A GET or HEAD is refused before that comparison.
	 * Missing headers, or a different scheme, host, or port, also refuse the
	 * request.
	 *
	 * @since 1.1.19
	 *
	 * @param string $action Requested action name.
	 * @return bool
	 */
	protected static function legacy_request_may_run( $action ) {
		if ( self::verify_request_action_nonce( $action ) || self::verify_legacy_wpnonce( $action ) ) {
			return true;
		}

		if ( 'POST' !== self::request_method() ) {
			return false;
		}

		return self::request_is_same_site();
	}

	/**
	 * Request method, or an empty string when it is missing.
	 *
	 * @since 1.1.19
	 *
	 * @return string
	 */
	protected static function request_method() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || ! is_string( $_SERVER['REQUEST_METHOD'] ) ) {
			return '';
		}

		return strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );
	}

	/**
	 * Whether Origin, or Referer when Origin is absent, matches this site.
	 *
	 * Scheme, host, and port are all compared. An omitted port uses the
	 * scheme default (80 for http, 443 for https).
	 *
	 * @since 1.1.19
	 *
	 * @return bool
	 */
	protected static function request_is_same_site() {
		$origin = self::request_header( 'HTTP_ORIGIN' );

		if ( '' !== $origin ) {
			return self::url_matches_site( $origin );
		}

		$referer = self::request_header( 'HTTP_REFERER' );

		if ( '' === $referer ) {
			return false;
		}

		return self::url_matches_site( $referer );
	}

	/**
	 * Whether a URL has the same scheme, host, and port as home_url() or admin_url().
	 *
	 * @since 1.1.19
	 *
	 * @param string $url Absolute URL from a request header.
	 * @return bool
	 */
	protected static function url_matches_site( $url ) {
		$parts = self::url_origin( $url );

		if ( null === $parts ) {
			return false;
		}

		$allowed = array(
			self::url_origin( home_url() ),
			self::url_origin( admin_url() ),
		);

		return in_array( $parts, $allowed, true );
	}

	/**
	 * Scheme, host, and port for a URL, or null when the URL has no scheme or host.
	 *
	 * Host and scheme are lowercase. An omitted port becomes 80 or 443 for
	 * http and https.
	 *
	 * @since 1.1.19
	 *
	 * @param string $url URL to inspect.
	 * @return array|null
	 */
	protected static function url_origin( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}

		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! is_string( $scheme ) || '' === $scheme || ! is_string( $host ) || '' === $host ) {
			return null;
		}

		$scheme = strtolower( $scheme );
		$host   = strtolower( $host );
		$port   = wp_parse_url( $url, PHP_URL_PORT );

		if ( ! is_int( $port ) ) {
			if ( 'http' === $scheme ) {
				$port = 80;
			} elseif ( 'https' === $scheme ) {
				$port = 443;
			} else {
				return null;
			}
		}

		return array(
			'scheme' => $scheme,
			'host'   => $host,
			'port'   => $port,
		);
	}

	/**
	 * Read one request header used for the same-site check.
	 *
	 * @since 1.1.19
	 *
	 * @param string $name `HTTP_ORIGIN` or `HTTP_REFERER`.
	 * @return string
	 */
	protected static function request_header( $name ) {
		if ( 'HTTP_ORIGIN' === $name && isset( $_SERVER['HTTP_ORIGIN'] ) && is_string( $_SERVER['HTTP_ORIGIN'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) );
		}

		if ( 'HTTP_REFERER' === $name && isset( $_SERVER['HTTP_REFERER'] ) && is_string( $_SERVER['HTTP_REFERER'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		return '';
	}

	/**
	 * Whether this action is opted in to run for any visitor.
	 *
	 * The callback must verify its own nonce before it performs work.
	 *
	 * CP Groups (`cp_send_email`) and CP Staff (`cp_staff_send_email`) are
	 * registered by default. Both post a front-end email form to admin-ajax.php
	 * and already verify a form nonce in the callback.
	 *
	 * @since 1.1.19
	 *
	 * @param string $action Requested action name.
	 * @return bool
	 */
	protected static function is_public_request_action( $action ) {
		/**
		 * Action names that dispatch for any visitor.
		 *
		 * Each callback must verify its own nonce. Core does not check the
		 * cp_action nonce or the request-action capability for these names.
		 *
		 * @since 1.1.19
		 *
		 * @param string[] $actions Public action names.
		 */
		$actions = apply_filters(
			'cp_public_request_actions',
			array(
				'cp_send_email',
				'cp_staff_send_email',
			)
		);

		return in_array( $action, self::normalize_action_list( $actions ), true );
	}

	/**
	 * Sanitize a filtered list of action names.
	 *
	 * @since 1.1.19
	 *
	 * @param mixed $actions Filter value.
	 * @return string[]
	 */
	protected static function normalize_action_list( $actions ) {
		if ( ! is_array( $actions ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $actions as $action ) {
			if ( ! is_string( $action ) ) {
				continue;
			}

			$key = sanitize_key( $action );

			if ( '' !== $key ) {
				$normalized[] = $key;
			}
		}

		return $normalized;
	}


}
