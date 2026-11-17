<?php
/**
 * The shared Licenses screen.
 *
 * @package ChurchPlugins
 */

namespace ChurchPlugins\Admin;

use ChurchPlugins\Setup\Admin\License;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Church Plugins → Licenses: one row per paid product.
 *
 * Every {@see License} registers itself here when it is constructed, so
 * products need no change to appear. A row shows the product's existing
 * license form: for the products whose License tab was a CMB2 options page
 * (`cpl_license`, `cp_groups_license`, …) it is that same box, posted to
 * the same `admin-post.php` handler with the same fields and nonce; for a
 * product without one (CP Sync) it uses the framework's existing license
 * REST routes. Activation, deactivation, the daily check and updates are
 * unchanged; only where the form is shown moved.
 *
 * The products' old License tabs are taken out of their settings tabs and
 * their URLs (`admin.php?page=cpl_license`, the License's own redirect
 * target after a failed activation) forward here, keeping `sl_activation`
 * and `message` so the error still shows.
 *
 * Host contract: a hub such as CP Essentials can show these rows on its own
 * screen. It returns its page slug from `cp_admin_licenses_page`, registers
 * that page under the shared menu, and calls
 * `\ChurchPlugins\Admin\Licenses::get_instance()->render_rows()` where the
 * rows go. The framework then adds no Licenses screen of its own and old
 * URLs forward to the host's page.
 *
 * @since 1.1.23
 */
class Licenses {

	/**
	 * Single instance.
	 *
	 * @var Licenses
	 */
	protected static $instance;

	/**
	 * Registered licenses, by id (the option name, e.g. `cpl_license`).
	 *
	 * @var License[]
	 */
	protected static $licenses = array();

	/**
	 * Tab group the license boxes are moved to, out of their product's tabs.
	 */
	const TAB_GROUP = 'cp_licenses';

