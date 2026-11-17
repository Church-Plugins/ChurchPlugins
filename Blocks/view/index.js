/**
 * Editor placeholder for the churchplugins/view block.
 *
 * The block is server rendered on the front end only; the editor shows a
 * placeholder with a selector for the plugin whose view it outputs. Written
 * without JSX so it needs no build step.
 */
( function ( wp, settings ) {
	const el = wp.element.createElement;
	const { __ } = wp.i18n;
	const { useBlockProps } = wp.blockEditor;
	const { Placeholder, SelectControl } = wp.components;

	const plugins = ( settings && settings.plugins ) || [];

	wp.blocks.registerBlockType( 'churchplugins/view', {
		edit( { attributes, setAttributes } ) {
			return el(
				'div',
				useBlockProps(),
				el(
					Placeholder,
					{
						icon: 'layout',
						label: __( 'ChurchPlugins View', 'churchplugins' ),
						instructions: __(
							'Outputs the view for the sermon, group, staff member or archive being viewed. It renders on the front end only.',
							'churchplugins'
						),
					},
					el( SelectControl, {
						label: __( 'Plugin', 'churchplugins' ),
						value: attributes.plugin,
						options: [
							{ label: __( 'Select a plugin', 'churchplugins' ), value: '' },
						].concat( plugins ),
						onChange: ( plugin ) => setAttributes( { plugin } ),
						__nextHasNoMarginBottom: true,
					} )
				)
			);
		},
		save: () => null,
	} );
} )( window.wp, window.cpViewBlock );
