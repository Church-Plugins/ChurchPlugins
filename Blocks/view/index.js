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
	const { useSelect } = wp.data;

	const plugins = ( settings && settings.plugins ) || [];

	// The block outputs only inside a template, so it is offered in the inserter
	// only where templates are edited (see ViewBlock::can_insert()). It stays
	// registered everywhere, so content that already contains it still loads.
	if ( ! ( settings && settings.inserter ) ) {
		wp.hooks.addFilter(
			'blocks.registerBlockType',
			'churchplugins/view/inserter',
			( blockSettings, name ) =>
				'churchplugins/view' === name
					? {
							...blockSettings,
							supports: { ...blockSettings.supports, inserter: false },
					  }
					: blockSettings
		);
	}

	/**
	 * Whether the block sits in a template (or template part) rather than in
	 * the content of a post, page or pattern.
	 *
	 * @param {string} clientId Block client id.
	 * @return {boolean} True when the block is part of a template.
	 */
	function useIsInTemplate( clientId ) {
		return useSelect(
			( select ) => {
				const editor = select( 'core/editor' );

				if ( ! editor ) {
					return true;
				}

				const postType = editor.getCurrentPostType();

				if ( 'wp_template' === postType || 'wp_template_part' === postType ) {
					return true;
				}

				// A post shown inside its template: blocks outside Post Content belong to the template.
				return (
					'template-locked' === editor.getRenderingMode?.() &&
					! select( 'core/block-editor' ).getBlockParentsByBlockName( clientId, 'core/post-content' ).length
				);
			},
			[ clientId ]
		);
	}

	wp.blocks.registerBlockType( 'churchplugins/view', {
		edit( { attributes, setAttributes, clientId } ) {
			const inTemplate = useIsInTemplate( clientId );

			return el(
				'div',
				useBlockProps(),
				el(
					Placeholder,
					{
						icon: 'layout',
						label: __( 'ChurchPlugins View', 'churchplugins' ),
						instructions: inTemplate
							? __(
									'Outputs the view for the sermon, group, staff member or archive being viewed. It appears on your site, not in this editor.',
									'churchplugins'
							  )
							: __(
									'This block only works in a template in the Site Editor. In a post, page or pattern it displays nothing on your site, so you can remove it.',
									'churchplugins'
							  ),
					},
					inTemplate &&
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
