<?php
/**
 * One-time notice that the menus moved.
 *
 * @package ChurchPlugins
 */

namespace ChurchPlugins\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Tells people who used the old menus that they moved under Church Plugins.
 *
 * The first time the shared menu runs on a site, the site is recorded as an
 * existing install (it already has product content, saved product settings
 * or a license key) or a fresh one. Only existing installs get the notice,
 * and only users who could open the old menus and were registered before
 * the move. Each user dismisses it once (per site).
 *
 * @since 1.1.23
 */
class MenuNotice {

	/**
	 * Option recording when the shared menu arrived and whether the site
	 * was already in use: { version, since, existing }.
	 */
	const OPTION = 'cp_admin_menu_layout';

	/**
	 * User option set when a user dismisses the notice.
	 */
	const DISMISSED = 'cp_admin_menu_notice_dismissed';

	/**
	 * AJAX action and nonce action for dismissing.
	 */
	const ACTION = 'cp_dismiss_menu_notice';

	/**
	 * Single instance.
	 *
	 * @var MenuNotice
	 */
	protected static $instance;

	/**
	 * Get the single instance.
	 *
	 * @return MenuNotice
	 */
	public static function get_instance() {
		if ( ! self::$instance instanceof MenuNotice ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hooks.
	 */
	protected function __construct() {
		add_action( 'admin_init', array( $this, 'record_state' ) );
		add_action( 'admin_init', array( $this, 'dismiss_from_link' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'dismiss_from_ajax' ) );
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Record, once per site, when the shared menu arrived and whether the
	 * site was already in use.
	 *
	 * @return void
	 */
	public function record_state() {
		if ( ! Menu::enabled() || false !== get_option( self::OPTION, false ) ) {
			return;
		}

		$products = Menu::products();

		if ( ! $this->any_product_active( $products ) ) {
			return;
		}

		add_option(
			self::OPTION,
			array(
				'version'  => defined( 'CHURCHPLUGINS_VERSION' ) ? CHURCHPLUGINS_VERSION : '',
				'since'    => time(),
				'existing' => MenuLayout::is_existing_install( $this->content_count( $products ), $this->has_license_key(), $this->has_saved_settings( $products ), self::essentials_active() ),
			)
		);
	}

	/**
	 * Whether CP Essentials is active. It has no framework inside and shares
	 * no code with it, so it is recognised by what its main file declares.
	 *
	 * @return bool
	 * @since 1.1.23
	 */
	protected static function essentials_active() {
		$active = defined( 'CP_ESSENTIALS_VERSION' ) || function_exists( 'cp_essentials' );

		/**
		 * Filters whether CP Essentials counts as active when the shared menu
		 * first records whether a site was already in use. While it is, the
		 * site is not treated as an existing install and gets no "menus moved"
		 * notice.
		 *
		 * @param bool $active Whether CP Essentials is active.
		 * @since 1.1.23
		 */
		return (bool) apply_filters( 'cp_admin_menu_essentials_active', $active );
	}

	/**
	 * Whether a product the shared menu moves is active (it registered a
	 * post type or a license).
	 *
	 * @param array $products Product table.
	 * @return bool
	 */
	protected function any_product_active( array $products ) {
		if ( Licenses::has_any() ) {
			return true;
		}

		foreach ( $products as $product ) {
			foreach ( MenuLayout::product_post_types( $product ) as $post_type ) {
				if ( post_type_exists( $post_type ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Posts in the products' post types, any status but auto-draft.
	 *
	 * @param array $products Product table.
	 * @return int
	 */
	protected function content_count( array $products ) {
		$count = 0;

		foreach ( $products as $product ) {
			foreach ( MenuLayout::product_post_types( $product ) as $post_type ) {
				if ( ! post_type_exists( $post_type ) ) {
					continue;
				}

				foreach ( (array) wp_count_posts( $post_type ) as $status => $number ) {
					if ( 'auto-draft' !== $status ) {
						$count += (int) $number;
					}
				}
			}
		}

		return $count;
	}

	/**
	 * Whether any product has a license key saved.
	 *
	 * @return bool
	 */
	protected function has_license_key() {
		foreach ( Licenses::all() as $license ) {
			if ( '' !== (string) $license->get( 'license' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether any product's main settings were ever saved.
	 *
	 * @param array $products Product table.
	 * @return bool
	 */
	protected function has_saved_settings( array $products ) {
		foreach ( $products as $product ) {
			if ( ! empty( $product['settings'] ) && false !== get_option( $product['settings'], false ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the notice is shown to the current user.
	 *
	 * @return bool
	 */
	public function applies() {
		if ( ! Menu::enabled() ) {
			return false;
		}

		$state = get_option( self::OPTION, array() );

		if ( ! is_array( $state ) || empty( $state['existing'] ) ) {
			return false;
		}

		$menu         = Menu::get_instance();
		$saw_content  = $menu->areas() && current_user_can( 'edit_posts' );
		// Only settings or License tabs that actually moved count: a site with
		// only CP Sync was already under this menu.
		$saw_settings = ( $menu->settings_tabs() || Licenses::get_instance()->has_moved_tabs() ) && current_user_can( 'manage_options' );
		$user         = wp_get_current_user();
		$registered   = $user && $user->user_registered ? strtotime( $user->user_registered . ' UTC' ) : 0;

		return MenuLayout::notice_applies( $state, (int) $registered, (bool) $saw_content, (bool) $saw_settings, (bool) get_user_option( self::DISMISSED ) );
	}

	/**
	 * Mark the notice dismissed for the current user on this site.
	 *
	 * @return void
	 */
	protected function dismiss() {
		update_user_option( get_current_user_id(), self::DISMISSED, 1 );
	}

	/**
	 * Dismiss from the notice's link (works without JavaScript).
	 *
	 * @return void
	 */
	public function dismiss_from_link() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified below.
		if ( empty( $_GET[ self::ACTION ] ) ) {
			return;
		}

		check_admin_referer( self::ACTION );
		$this->dismiss();

		wp_safe_redirect( remove_query_arg( array( self::ACTION, '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Dismiss from the notice's close button.
	 *
	 * @return void
	 */
	public function dismiss_from_ajax() {
		check_ajax_referer( self::ACTION );
		$this->dismiss();
		wp_send_json_success();
	}

	/**
	 * Print the notice.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! $this->applies() ) {
			return;
		}

		$names = array();

		foreach ( Menu::get_instance()->areas() as $area ) {
			$names[] = MenuLayout::strip_count( $area['label'] );
		}

		$what = $names ? wp_sprintf( '%l', $names ) : __( 'Your Church Plugins screens', 'churchplugins' );

		$dismiss_url = wp_nonce_url( add_query_arg( self::ACTION, '1' ), self::ACTION );

		printf(
			'<div class="notice notice-info is-dismissible cp-menu-moved-notice" data-nonce="%1$s"><p><strong>%2$s</strong></p><p>%3$s</p><p><a class="button button-primary" href="%4$s">%5$s</a> <a class="button-link" href="%6$s">%7$s</a></p></div>',
			esc_attr( wp_create_nonce( self::ACTION ) ),
			esc_html__( 'Church Plugins has moved to one menu', 'churchplugins' ),
			esc_html(
				sprintf(
					/* translators: %s: list of screens, such as "Messages, Groups and Staff". */
					__( '%s are now under Church Plugins in the admin menu. Related screens, like categories and speakers, are tabs across the top of each screen. Settings for every product are under Church Plugins → Settings, and license keys under Church Plugins → Licenses. Your content, settings and saved links are unchanged.', 'churchplugins' ),
					$what
				)
			),
			esc_url( Menu::get_instance()->first_url() ),
			esc_html__( 'Show me', 'churchplugins' ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'churchplugins' )
		);

		$script = "document.addEventListener('click',function(e){var b=e.target.closest('.cp-menu-moved-notice .notice-dismiss');if(!b){return;}var n=b.closest('.cp-menu-moved-notice');var d=new FormData();d.append('action','" . esc_js( self::ACTION ) . "');d.append('_ajax_nonce',n.getAttribute('data-nonce'));fetch(window.ajaxurl,{method:'POST',credentials:'same-origin',body:d});});";

		echo '<script>' . $script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static script, action escaped.
	}
}
