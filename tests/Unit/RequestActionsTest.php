<?php
/**
 * Request action dispatch.
 *
 * @package ChurchPlugins
 */

namespace ChurchPlugins\Tests;

use ChurchPlugins\Admin\_Init;
use PHPUnit\Framework\TestCase;

/**
 * Subclass that skips file includes and hook registration.
 */
class Request_Actions_Harness extends _Init {

	/**
	 * Skip the parent constructor.
	 */
	public function __construct() {}
}

/**
 * @covers \ChurchPlugins\Admin\_Init::request_actions
 */
class RequestActionsTest extends TestCase {

	/**
	 * Reset request and user state before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['cp_test_actions']      = array();
		$GLOBALS['cp_test_filters']      = array();
		$GLOBALS['cp_test_doing_action'] = array();
		$GLOBALS['cp_test_user']         = array(
			'logged_in' => false,
			'caps'      => array(),
		);

		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
	}

	/**
	 * A logged-out request dispatches nothing.
	 */
	public function test_logged_out_request_dispatches_nothing() {
		$this->allow_actions( array( 'cp_export_items' ) );
		$this->set_request( 'cp_export_items', $this->nonce_for( 'cp_export_items' ) );

		$this->dispatch();

		$this->assertSame( array(), $this->dispatched_hooks() );
	}

	/**
	 * A subscriber dispatches nothing.
	 */
	public function test_subscriber_dispatches_nothing() {
		$this->set_user( true, array( 'read' ) );
		$this->allow_actions( array( 'cp_export_items' ) );
		$this->set_request( 'cp_export_items', $this->nonce_for( 'cp_export_items' ) );

		$this->dispatch();

		$this->assertSame( array(), $this->dispatched_hooks() );
	}

	/**
	 * A missing nonce dispatches nothing.
	 */
	public function test_missing_nonce_dispatches_nothing() {
		$this->set_user( true, array( 'manage_options' ) );
		$this->allow_actions( array( 'cp_custom_tool' ) );
		$this->set_request( 'cp_custom_tool', null );

		$this->dispatch();

		$this->assertSame( array(), $this->dispatched_hooks() );
	}

	/**
	 * An unregistered hook name dispatches nothing.
	 */
	public function test_unregistered_hook_name_dispatches_nothing() {
		$this->set_user( true, array( 'manage_options' ) );
		$this->allow_actions( array( 'cp_export_items' ) );
		$this->set_request( 'init', $this->nonce_for( 'init' ) );

		$this->dispatch();

		$this->assertSame( array(), $this->dispatched_hooks() );
	}

	/**
	 * An admin with a nonce and a registered action dispatches.
	 */
	public function test_admin_with_nonce_and_registered_action_dispatches() {
		$this->set_user( true, array( 'manage_options' ) );
		$this->allow_actions( array( 'cp_export_items' ) );
		$this->set_request( 'cp_export_items', $this->nonce_for( 'cp_export_items' ), 'POST' );

		$this->dispatch();

		$this->assertSame( array( 'cp_export_items' ), $this->dispatched_hooks() );
		$this->assertSame( 'cp_export_items', $GLOBALS['cp_test_actions'][0]['arg']['cp_action'] );
	}

	/**
	 * A legacy name dispatches for an admin with no nonce.
	 */
	public function test_legacy_action_dispatches_for_admin_without_nonce() {
		$this->set_user( true, array( 'manage_options' ) );
		$this->set_request( 'cp_export_items', null );

		$this->dispatch();

		$this->assertSame( array( 'cp_export_items' ), $this->dispatched_hooks() );
	}

	/**
	 * A legacy name dispatches nothing for a logged-out visitor.
	 */
	public function test_legacy_action_dispatches_nothing_for_logged_out_visitor() {
		$this->set_request( 'cpl_import_transcript', null );

		$this->dispatch();

		$this->assertSame( array(), $this->dispatched_hooks() );
	}

	/**
	 * A legacy name dispatches nothing for a subscriber.
	 */
	public function test_legacy_action_dispatches_nothing_for_subscriber() {
		$this->set_user( true, array( 'read' ) );
		$this->set_request( 'cpl_adapter_import_sermon_audio', null );

		$this->dispatch();

		$this->assertSame( array(), $this->dispatched_hooks() );
	}

