<?php

namespace ChurchPlugins\Integrations\CMB2;

/*
Plugin Name: CMB2 Field Type: Select2
Plugin URI: https://github.com/mustardBees/cmb-field-select2
GitHub Plugin URI: https://github.com/mustardBees/cmb-field-select2
Description: Select2 field type for CMB2.
Version: 3.0.3
Author: Phil Wylie
Author URI: https://www.philwylie.co.uk/
License: GPLv2+
*/

/**
 * Class PW_CMB2_Field_Select2
 */
class Init {

	/**
	 * Current version number
	 */
	const VERSION = '3.0.3';

	/**
	 * @var Init
	 */
	protected static $_instance;

	/**
	 * Only make one instance of Init
	 *
	 * @return Init
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Init ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Initialize the plugin by hooking into CMB2
	 */
	protected function __construct() {
		add_filter( 'cmb2_render_license', array( $this, 'render_license' ), 10, 5 );

		add_filter( 'cmb2_render_pw_select', array( $this, 'render_pw_select' ), 10, 5 );
		add_filter( 'cmb2_render_pw_multiselect', array( $this, 'render_pw_multiselect' ), 10, 5 );
		add_filter( 'cmb2_sanitize_pw_multiselect', array( $this, 'pw_multiselect_sanitize' ), 10, 4 );
		add_filter( 'cmb2_types_esc_pw_multiselect', array( $this, 'pw_multiselect_escaped_value' ), 10, 3 );
		add_filter( 'cmb2_repeat_table_row_types', array( $this, 'pw_multiselect_table_row_class' ), 10, 1 );
		add_filter( 'cmb2_render_cp_social_links', array( $this, 'cp_social_links' ), 10, 5 );
		add_filter( 'cmb2_sanitize_cp_social_links', array( $this, 'cp_social_links_sanitize' ), 10, 4 );
		add_filter( 'cmb2_types_esc_cp_social_links', array( $this, 'cp_social_links_escaped_value' ), 10, 3 );

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
	}

	public function admin_scripts() {
		$asset_path = CHURCHPLUGINS_URL . 'Integrations/CMB2';
		wp_enqueue_script( 'cmb2-conditional-logic', $asset_path . '/js/cmb2-conditional-logic.js', array( 'jquery' ), self::VERSION );
	}

	public function render_license( $field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object ) {
		$desc = $field_type_object->field->args['description'];
		$field_type_object->field->args['desc'] = $field_type_object->field->args['description'] = '';

		$render_class = $field_type_object->get_new_render_type( 'text', 'CMB2_Type_Text' );
		echo $render_class->render();

		if ( $field->args['nonce'] ) {
			echo $field->args['nonce'];

			if ( $field->args['is_active'] ) {
				echo '<span style="margin: .25rem .5rem;color: #00a32a;" class="dashicons dashicons-yes"></span>';
			} else {
				echo '&nbsp;';
			}
			submit_button( $field->args['button_text'], $field->args['button_type'], $field->args['button_name'], false );
		}

		$field_type_object->field->args['desc'] = $field_type_object->field->args['description'] = $desc;
		echo $render_class->_desc( true );
	}

	/**
	 * Render select box field
	 */
	public function render_pw_select( $field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object ) {
		$this->setup_admin_scripts();

		if ( version_compare( CMB2_VERSION, '2.2.2', '>=' ) ) {
			$field_type_object->type = new \CMB2_Type_Select( $field_type_object );
		}

		echo $field_type_object->select( array(
			'class'            => 'pw_select2 pw_select',
			'desc'             => $field_type_object->_desc( true ),
			'options'          => '<option></option>' . $field_type_object->concat_items(),
			'data-placeholder' => $field->args( 'attributes', 'placeholder' ) ? $field->args( 'attributes', 'placeholder' ) : $field->args( 'description' ),
		) );
	}

