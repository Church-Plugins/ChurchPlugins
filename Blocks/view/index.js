/**
 * Editor placeholder for the churchplugins/view block.
 *
 * The block is server rendered on the front end only; the editor shows a
 * placeholder with a selector for the plugin whose view it outputs. Written
 * without JSX so it needs no build step.
 */
( function ( wp, settings ) {
	const el = wp.element.createElement;
	const { __, sprintf } = wp.i18n;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { Placeholder, SelectControl, PanelBody } = wp.components;
	const { useSelect } = wp.data;

	// Plugins that own post types or taxonomies: { value, label, postTypes, taxonomies }.
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
	 * Where the block sits: in a template (or template part) rather than in
	 * the content of a post, page or pattern, and that template's slug when
	 * the editor exposes it.
	 *
	 * @param {string} clientId Block client id.
	 * @return {{inTemplate: boolean, templateSlug: string}} Block context.
	 */
	function useTemplateContext( clientId ) {
		return useSelect(
			( select ) => {
				const editor = select( 'core/editor' );

				if ( ! editor ) {
					return { inTemplate: true, templateSlug: '' };
				}

				const postType = editor.getCurrentPostType();

				if ( 'wp_template' === postType ) {
					const record = select( 'core' ).getEditedEntityRecord(
						'postType',
						postType,
						editor.getCurrentPostId()
					);

					return { inTemplate: true, templateSlug: ( record && record.slug ) || '' };
				}

				if ( 'wp_template_part' === postType ) {
					return { inTemplate: true, templateSlug: '' };
				}

				// A post shown inside its template: blocks outside Post Content belong to the template.
				const inTemplate =
					'template-locked' === editor.getRenderingMode?.() &&
					! select( 'core/block-editor' ).getBlockParentsByBlockName( clientId, 'core/post-content' ).length;
				const templateId = ( inTemplate && editor.getCurrentTemplateId?.() ) || '';

				return {
					inTemplate,
					templateSlug: 'string' === typeof templateId ? templateId.split( '//' ).pop() : '',
				};
			},
			[ clientId ]
		);
	}

	/**
	 * Plugins with something to show in a template.
	 *
	 * A template for one post type or taxonomy (single-{type}, archive-{type},
	 * taxonomy-{taxonomy}, and their -{slug} variants) gets the plugins that
	 * own it; any other template, or one no plugin owns, gets them all.
	 *
	 * @param {string} slug Template slug, or empty when unknown.
	 * @return {Array} Plugins.
	 */
	function pluginsFor( slug ) {
		if ( ! slug ) {
			return plugins;
		}

		const is = ( prefix, name ) => slug === prefix + name || 0 === slug.indexOf( prefix + name + '-' );
		const matching = plugins.filter(
			( plugin ) =>
				( plugin.postTypes || [] ).some(
					( type ) => is( 'single-', type ) || slug === 'archive-' + type
				) || ( plugin.taxonomies || [] ).some( ( taxonomy ) => is( 'taxonomy-', taxonomy ) )
		);

		return matching.length ? matching : plugins;
	}

	function PluginSelect( { value, options, onChange, help } ) {
		return el( SelectControl, {
			label: __( 'Content to show', 'churchplugins' ),
			value,
			options,
			onChange,
			help,
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
		} );
	}

	wp.blocks.registerBlockType( 'churchplugins/view', {
		edit( { attributes, setAttributes, clientId } ) {
			const { inTemplate, templateSlug } = useTemplateContext( clientId );
			const blockProps = useBlockProps();

			if ( ! inTemplate ) {
				return el(
					'div',
					blockProps,
					el( Placeholder, {
						icon: 'layout',
						label: __( 'ChurchPlugins View', 'churchplugins' ),
						instructions: __(
							'This block only works in a template in the Site Editor. In a post, page or pattern it displays nothing on your site, so you can remove it.',
							'churchplugins'
						),
					} )
				);
			}

			const available = pluginsFor( templateSlug );
			const selected = available.find( ( plugin ) => plugin.value === attributes.plugin );
			const options = [ { label: __( '— Choose what to show —', 'churchplugins' ), value: '' } ].concat(
				available.map( ( plugin ) => ( { label: plugin.label, value: plugin.value } ) )
			);

			let instructions;

			if ( ! attributes.plugin ) {
				instructions = __(
					'Choose what this block shows. Until you do, it shows nothing on your site.',
					'churchplugins'
				);
			} else if ( ! selected ) {
				const known = plugins.find( ( plugin ) => plugin.value === attributes.plugin );
				const name = known ? known.label : attributes.plugin;

				// Keep the saved value selectable so the control reflects it.
				options.push( {
					/* translators: %s: Plugin name. */
					label: sprintf( __( '%s (nothing to show here)', 'churchplugins' ), name ),
					value: attributes.plugin,
				} );

				instructions = sprintf(
					/* translators: %s: Plugin name. */
					__(
						'%s has nothing to show in this template, so this block displays nothing. Choose what to show.',
						'churchplugins'
					),
					name
				);
			} else {
				instructions = sprintf(
					/* translators: %s: Plugin name. */
					__(
						'Shows the %s content for the page being viewed. It appears on your site, not in this editor.',
						'churchplugins'
					),
					selected.label
				);
			}

			const onChange = ( plugin ) => setAttributes( { plugin } );

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Settings', 'churchplugins' ) },
						el( PluginSelect, { value: attributes.plugin, options, onChange, help: instructions } )
					)
				),
				el(
					Placeholder,
					{
						icon: 'layout',
						label: __( 'ChurchPlugins View', 'churchplugins' ),
						instructions,
					},
					el( PluginSelect, { value: attributes.plugin, options, onChange } )
				)
			);
		},
		save: () => null,
	} );
} )( window.wp, window.cpViewBlock );
