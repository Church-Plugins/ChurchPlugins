<?php
/**
 * Models a Customizer range slider.
 *
 * @since 4.12.13
 *
 * @package ChurchPlugins\Setup\Customizer\Controls
 */

namespace ChurchPlugins\Setup\Customizer\Controls;

/**
 * Class RangeSlider
 *
 * @since 1.0.6
 *
 * @package ChurchPlugins\Setup\Customizer\Controls
 */
class RangeSlider extends _Control {

	/**
	 * Control's Type.
	 *
	 * @since 1.0.6
	 *
	 * @var string
	 */
	public $type = 'range-slider';

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
		?>
		<?php if ( ! empty( $this->label ) ) : ?>
			<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
		<?php endif; ?>
		<?php if ( ! empty( $this->description ) ) : ?>
			<span id="<?php echo esc_attr( $description_id ); ?>" class="description customize-control-description">
				<?php echo wp_kses_post( $this->description ); ?>
			</span>
		<?php endif; ?>

		<span class="tribe-common-form-control-slider">
			<label
				class="tec-range-slider-label tribe-common-a11y-visual-hide"
				for="<?php echo esc_attr( $input_id . '-range-slider' ); ?>"
			>
				<?php echo wp_kses_post( $this->label ); ?>
			</label>
			<input
				id="<?php echo esc_attr( $input_id . '-range-slider' ); ?>"
				type="range"
				class="tec-range-slider"
				<?php echo $describedby_attr; ?>
				name="<?php echo esc_attr( '_customize-range-slider-' . $this->id ); ?>"
				<?php $this->input_attrs(); ?>
				<?php $this->link(); ?>
				<?php if ( ! empty( $this->choices ) ) : ?>
					list="<?php echo esc_attr( $input_id . '-range-slider-datalist' ); ?>"
				<?php endif; ?>
			/>
			<?php if ( ! empty( $this->choices ) ) : ?>
				<div
					class="tec-range-slider-datalist"
					id="<?php echo esc_attr( $input_id . '-range-slider-datalist' ); ?>"
				>
					<?php foreach ( $this->choices as $label => $value ) : ?>
						<div
							class="tec-range-slider-option"
							value="<?php echo esc_attr( $value ); ?>"
							label="<?php echo esc_attr( $label ); ?>"
						>
							<?php echo esc_html( $label ); ?></div>
					<?php endforeach;?>
				</div>
			<?php endif; ?>
		</span>
		<?php
	}
}