	/**
	 * Render multi-value select input field
	 */
	public function render_pw_multiselect( $field, $field_escaped_value, $field_object_id, $field_object_type, $field_type_object ) {
		$this->setup_admin_scripts();

		if ( version_compare( CMB2_VERSION, '2.2.2', '>=' ) ) {
			$field_type_object->type = new \CMB2_Type_Select( $field_type_object );
		}

		$a = $field_type_object->parse_args( 'pw_multiselect', array(
			'multiple'         => 'multiple',
			'style'            => 'width: 99%',
			'class'            => 'pw_select2 pw_multiselect',
			'name'             => $field_type_object->_name() . '[]',
			'id'               => $field_type_object->_id(),
			'desc'             => $field_type_object->_desc( true ),
			'options'          => $this->get_pw_multiselect_options( $field_escaped_value, $field_type_object ),
			'data-placeholder' => $field->args( 'attributes', 'placeholder' ) ? $field->args( 'attributes', 'placeholder' ) : '',
		) );

		$attrs = $field_type_object->concat_attrs( $a, array( 'desc', 'options' ) );
		echo sprintf( '<select%s>%s</select>%s', $attrs, $a['options'], $a['desc'] );
	}

	/**
	 * Return list of options for pw_multiselect
	 *
	 * Return the list of options, with selected options at the top preserving their order. This also handles the
	 * removal of selected options which no longer exist in the options array.
	 */
	public function get_pw_multiselect_options( $field_escaped_value, $field_type_object ) {
		$options = (array) $field_type_object->field->options();

		// If we have selected items, we need to preserve their order
		if ( ! empty( $field_escaped_value ) ) {
			$options = $this->sort_array_by_array( $options, $field_escaped_value );
		}

		$selected_items = '';
		$other_items = '';

		foreach ( $options as $option_value => $option_label ) {

			// Clone args & modify for just this item
			$option = array(
				'value' => $option_value,
				'label' => $option_label,
			);

			// Split options into those which are selected and the rest
			if ( in_array( $option_value, (array) $field_escaped_value ) ) {
				$option['checked'] = true;
				$selected_items .= $field_type_object->select_option( $option );
			} else {
				$other_items .= $field_type_object->select_option( $option );
			}
		}

		return $selected_items . $other_items;
	}

	/**
	 * Sort an array by the keys of another array
	 *
	 * @author Eran Galperin
	 * @link http://link.from.pw/1Waji4l
	 */
	public function sort_array_by_array( array $array, array $orderArray ) {
		$ordered = array();

		foreach ( $orderArray as $key ) {
			if ( array_key_exists( $key, $array ) ) {
				$ordered[ $key ] = $array[ $key ];
				unset( $array[ $key ] );
			}
		}

		return $ordered + $array;
	}

	/**
	 * Handle sanitization for repeatable fields
	 */
	public function pw_multiselect_sanitize( $check, $meta_value, $object_id, $field_args ) {
		if ( ! is_array( $meta_value ) || ! $field_args['repeatable'] ) {
			return $check;
		}

		foreach ( $meta_value as $key => $val ) {
			$meta_value[$key] = array_map( 'sanitize_text_field', $val );
		}

		return $meta_value;
	}

	/**
	 * Handle escaping for repeatable fields
	 */
	public function pw_multiselect_escaped_value( $check, $meta_value, $field_args ) {
		if ( ! is_array( $meta_value ) || ! $field_args['repeatable'] ) {
			return $check;
		}

		foreach ( $meta_value as $key => $val ) {
			$meta_value[$key] = array_map( 'esc_attr', $val );
		}

		return $meta_value;
	}

	/**
	 * Add 'table-layout' class to multi-value select field
	 */
	public function pw_multiselect_table_row_class( $check ) {
		$check[] = 'pw_multiselect';

		return $check;
	}

	/**
	 * Handle escaping for cp_social_links field
	 *
	 * @param string $check
	 * @param mixed  $meta_value
	 * @param array  $field_args
	 * @since 1.2.0
	 */
	public function cp_social_links_escaped_value( $check, $meta_value, $field_args ) {
		if ( ! is_array( $meta_value ) ) {
			return $check;
		}

		foreach ( $meta_value as $key => $val ) {
			$meta_value[ $key ]['network'] = esc_attr( $val['network'] );
			$meta_value[ $key ]['url']     = esc_url( $val['url'] );
		}

		return $meta_value;
	}

