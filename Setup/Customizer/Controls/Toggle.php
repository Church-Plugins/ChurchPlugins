<?php
/**
 * Models a Customizer toggle switch.
 *
 * @since 1.0.6
 *
 * @package ChurchPlugins\Setup\Customizer\Controls
 */

namespace ChurchPlugins\Setup\Customizer\Controls;

/**
 * Class Toggle
 *
 * @since 1.0.6
 *
 * @package ChurchPlugins\Setup\Customizer\Controls
 */
class Toggle extends _Control {

	/**
	 * Control's Type.
	 *
	 * @since 1.0.6
	 *
	 * @var string
	 */
	public $type = 'toggle';

	/**
	 * Anyone able to set theme options will be able to see the slider.
	 *
	 * @since 1.0.6
	 *
	 * @var string
	 */
	public $capability = 'edit_theme_options';

	/**
	 * Render the control's content
	 *
	 * @since 1.0.6
	 */
	public function render_content() {
		$input_id         = '_customize-input-' . $this->id;
		$description_id   = '_customize-description-' . $this->id;
		$describedby_attr = ( ! empty( $this->description ) ) ? ' aria-describedby="' . esc_attr( $description_id ) . '" ' : '';
		$name             = '_customize-toggle-' . $this->id;

		?>
		<?php if ( ! empty( $this->label ) ) : ?>
			<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
		<?php endif; ?>
		<?php if ( ! empty( $this->description ) ) : ?>
			<span id="<?php echo esc_attr( $description_id ); ?>" class="description customize-control-description">
				<?php echo wp_kses_post( $this->description ); ?>
			</span>
		<?php endif; ?>

		<span class="customize-inside-control-row">
			<label class="tec-switch-label">
				<span class="toggle-label-off">
					<?php echo wp_kses_post( ! empty( $this->choices['off'] ) ? $this->choices['off'] : $this->label ); ?>
				</span>
				<input
					id="<?php echo esc_attr( $input_id . '-toggle' ); ?>"
					type="checkbox"
					class="tec-switch-input tribe-common-a11y-visual-hide"
					<?php echo $describedby_attr; ?>
					name="<?php echo esc_attr( '_customize-toggle-' . $this->id ); ?>"
					<?php $this->input_attrs(); ?>
					<?php $this->link(); ?>
				/>
				<span class="tec-switch-toggle"></span>
				<?php if ( ! empty( $this->choices['on'] ) ) : ?>
					<span class="toggle-label-on">
						<?php echo wp_kses_post( $this->choices['on'] ); ?>
					</span>
				<?php endif; ?>
			</label>
		</span>
		<?php
	}
}
