<?php
/**
 * Models a Customizer separator, a Control just in name, it does not control any setting.
 *
 * @since   1.0.6
 *
 * @package ChurchPlugins\Setup\Customizer\Controls
 */

namespace ChurchPlugins\Setup\Customizer\Controls;

/**
 * Class Separator
 *
 * @since   1.0.6
 *
 * @package ChurchPlugins\Setup\Customizer\Controls
 */
class Separator extends _Control {

	/**
	 * Control's Type.
	 *
	 * @since 1.0.6
	 *
	 * @var string
	 */
	public $type = 'separator';

	/**
	 * Anyone able to set theme options will be able to see the header.
	 *
	 * @since 1.0.6
	 *
	 * @var string
	 */
	public $capability = 'edit_theme_options';

	/**
	 * The heading does not control any setting.
	 *
	 * @since 1.0.6
	 *
	 * @var array<string,mixed>
	 */
	public $settings = [];

	/**
	 * Render the control's content
	 *
	 * @since 1.0.6
	 */
	public function render_content() {
		?>
		<p>
			<hr>
		</p>
		<?php
	}
}
