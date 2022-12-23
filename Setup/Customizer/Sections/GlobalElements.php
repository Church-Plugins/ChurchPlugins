<?php
/**
 * The Events Calendar Customizer Section Class
 * Global Elements
 *
 * @since 1.0.6
 */

namespace ChurchPlugins\Setup\Customizer\Sections;

/**
 * Global Elements
 *
 * @since 1.0.6
 */
final class GlobalElements extends _Section {

	/**
	 * ID of the section.
	 *
	 * @since 1.0.6
	 *
	 * @access public
	 * @var string
	 */
	public $ID = 'cp_global_elements';

	/**
	 * Allows sections to be loaded in order for overrides.
	 *
	 * @var integer
	 */
	public $queue_priority = 15;

	/**
	 * Placeholder for filtered multiplier for small font size.
	 *
	 * @var float
	 */
	private $small_font_multiplier = .75;

	/**
	 * Placeholder for filtered multiplier for large font size.
	 *
	 * @var float
	 */
	private $large_font_multiplier = 1.5;

	/**
	 * Placeholder for filtered min font size.
	 *
	 * @var int
	 */
	private $min_font_size = 8;

	/**
	 * Placeholder for filtered max font size.
	 *
	 * @var int
	 */
	private $max_font_size = 72;

	/**
	 * This method will be executed when the Class is Initialized.
	 *
	 * @since 1.0.6
	 */
	public function setup() {
		parent::setup();

		/**
		 * Allows users and plugins to change the "small" font size multiplier.
		 *
		 * @since 1.0.6
		 *
		 * @param int $small_font_multiplier The multiplier for "small" font size.
		 *
		 * @return int The multiplier for "small" font size. This should be less than 1.
		 */
		$this->small_font_multiplier = apply_filters( 'cp_customizer_small_font_size_multiplier', $this->small_font_multiplier );

		/**
		 * Allows users and plugins to change the "large" font size multiplier.
		 *
		 * @since 5.9.0
		 *
		 * @param int $large_font_multiplier The multiplier for "large" font size.
		 *
		 * @return int The multiplier for "large" font size. This should be greater than 1.
		 */
		$this->large_font_multiplier = apply_filters( 'cp_customizer_large_font_size_multiplier', $this->large_font_multiplier );

		/**
		 * Allows users and plugins to change the minimum font size.
		 *
		 * @since 5.9.0
		 *
		 * @param int $min_font_size The enforced minimum font size.
		 *
		 * @return int The enforced minimum font size.
		 */
		$this->min_font_size = apply_filters( 'cp_customizer_minimum_font_size', $this->min_font_size );

		/**
		 * Allows users and plugins to change the maximum font size.
		 *
		 * @since 5.9.0
		 *
		 * @param int $max_font_size The enforced maximum font size.
		 *
		 * @return int The enforced maximum font size.
		 */
		$this->max_font_size = apply_filters( 'cp_customizer_maximum_font_size', $this->max_font_size );
	}