	/**
	 * Sanitize the value of the cp_social_links field
	 *
	 * @param mixed  $check
	 * @param mixed  $meta_value
	 * @param int    $object_id
	 * @param array  $field_args
	 */
	public function cp_social_links_sanitize( $check, $meta_value, $object_id, $field_args ) {
		if ( ! is_array( $meta_value ) ) {
			return $check;
		}

		$sanitized = array();

		foreach ( $meta_value as $link ) {
			$sanitized[] = array(
				'network' => sanitize_text_field( $link['network'] ),
				'url'     => esc_url( $link['url'] ),
			);
		}

		return $sanitized;
	}

	/**
	 * Render a social links field
	 *
	 * @param \CMB2_Field $field
	 * @param mixed       $escaped_value
	 * @param int         $object_id
	 * @param string      $object_type
	 * @param \CMB2_Types $field_type_object
	 */
	public function cp_social_links( $field, $escaped_value, $object_id, $object_type, $field_type_object ) {
		$this->setup_admin_scripts();

		$supported_networks = array(
			'facebook'  => __( 'Facebook', 'cp-staff' ),
			'instagram' => __( 'Instagram', 'cp-staff' ),
			'linkedin'  => __( 'LinkedIn', 'cp-staff' ),
			'pinterest' => __( 'Pinterest', 'cp-staff' ),
			'twitter'   => __( 'Twitter (X)', 'cp-staff' ),
			'vimeo'     => __( 'Vimeo', 'cp-staff' ),
			'youtube'   => __( 'YouTube', 'cp-staff' ),
		);

		$field_id   = $field->args['id'];
		$links      = is_array( $escaped_value ) ? $escaped_value : array();
		$link_count = count( $links );
		?>
		<p class="cmb2-metabox-description"><?php echo esc_html( $field->args['desc'] ); ?></p>
		<div class="cp-social-links">
			<template class="cp-social-links--template">
				<div class="cp-social-links--item">
					<select name="<?php echo esc_attr( $field_id ); ?>[ID][network]">
						<?php foreach ( $supported_networks as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
						<input type="text" name="<?php echo esc_attr( $field_id ); ?>[ID][url]" />
						<button class="button button-secondary cp-social-links--remove"><?php esc_html_e( 'Remove', 'cp-staff' ); ?></button>
					</select>
				</div>
			</template>
			<div class="cp-social-links--list">
				<?php for ( $i = 0; $i < $link_count; $i++ ) : ?>
					<?php $link = $links[ $i ]; ?>
					<div class="cp-social-links--item">
						<select name="<?php echo esc_attr( $field_id ); ?>[<?php echo absint( $i ); ?>][network]">
							<?php foreach ( $supported_networks as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $link['network'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="text" name="<?php echo esc_attr( $field_id ); ?>[<?php echo absint( $i ); ?>][url]" value="<?php echo esc_attr( $link['url'] ); ?>" />
						<button class="button button-secondary cp-social-links--remove"><?php esc_html_e( 'Remove', 'cp-staff' ); ?></button>
					</div>
				<?php endfor; ?>
			</div>
			<button class="button button-secondary cp-social-links--add" style="margin-top: 8px;"><?php esc_html_e( 'Add', 'cp-staff' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function setup_admin_scripts() {
		$asset_path = CHURCHPLUGINS_URL . 'Integrations/CMB2';

		wp_register_script( 'pw-select2', $asset_path . '/js/select2.min.js', array( 'jquery-ui-sortable' ), '4.0.3' );
		wp_enqueue_script( 'pw-select2-init', $asset_path . '/js/script.js', array( 'cmb2-scripts', 'pw-select2' ), self::VERSION );
		wp_register_style( 'pw-select2', $asset_path . '/css/select2.min.css', array(), '4.0.3' );
		wp_enqueue_style( 'pw-select2-tweaks', $asset_path . '/css/style.css', array( 'pw-select2' ), self::VERSION );
	}
}