	/**
	 * Get the single instance.
	 *
	 * @return Licenses
	 */
	public static function get_instance() {
		if ( ! self::$instance instanceof Licenses ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hooks.
	 */
	protected function __construct() {
		add_action( 'admin_init', array( $this, 'redirect_old_screens' ), 1 );
	}

	/**
	 * Register a license. Called by License's constructor.
	 *
	 * @param License $license License.
	 * @return void
	 */
	public static function register( $license ) {
		if ( is_object( $license ) && method_exists( $license, 'get_id' ) ) {
			self::$licenses[ (string) $license->get_id() ] = $license;
		}
	}

	/**
	 * Registered licenses.
	 *
	 * @return License[]
	 */
	public static function all() {
		return self::$licenses;
	}

	/**
	 * Registered license ids.
	 *
	 * @return string[]
	 */
	public static function ids() {
		return array_map( 'strval', array_keys( self::$licenses ) );
	}

	/**
	 * Whether any product registered a license.
	 *
	 * @return bool
	 */
	public static function has_any() {
		return ! empty( self::$licenses );
	}

	/**
	 * Slug of the page that shows the rows.
	 *
	 * @return string
	 */
	public static function page_slug() {
		/**
		 * Filters the slug of the page that shows the license rows. A host
		 * (CP Essentials) returns its own page's slug.
		 *
		 * @param string $slug Default `cp-licenses`.
		 * @since 1.1.23
		 */
		$slug = apply_filters( 'cp_admin_licenses_page', Menu::LICENSES_SLUG );

		return is_string( $slug ) && '' !== $slug ? $slug : Menu::LICENSES_SLUG;
	}

	/**
	 * URL of the Licenses screen.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'admin.php?page=' . self::page_slug() );
	}

	/**
	 * Forward the old License tabs to the Licenses screen (GET only).
	 *
	 * @return void
	 */
	public function redirect_old_screens() {
		global $pagenow;

		if ( ! Menu::enabled() || ! self::has_any() || wp_doing_ajax() ) {
			return;
		}

		if ( ! in_array( $pagenow, array( 'admin.php', 'options-general.php', 'edit.php' ), true ) ) {
			return;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

		if ( 'GET' !== $method ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only reads which screen was asked for, and forwards the display arguments.
		$page  = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$query = array();

		foreach ( array( 'sl_activation', 'message', 'settings-updated' ) as $arg ) {
			if ( isset( $_GET[ $arg ] ) && is_string( $_GET[ $arg ] ) ) {
				$query[ $arg ] = sanitize_text_field( wp_unslash( $_GET[ $arg ] ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$url = MenuLayout::license_redirect( $page, self::ids(), $query, self::url() );

		if ( '' === $url ) {
			return;
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * While the menu is built: take the license boxes out of their product's
	 * settings tabs.
	 *
	 * @return void
	 */
	public function prepare_menu() {
		foreach ( $this->boxes() as $cmb ) {
			$cmb->set_prop( 'tab_group', self::TAB_GROUP );
		}
	}

	/**
	 * Add the Licenses screen, unless a host provides it.
	 *
	 * @return void
	 */
	public function add_page() {
		if ( ! self::has_any() || Menu::LICENSES_SLUG !== self::page_slug() ) {
			return;
		}

		add_submenu_page(
			Menu::SLUG,
			__( 'Licenses', 'churchplugins' ),
			__( 'Licenses', 'churchplugins' ),
			'manage_options',
			Menu::LICENSES_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Whether any product had a License tab of its own that now lives here.
	 *
	 * @return bool
	 */
	public function has_moved_tabs() {
		return (bool) $this->boxes();
	}

	/**
	 * CMB2 boxes that are a License tab, by license id.
	 *
	 * @return \CMB2[]
	 */
	protected function boxes() {
		$boxes = array();

		if ( ! class_exists( '\CMB2_Boxes' ) ) {
			return $boxes;
		}

		$ids = self::ids();

		foreach ( \CMB2_Boxes::get_all() as $cmb ) {
			if ( ! in_array( 'options-page', (array) $cmb->prop( 'object_types' ), true ) ) {
				continue;
			}

			$keys = $cmb->options_page_keys();

			if ( ! empty( $keys[0] ) && in_array( (string) $keys[0], $ids, true ) ) {
				$boxes[ (string) $keys[0] ] = $cmb;
			}
		}

		return $boxes;
	}

	/**
	 * Licenses in menu order, with the product each belongs to.
	 *
	 * @return array[] [ { license, name, version, box } ]
	 */
	public function rows() {
		$rows  = array();
		$boxes = $this->boxes();

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( self::$licenses as $id => $license ) {
			$file = $license->get_plugin_file();
			$data = $file && is_readable( $file ) ? get_plugin_data( $file, false, false ) : array();
			$slug = $file ? basename( dirname( $file ) ) : '';

			$rows[] = array(
				'id'       => (string) $id,
				'license'  => $license,
				'name'     => ! empty( $data['Name'] ) ? $data['Name'] : $id,
				'version'  => ! empty( $data['Version'] ) ? $data['Version'] : '',
				'box'      => isset( $boxes[ $id ] ) ? $boxes[ $id ] : null,
				'position' => self::position( $slug ),
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return $a['position'] === $b['position'] ? strcmp( $a['name'], $b['name'] ) : $a['position'] - $b['position'];
			}
		);

		return $rows;
	}

	/**
	 * Menu position of a product, for ordering rows.
	 *
	 * @param string $product Product key (plugin folder).
	 * @return int
	 */
	protected static function position( $product ) {
		$products = Menu::products();

		return isset( $products[ $product ]['position'] ) ? (int) $products[ $product ]['position'] : MenuLayout::DEFAULT_SLOT;
	}

	/**
	 * Print the Licenses screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap cp-licenses">';
		echo '<h1>' . esc_html__( 'Licenses', 'churchplugins' ) . '</h1>';
		echo '<p>' . esc_html__( 'Enter the license key for each Church Plugins product to receive updates and support.', 'churchplugins' ) . '</p>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'License settings saved.', 'churchplugins' ) . '</p></div>';
		}

		$this->render_rows();

		echo '</div>';
	}

	/**
	 * Print one row per license.
	 *
	 * @return void
	 */
	public function render_rows() {
		echo '<style>.cp-licenses__row{background:#fff;border:1px solid #c3c4c7;margin:16px 0;max-width:960px}.cp-licenses__head{display:flex;gap:12px;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #f0f0f1}.cp-licenses__head h2{margin:0;font-size:15px}.cp-licenses__body{padding:0 16px 8px}.cp-licenses__body .cmb2-wrap .cmb-row{padding:12px 0}.cp-licenses__status{border-radius:2px;padding:2px 8px;font-size:12px;background:#f0f0f1;color:#50575e}.cp-licenses__status.is-valid{background:#edfaef;color:#00650f}.cp-licenses__status.is-problem{background:#fcf0f1;color:#8a2424}.cp-licenses__rest{display:flex;gap:8px;align-items:center;padding:12px 0}.cp-licenses__rest input{min-width:280px}</style>';

		foreach ( $this->rows() as $row ) {
			$license = $row['license'];
			$status  = (string) $license->get( 'status' );

			printf( '<section class="cp-licenses__row" id="%s">', esc_attr( $row['id'] ) );
			echo '<div class="cp-licenses__head"><h2>' . esc_html( $row['name'] );

			if ( $row['version'] ) {
				echo ' <span class="description">' . esc_html( $row['version'] ) . '</span>';
			}

			echo '</h2>' . self::status_badge( $license->is_active(), $status, (string) $license->get( 'license' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in status_badge().
			echo '<div class="cp-licenses__body">';

			if ( $row['box'] ) {
				$this->render_box( $row['box'], $row['id'] );
			} else {
				$this->render_rest_form( $license, $row['id'] );
			}

			echo '</div></section>';
		}
	}

	/**
	 * Status badge.
	 *
	 * @param bool   $active Whether the license is active.
	 * @param string $status Stored status.
	 * @param string $key    Stored key.
	 * @return string
	 */
	protected static function status_badge( $active, $status, $key ) {
		if ( $active ) {
			return '<span class="cp-licenses__status is-valid">' . esc_html__( 'Active', 'churchplugins' ) . '</span>';
		}

		if ( '' === $key ) {
			return '<span class="cp-licenses__status">' . esc_html__( 'No license key', 'churchplugins' ) . '</span>';
		}

		$labels = array(
			'expired'       => __( 'Expired', 'churchplugins' ),
			'disabled'      => __( 'Disabled', 'churchplugins' ),
			'revoked'       => __( 'Disabled', 'churchplugins' ),
			'invalid'       => __( 'Invalid', 'churchplugins' ),
			'site_inactive' => __( 'Not active for this site', 'churchplugins' ),
			'deactivated'   => __( 'Not activated', 'churchplugins' ),
			'inactive'      => __( 'Not activated', 'churchplugins' ),
		);

		$label = isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Not activated', 'churchplugins' );
		$class = in_array( $status, array( 'expired', 'disabled', 'revoked', 'invalid' ), true ) ? ' is-problem' : '';

		return '<span class="cp-licenses__status' . $class . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * The product's own CMB2 license box, in the same form its License tab
	 * printed: posted to admin-post.php with `action` = the option key, so
	 * CMB2 saves the key and the License's admin_init handlers see the
	 * Activate / Deactivate buttons exactly as before.
	 *
	 * @param \CMB2  $cmb CMB2 box.
	 * @param string $id  License id (option key).
	 * @return void
	 */
	protected function render_box( $cmb, $id ) {
		if ( method_exists( '\CMB2_Hookup', 'enqueue_cmb_css' ) ) {
			\CMB2_Hookup::enqueue_cmb_css();
		}

		printf(
			'<form class="cmb-form" action="%1$s" method="POST" id="%2$s" enctype="multipart/form-data" encoding="multipart/form-data"><input type="hidden" name="action" value="%3$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $cmb->cmb_id ),
			esc_attr( $id )
		);

		$cmb->show_form( $id, 'options-page' );

		submit_button( __( 'Save', 'churchplugins' ), 'secondary', 'submit-cmb', true, array( 'id' => 'submit-cmb-' . $id ) );
		echo '</form>';
	}

	/**
	 * A key field for a product without a CMB2 license box, using the
	 * License's own REST routes (`churchplugins/v1/license/<id>`), the same
	 * routes CP Sync's License tab calls.
	 *
	 * @param License $license License.
	 * @param string  $id      License id.
	 * @return void
	 */
	protected function render_rest_form( $license, $id ) {
		wp_enqueue_script( 'wp-api-fetch' );

		$active = $license->is_active();

		printf(
			'<div class="cp-licenses__rest" data-cp-license="%1$s"><label class="screen-reader-text" for="cp-license-%1$s">%2$s</label><input type="text" class="regular-text" id="cp-license-%1$s" value="%3$s"%4$s> <button type="button" class="button %5$s" data-cp-license-action="%6$s">%7$s</button> <span class="cp-licenses__message" role="status"></span></div>',
			esc_attr( $id ),
			esc_html__( 'License key', 'churchplugins' ),
			esc_attr( (string) $license->get( 'license' ) ),
			$active ? ' disabled' : '',
			$active ? 'button-secondary' : 'button-primary',
			$active ? 'deactivate' : 'activate',
			$active ? esc_html__( 'Deactivate License', 'churchplugins' ) : esc_html__( 'Activate License', 'churchplugins' )
		);

		static $printed = false;

		if ( $printed ) {
			return;
		}

		$printed = true;

		$script = <<<'JS'
document.addEventListener('click', function (event) {
	var button = event.target.closest('[data-cp-license-action]');
	if (!button || !window.wp || !wp.apiFetch) { return; }
	var row = button.closest('[data-cp-license]');
	var id = row.getAttribute('data-cp-license');
	var input = row.querySelector('input');
	var message = row.querySelector('.cp-licenses__message');
	var activate = 'activate' === button.getAttribute('data-cp-license-action');
	button.disabled = true;
	message.textContent = '';
	wp.apiFetch({ path: '/churchplugins/v1/license/' + id, method: activate ? 'POST' : 'DELETE', data: activate ? { license: input.value } : undefined })
		.then(function () { window.location.reload(); })
		.catch(function (error) { message.textContent = (error && error.message) || ''; button.disabled = false; });
});
JS;

		wp_add_inline_script( 'wp-api-fetch', $script );
	}
}