	/**
	 * {@inheritdoc}
	 */
	public function setup_arguments() {
		return [
			'priority'   => 1,
			'capability' => 'edit_theme_options',
			'title'      => esc_html__( 'Global Elements', 'church-plugins' ),
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function setup_defaults() {
		$controls = $this->get_controls();

		$defaults = [];

		foreach( $controls as $key => $control ) {
			if ( empty( $control['default'] ) ) {
				continue;
			}

			$defaults[ $key ] = $control['default'];
		}

		return $defaults;
	}

	/**
	 * {@inheritdoc}
	 */
	public function setup_content_settings() {
		$settings = [];
		$controls = $this->get_controls();

		foreach ( $controls as $key => $control ) {
			if ( 'color' === $control['type'] ) {
				$settings[ $key ] = [
					'sanitize_callback'    => 'sanitize_hex_color',
					'sanitize_js_callback' => 'maybe_hash_hex_color',
					'transport'            => 'refresh',
				];
			} else {
				$settings[ $key ] = [
					'sanitize_callback'    => 'sanitize_user', // sanitize_user instead of sanitize_key to allow period
					'sanitize_js_callback' => 'sanitize_user',
					'transport'            => 'refresh',
				];
			}
		}

		return $settings;
	}

	/**
	 * {@inheritdoc}
	 */
	public function setup_content_headings() {
		return [
			'color_heading'        => [
				'priority' => 5,
				'type'     => 'heading',
				'label'    => esc_html__( 'UI Colors', 'church-plugins' ),
			],
			'color_ui_separator' => [
				'priority' => 8,
				'type'     => 'separator',
			],
			'color_text_separator' => [
				'priority' => 15,
				'type'     => 'separator',
			],
			'font_separator' => [
				'priority' => 30,
				'type'     => 'separator',
			],
			'font_heading'        => [
				'priority' => 35,
				'type'     => 'heading',
				'label'    => esc_html__( 'Fonts', 'church-plugins' ),
			],
			'font_weight_separator' => [
				'priority' => 55,
				'type'     => 'separator',
			],
			'gaps_separator' => [
				'priority' => 70,
				'type'     => 'separator',
			],
			'gaps_heading'        => [
				'priority' => 75,
				'type'     => 'heading',
				'label'    => esc_html__( 'Gaps (padding / margin)', 'church-plugins' ),
			],
			'canvas_separator' => [
				'priority' => 95,
				'type'     => 'separator',
			],
			'canvas_heading'        => [
				'priority' => 100,
				'type'     => 'heading',
				'label'    => esc_html__( 'Canvas Settings', 'church-plugins' ),
			],
		];
	}

	public function get_controls() {
		return [
			'color_brand'       => [
				'priority' => 7,
				'var'      => '--cp-color-brand-primary',
				'default'  => '#313E48',
				'type'     => 'color',
				'label'    => esc_html_x(
					'Brand Color',
					'The brand color setting label.',
					'church-plugins'
				),
			],
			'color_ui_primary'       => [
				'priority' => 9,
				'var'      => '--cp-color-ui-primary',
				'type'     => 'color',
				'label'    => esc_html_x(
					'Primary UI Color',
					'The primary ui color setting label.',
					'church-plugins'
				),
				'description' => esc_html_x(
					'Defaults to Brand Color',
					'The description for the primary ui color setting',
					'church-plugins'
				),
			],
			'color_ui_secondary'       => [
				'priority' => 10,
				'default'  => '#6c757d',
				'var'      => '--cp-color-ui-secondary',
				'type'     => 'color',
				'label'    => esc_html_x(
					'Secondary UI Color',
					'The secondary ui color setting label.',
					'church-plugins'
				),
			],
			'color_ui_inverted'       => [
				'priority' => 11,
				'default'  => '#E5E8EF',
				'var'      => '--cp-color-ui-inverted',
				'type'     => 'color',
				'label'    => esc_html_x(
					'Inverted UI Color',
					'The inverted ui color setting label.',
					'church-plugins'
				),
			],
			'color_ui_inverted_secondary'  => [
				'priority' => 12,
				'default'  => '#EBECED',
				'var'      => '--cp-color-ui-inverted-light',
				'type'     => 'color',
				'label'    => esc_html_x(
					'Secondary Inverted UI Color',
					'The secondary inverted ui color setting label.',
					'church-plugins'
				),
			],

			'color_text_primary'  => [
				'priority' => 17,
				'default'  => '#333333',
				'var'      => '--cp-color-text-primary',
				'type'     => 'color',
				'label'    => esc_html_x(
					'Primary Text Color',
					'The primary text color setting label.',
					'church-plugins'
				),
			],
			'color_text_secondary'  => [
				'priority' => 19,
				'var'      => '--cp-color-text-secondary',
				'type'     => 'color',
				'label'    => esc_html_x(
					'Secondary Text Color',
					'The secondary text color setting label.',
					'church-plugins'
				),
				'description' => esc_html_x(
					'Defaults to Secondary UI Color',
					'The description for the secondary text color setting',
					'church-plugins'
				),
			],
			'color_text_tertiary'  => [
				'priority' => 21,
				'default'  => '#76737A',
				'var'      => '--cp-color-text-tertiary',
				'type'     => 'color',
				'label'    => esc_html_x(
					'Tertiary Text Color',
					'The tertiary text color setting label.',
					'church-plugins'
				),
			],
			'color_text_disabled'  => [
				'priority' => 23,
				'default'  => '#A2A1A4',
				'var'      => '--cp-color-text-disabled',
				'type'     => 'color',
				'label'    => esc_html_x(
					'Disabled Text Color',
					'The disabled text color setting label.',
					'church-plugins'
				),
			],
			'color_text_inverted'  => [
				'priority' => 25,
				'default'  => '#ffffff',
				'var'      => '--cp-color-text-inverted',
				'type'     => 'color',
				'label'    => esc_html_x(
					'Inverted Text Color',
					'The inverted text color setting label.',
					'church-plugins'
				),
			],
			'color_text_link'  => [
				'priority' => 25,
				'var'      => '--cp-color-text-link',
				'type'     => 'color',
				'label'    => esc_html_x(
					'Link Text Color',
					'The link text color setting label.',
					'church-plugins'
				),
			],

			'font_size_base'  => [
				'priority' => 40,
				'default'  => '16px',
				'var'      => '--cp-font-size--base',
				'type'     => 'text',
				'label'    => esc_html_x(
					'Base Font Size',
					'The font base size setting label.',
					'church-plugins'
				),
				'input_attrs' => [
					'style' => 'width: 6em;',
				]
			],
			'font_size_xs'  => [
				'priority' => 42,
				'default'  => '.75em',
				'var'      => '--cp-font-size--xs',
				'type'     => 'text',
				'label'    => esc_html_x(
					'Font Size XS',
					'The font extra small size setting label.',
					'church-plugins'
				),
				'input_attrs' => [
					'style' => 'width: 6em;',
				]
			],
			'font_size_sm'  => [
				'priority' => 44,
				'default'  => '.85em',
				'var'      => '--cp-font-size--sm',
				'type'     => 'text',
				'label'    => esc_html_x(
					'Font Size SM',
					'The font small size setting label.',
					'church-plugins'
				),
				'input_attrs' => [
					'style' => 'width: 6em;',
				]
			],
			'font_size_md'  => [
				'priority' => 46,
				'default'  => '1em',
				'var'      => '--cp-font-size',
				'type'     => 'text',
				'label'    => esc_html_x(
					'Regular Font Size',
					'The regular font size setting label.',
					'church-plugins'
				),
				'input_attrs' => [
					'style' => 'width: 6em;',
				]
			],
			'font_size_lg'  => [
				'priority' => 48,
				'default'  => '1.375em',
				'var'      => '--cp-font-size-lg',
				'type'     => 'text',
				'label'    => esc_html_x(
					'Font Size Large',
					'The large font size setting label.',
					'church-plugins'
				),
				'input_attrs' => [
					'style' => 'width: 6em;',
				]
			],

			'font_weight_light'  => [
				'priority' => 60,
				'default'  => '300',
				'var'      => '--cp-font-weight--light',
				'type'     => 'text',
				'label'    => esc_html_x(
					'Font Weight Light',
					'The light font weight size setting label.',
					'church-plugins'
				),
				'input_attrs' => [
					'style' => 'width: 6em;',
				]
			],
			'font_weight_normal'  => [
				'priority' => 62,
				'default'  => '400',
				'var'      => '--cp-font-weight--normal',
				'type'     => 'text',
				'label'    => esc_html_x(
					'Font Weight Normal',
					'The normal font weight size setting label.',
					'church-plugins'
				),
				'input_attrs' => [
					'style' => 'width: 6em;',
				]
			],
			'font_weight_bold'  => [
				'priority' => 64,
				'default'  => '700',
				'var'      => '--cp-font-weight--bold',
				'type'     => 'text',
				'label'    => esc_html_x(
					'Font Weight Bold',
					'The bold font weight size setting label.',
					'church-plugins'
				),
				'input_attrs' => [
					'style' => 'width: 6em;',
				]
			],

			'gap_base'  => [
				'priority' => 77,
				'default'  => '.5rem',
				'var'      => '--cp-gap-base',
				'type'     => 'text',
				'label'    => esc_html_x(
					'Gap Base',
					'The gap base setting label.',
					'church-plugins'
				),
				'input_attrs' => [
					'style' => 'width: 6em;',
				]
			],
			'gap_sm'  => [
				'priority' => 79,
				'var'      => '--cp-gap--sm',
				'type'     => 'text',
				'label'    => esc_html_x(
					'Small Gap',
					'The small gap setting label.',
					'church-plugins'
				),
				'description' => esc_html_x(
					'Defaults to 2 times the Gap Base',
					'The description for the small gap setting',
					'church-plugins'
				),
				'input_attrs' => [
					'style' => 'width: 6em;',
				]
			],
			'gap_md'  => [
				'priority' => 79,
				'var'      => '--cp-gap--md',
				'type'     => 'text',
				'label'    => esc_html_x(
					'Medium Gap',
					'The medium gap setting label.',
					'church-plugins'
				),
				'description' => esc_html_x(
					'Defaults to 4 times the Gap Base',
					'The description for the medium gap setting',
					'church-plugins'
				),
				'input_attrs' => [
					'style' => 'width: 6em;',
				]
			],
			'gap_lg'  => [
				'priority' => 81,
				'var'      => '--cp-gap--lg',
				'type'     => 'text',
				'label'    => esc_html_x(
					'Large Gap',
					'The large gap setting label.',
					'church-plugins'
				),
				'description' => esc_html_x(
					'Defaults to 8 times the Gap Base',
					'The description for the large gap setting',
					'church-plugins'
				),
				'input_attrs' => [
					'style' => 'width: 6em;',
				]
			],
			'gap_xl'  => [
				'priority' => 79,
				'var'      => '--cp-gap--xl',
				'type'     => 'text',
				'label'    => esc_html_x(
					'XLarge Gap',
					'The extra large gap setting label.',
					'church-plugins'
				),
				'description' => esc_html_x(
					'Defaults to 16 times the Gap Base',
					'The description for the extra large gap setting',
					'church-plugins'
				),
				'input_attrs' => [
					'style' => 'width: 6em;',
				]
			],
			'gap_xxl'  => [
				'priority' => 81,
				'var'      => '--cp-gap--xxl',
				'type'     => 'text',
				'label'    => esc_html_x(
					'XXLarge Gap',
					'The extra extra large gap setting label.',
					'church-plugins'
				),
				'description' => esc_html_x(
					'Defaults to 32 times the Gap Base',
					'The description for the extra extra large gap setting',
					'church-plugins'
				),
				'input_attrs' => [
					'style' => 'width: 6em;',
				]
			],

			'canvas_width'          => [
				'priority' => 105,
				'default'  => '70rem',
				'type'     => 'text',
				'label'    => esc_html__( 'Canvas Width', 'church-plugins' ),
				'description' => esc_html_x(
					'The max width for Church Plugins templates. Enter value and unit (rem, %, px, etc)',
					'The description for the content width setting',
					'church-plugins'
				),
				'input_attrs' => [
					'style' => 'width: 6em;',
				]
			],
			'canvas_padding'          => [
				'priority' => 110,
				'default'  => 'medium',
				'type'     => 'select',
				'choices'  => [
					'none'   => __( 'No Padding', 'church-plugins' ),
					'small'  => __( 'Small Padding', 'church-plugins' ),
					'medium' => __( 'Medium Padding', 'church-plugins' ),
					'large'  => __( 'Large Padding', 'church-plugins' ),
				],
				'label'    => esc_html__( 'Canvas Padding', 'church-plugins' ),
				'description' => esc_html_x(
					'The padding to add around the Church Plugins template. Uses the values from the Gap UI settings.',
					'The description for the content padding setting',
					'church-plugins'
				),
			],
		];
	}
	/**
	 * {@inheritdoc}
	 */
	public function setup_content_controls() {
		$controls = $this->get_controls();

		$controls['canvas_width']['input_attrs']['value'] = (int) ! empty( $this->get_option( 'canvas_width' ) )
			? $this->get_option( 'canvas_width' )
			: $this->defaults['canvas_width'];

		return $controls;
	}

	/**
	 * Filters the Global Elements section CSS template to add Views v2 related style templates to it.
	 *
	 * Please note: the order is important for proper cascading overrides!
	 *
	 * @since 5.3.1
	 *
	 * @param string                      $css_template The current CSS template, as produced by the Section.
	 *
	 * @return string The filtered CSS template.
	 */
	public function get_css_template( $css_template ) {
		$new_styles   = [];

		// It's all custom props now, baby...

		if ( $this->should_include_setting_css( 'canvas_width' ) ) {
			$new_styles[] = "--cp-content-width: " . $this->get_option( 'canvas_width' ) . ";";
		}

		if ( $this->should_include_setting_css( 'canvas_padding' ) ) {
			$padding = $this->get_option( 'canvas_padding' );

			switch( $padding ) {
				case 'none' : $new_styles[] = "--cp-content-padding: 0;";
					break;
				case 'small' : $new_styles[] = "--cp-content-padding: var(--cp-gap--sm);";
					break;
				case 'large' : $new_styles[] = "--cp-content-padding: var(--cp-gap--lg);";
					break;
				default: $new_styles[] = "--cp-content-padding: var(--cp-gap--md);";
					break;
			}

		}

		// save colors
		$controls = $this->get_controls();

		foreach ( $controls as $key => $control ) {
			if ( ! empty( $control['var'] ) ) {
				if ( ! $this->should_include_setting_css( $key ) ) {
					continue;
				}

				$value = $this->get_option( $key );
				$new_styles[] = $control['var'] . ': ' . $value . ';';
			}
		}

		// set the default primary ui color to brand
		if ( ! $this->should_include_setting_css( 'color_ui_primary' ) ) {
			$new_styles[] = '--cp-color-ui-primary: var(--cp-color-brand-primary);';
		}

		// set the default secondary text color
		if ( ! $this->should_include_setting_css( 'color_text_secondary' ) ) {
			$new_styles[] = '--cp-color-text-secondary: var(--cp-color-ui-secondary);';
		}

		if ( empty( $new_styles ) ) {
			return $css_template;
		}

		$new_css = sprintf(
			':root {
				/* Customizer-added Global Event styles */
				%1$s
			}',
			implode( "\n", $new_styles )
		);

		return $css_template . $new_css;
	}
}