	/**
	 * Registering a legacy name on the allowlist requires the core nonce.
	 */
	public function test_allowlisted_legacy_action_requires_nonce() {
		$this->set_user( true, array( 'manage_options' ) );
		$this->allow_actions( array( 'cp_export_items' ) );
		$this->set_request( 'cp_export_items', null );

		$this->dispatch();

		$this->assertSame( array(), $this->dispatched_hooks() );
	}

	/**
	 * A nonce minted for a different action does not authorize this one.
	 */
	public function test_nonce_for_a_different_action_dispatches_nothing() {
		$this->set_user( true, array( 'manage_options' ) );
		$this->allow_actions( array( 'cp_export_items', 'cpl_import_transcript' ) );
		$this->set_request( 'cp_export_items', $this->nonce_for( 'cpl_import_transcript' ) );

		$this->dispatch();

		$this->assertSame( array(), $this->dispatched_hooks() );
	}

	/**
	 * An editor with a valid core nonce can run the transcript import action.
	 */
	public function test_editor_with_nonce_can_run_transcript_import() {
		$this->set_user( true, array( 'edit_posts' ) );
		$this->allow_actions( array( 'cpl_import_transcript' ) );
		$this->set_request( 'cpl_import_transcript', $this->nonce_for( 'cpl_import_transcript' ) );

		$this->dispatch();

		$this->assertSame( array( 'cpl_import_transcript' ), $this->dispatched_hooks() );
	}

	/**
	 * A subscriber cannot run the transcript import action.
	 */
	public function test_subscriber_cannot_run_transcript_import() {
		$this->set_user( true, array( 'read' ) );
		$this->allow_actions( array( 'cpl_import_transcript' ) );
		$this->set_request( 'cpl_import_transcript', $this->nonce_for( 'cpl_import_transcript' ) );

		$this->dispatch();

		$this->assertSame( array(), $this->dispatched_hooks() );
	}

	/**
	 * A transcript import request without a nonce does not dispatch once the action is registered.
	 */
	public function test_transcript_import_without_nonce_dispatches_nothing() {
		$this->set_user( true, array( 'edit_posts' ) );
		$this->allow_actions( array( 'cpl_import_transcript' ) );
		$this->set_request( 'cpl_import_transcript', null );

		$this->dispatch();

		$this->assertSame( array(), $this->dispatched_hooks() );
	}

	/**
	 * An editor can run the transcript import action CP Library posts today.
	 */
	public function test_editor_can_run_legacy_transcript_import_without_core_nonce() {
		$this->set_user( true, array( 'edit_posts' ) );
		$this->set_request( 'cpl_import_transcript', null );

		$this->dispatch();

		$this->assertSame( array( 'cpl_import_transcript' ), $this->dispatched_hooks() );
	}

	/**
	 * Other legacy actions still require manage_options.
	 */
	public function test_editor_cannot_run_other_legacy_actions() {
		$this->set_user( true, array( 'edit_posts' ) );
		$this->set_request( 'cp_export_items', null );

		$this->dispatch();

		$this->assertSame( array(), $this->dispatched_hooks() );
	}

	/**
	 * The capability filter can grant a role other than the default.
	 */
	public function test_capability_filter_allows_a_user_who_has_the_filtered_capability() {
		$this->set_user( true, array( 'edit_posts' ) );
		$this->allow_actions( array( 'cpl_import_transcript' ) );
		$GLOBALS['cp_test_filters']['cp_request_action_capability'] = function ( $capability, $action ) {
			if ( 'cpl_import_transcript' === $action ) {
				return 'edit_posts';
			}
			return $capability;
		};
		$this->set_request( 'cpl_import_transcript', $this->nonce_for( 'cpl_import_transcript' ) );

		$this->dispatch();

		$this->assertSame( array( 'cpl_import_transcript' ), $this->dispatched_hooks() );
	}

	/**
	 * A registered public action dispatches for a logged-out visitor.
	 */
	public function test_registered_public_action_dispatches_for_logged_out_visitor() {
		$_GET['cp_action']         = 'cp_send_email';
		$_POST['from-name']        = 'Ada';
		$_REQUEST['cp_action']     = 'cp_send_email';
		$_REQUEST['from-name']     = 'Ada';

		$this->dispatch();

		$this->assertSame( array( 'cp_send_email' ), $this->dispatched_hooks() );
	}

