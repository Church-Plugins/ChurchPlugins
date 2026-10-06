<?php
/**
 * WordPress-free bootstrap for request-action tests.
 *
 * Defines the handful of WordPress functions Admin\_Init calls, then loads that
 * class directly. The suite does not boot WordPress.
 *
 * @package ChurchPlugins
 */

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Strip slashes from a string or an array of request values.
	 *
	 * @param mixed $value Value to unslash.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}

		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param mixed $key Key to sanitize.
	 * @return string
	 */
	function sanitize_key( $key ) {
		if ( ! is_string( $key ) && ! is_numeric( $key ) ) {
			return '';
		}

		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param mixed $str Text to sanitize.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		if ( ! is_string( $str ) ) {
			return '';
		}

		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	/**
	 * @return bool
	 */
	function is_user_logged_in() {
		return ! empty( $GLOBALS['cp_test_user']['logged_in'] );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * @param string $cap Capability name.
	 * @return bool
	 */
	function current_user_can( $cap ) {
		$caps = isset( $GLOBALS['cp_test_user']['caps'] ) ? $GLOBALS['cp_test_user']['caps'] : array();
		return in_array( $cap, $caps, true );
	}
}

if ( ! function_exists( 'doing_action' ) ) {
	/**
	 * @param string|null $hook Hook name.
	 * @return bool
	 */
	function doing_action( $hook = null ) {
		if ( null === $hook ) {
			return false;
		}

		return ! empty( $GLOBALS['cp_test_doing_action'][ $hook ] );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * @param string $action Nonce action.
	 * @return string
	 */
	function wp_create_nonce( $action ) {
		return 'nonce-' . $action;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * @param mixed  $nonce  Nonce from the request.
	 * @param string $action Expected nonce action.
	 * @return int|false
	 */
	function wp_verify_nonce( $nonce, $action ) {
		if ( ! is_string( $nonce ) || ! is_string( $action ) ) {
			return false;
		}

		return hash_equals( wp_create_nonce( $action ), $nonce ) ? 1 : false;
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * @param string $action  Nonce action.
	 * @param string $name    Field name.
	 * @param bool   $referer Whether to include the referer field.
	 * @param bool   $display Whether to echo the markup.
	 * @return string
	 */
	function wp_nonce_field( $action = '-1', $name = '_wpnonce', $referer = true, $display = true ) {
		$html = '<input type="hidden" name="' . $name . '" value="' . wp_create_nonce( $action ) . '" />';

		if ( $referer ) {
			$html .= '<input type="hidden" name="_wp_http_referer" value="" />';
		}

		if ( $display ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test double of wp_nonce_field.
		}

		return $html;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * @param string $key   Query argument name.
	 * @param string $value Query argument value.
	 * @param string $url   URL to modify.
	 * @return string
	 */
	function add_query_arg( $key, $value, $url ) {
		$separator = ( false === strpos( $url, '?' ) ) ? '?' : '&';
		return $url . $separator . rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook  Filter name.
	 * @param mixed  $value Value to filter.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) {
		$args = func_get_args();

		if ( isset( $GLOBALS['cp_test_filters'][ $hook ] ) && is_callable( $GLOBALS['cp_test_filters'][ $hook ] ) ) {
			return call_user_func_array( $GLOBALS['cp_test_filters'][ $hook ], array_slice( $args, 1 ) );
		}

		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * @param string $hook Hook name.
	 * @param mixed  $arg  Argument passed to the hook.
	 * @return void
	 */
	function do_action( $hook, $arg = null ) {
		if ( ! isset( $GLOBALS['cp_test_actions'] ) || ! is_array( $GLOBALS['cp_test_actions'] ) ) {
			$GLOBALS['cp_test_actions'] = array();
		}

		$GLOBALS['cp_test_actions'][] = array(
			'hook' => $hook,
			'arg'  => $arg,
		);
	}
}

require_once dirname( __DIR__ ) . '/Admin/_Init.php';