	/**
	 * The CP Staff email form is registered alongside the CP Groups form.
	 */
	public function test_staff_email_action_dispatches_for_logged_out_visitor() {
		$_GET['cp_action']     = 'cp_staff_send_email';
		$_REQUEST['cp_action'] = 'cp_staff_send_email';

		$this->dispatch();

		$this->assertSame( array( 'cp_staff_send_email' ), $this->dispatched_hooks() );
	}

	/**
	 * A plugin can register an additional public action.
	 */
	public function test_filtered_public_action_dispatches_for_logged_out_visitor() {
		$GLOBALS['cp_test_filters']['cp_public_request_actions'] = function ( $actions ) {
			$actions[] = 'my_public_form';
			return $actions;
		};
		$_REQUEST['cp_action'] = 'my_public_form';
		$_GET['cp_action']     = 'my_public_form';

		$this->dispatch();

		$this->assertSame( array( 'my_public_form' ), $this->dispatched_hooks() );
	}

	/**
	 * Removing the default public actions leaves those names undispatched.
	 */
	public function test_public_action_removed_by_filter_dispatches_nothing() {
		$GLOBALS['cp_test_filters']['cp_public_request_actions'] = function () {
			return array();
		};
		$_REQUEST['cp_action'] = 'cp_send_email';
		$_GET['cp_action']     = 'cp_send_email';

		$this->dispatch();

		$this->assertSame( array(), $this->dispatched_hooks() );
	}

	/**
	 * A hook that is already running is not dispatched again.
	 */
	public function test_action_already_running_dispatches_nothing() {
		$this->set_user( true, array( 'manage_options' ) );
		$this->allow_actions( array( 'init' ) );
		$this->set_request( 'init', $this->nonce_for( 'init' ) );
		$GLOBALS['cp_test_doing_action']['init'] = true;

		$this->dispatch();

		$this->assertSame( array(), $this->dispatched_hooks() );
	}

	/**
	 * The field helper returns a hidden input named cp_action_nonce.
	 */
	public function test_nonce_field_helper_includes_action_nonce() {
		$html = _Init::request_action_nonce_field( 'cp_export_items' );

		$this->assertStringContainsString( 'name="cp_action_nonce"', $html );
		$this->assertStringContainsString( $this->nonce_for( 'cp_export_items' ), $html );
	}

	/**
	 * The URL helper appends the nonce query argument.
	 */
	public function test_nonce_url_helper_appends_nonce_arg() {
		$url = _Init::request_action_nonce_url(
			'https://example.test/wp-admin/admin-post.php?cp_action=cp_export_items',
			'cp_export_items'
		);

		$this->assertStringContainsString(
			'cp_action_nonce=' . rawurlencode( $this->nonce_for( 'cp_export_items' ) ),
			$url
		);
	}

	/**
	 * @param string[] $actions Allowlisted action names.
	 */
	private function allow_actions( array $actions ) {
		$GLOBALS['cp_test_filters']['cp_request_actions'] = function () use ( $actions ) {
			return $actions;
		};
	}

	/**
	 * @param bool     $logged_in Whether the visitor is logged in.
	 * @param string[] $caps      Capabilities the user has.
	 */
	private function set_user( $logged_in, array $caps ) {
		$GLOBALS['cp_test_user'] = array(
			'logged_in' => $logged_in,
			'caps'      => $caps,
		);
	}

	/**
	 * @param string      $action Action name.
	 * @param string|null $nonce  Nonce value, or null when the request has none.
	 * @param string      $method GET or POST.
	 */
	private function set_request( $action, $nonce, $method = 'GET' ) {
		$query = array(
			'cp_action' => $action,
		);

		if ( null !== $nonce ) {
			$query[ _Init::REQUEST_ACTION_NONCE_ARG ] = $nonce;
		}

		$_GET     = array();
		$_POST    = array();
		$_REQUEST = $query;

		if ( 'POST' === $method ) {
			$_POST = $query;
		} else {
			$_GET = $query;
		}
	}

	/**
	 * @param string $action Action name.
	 * @return string
	 */
	private function nonce_for( $action ) {
		return wp_create_nonce( _Init::request_action_nonce_action( $action ) );
	}

	/**
	 * Run the request handler.
	 */
	private function dispatch() {
		$init = new Request_Actions_Harness();
		$init->request_actions();
	}

	/**
	 * @return string[]
	 */
	private function dispatched_hooks() {
		return array_column( $GLOBALS['cp_test_actions'], 'hook' );
	}
}
