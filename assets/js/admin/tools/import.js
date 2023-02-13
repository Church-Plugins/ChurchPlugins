
/**
 * Import screen JS
 */
var ChurchPlugins_Import = {

	init: function() {
		this.submit();
	},

	submit: function() {
		const self = this;

		jQuery( '.cp-import-form' ).ajaxForm( {
			beforeSubmit: self.before_submit,
			success: self.success,
			complete: self.complete,
			dataType: 'json',
			error: self.error,
		} );
	},

	before_submit: function( arr, form, options ) {
		form.find( '.notice-wrap' ).remove();
		form.append( '<div class="notice-wrap"><div class="cp-progress"><div></div></div></div>' );

		//check whether client browser fully supports all File API
		if ( window.File && window.FileReader && window.FileList && window.Blob ) {

			// HTML5 File API is supported by browser

		} else {
			const import_form = jQuery( '.cp-import-form' ).find( '.cp-progress' ).parent().parent();
			const notice_wrap = import_form.find( '.notice-wrap' );

			import_form.find( '.button:disabled' ).attr( 'disabled', false );

			//Error for older unsupported browsers that doesn't support HTML5 File API
			notice_wrap.html( '<div class="update error"><p>We are sorry but your browser is not compatible with this kind of file upload. Please upgrade your browser.</p></div>' );
			return false;
		}
	},

	success: function( responseText, statusText, xhr, form ) {},

	complete: function( xhr ) {
		const self = jQuery( this ),
			response = jQuery.parseJSON( xhr.responseText );

		if ( response.success ) {
			const form = jQuery( '.cp-import-form .notice-wrap' ).parent();

			form.find( '.cp-import-file-wrap,.notice-wrap' ).remove();
			form.find( '.cp-import-options' ).slideDown();

			// Show column mapping
			let select = form.find( 'select.cp-import-csv-column' ),
				row = select.parents( 'tr' ).first(),
				options = '',
				columns = response.data.columns.sort( function( a, b ) {
					if ( a < b ) {
						return -1;
					}
					if ( a > b ) {
						return 1;
					}
					return 0;
				} );

			jQuery.each( columns, function( key, value ) {
				options += '<option value="' + value + '">' + value + '</option>';
			} );

			select.append( options );

			select.on( 'change', function() {
				const key = jQuery( this ).val();

				if ( ! key ) {
					jQuery( this ).parent().next().html( '' );
				} else if ( false !== response.data.first_row[ key ] ) {
					jQuery( this ).parent().next().html( response.data.first_row[ key ] );
				} else {
					jQuery( this ).parent().next().html( '' );
				}
			} );

			jQuery.each( select, function() {
				jQuery( this ).val( jQuery( this ).attr( 'data-field' ) ).change();
			} );

			jQuery( document.body ).on( 'click', '.cp-import-proceed', function( e ) {
				e.preventDefault();

				form.find( '.cp-import-proceed.button-primary' ).addClass( 'updating-message' );
				form.find( '.notice-wrap' ).remove();
				form.append( '<div class="notice-wrap"><div class="cp-progress"><div></div></div></div>' );

				response.data.mapping = form.serialize();

				ChurchPlugins_Import.process_step( 1, response.data, self );
			} );
		} else {
			ChurchPlugins_Import.error( xhr );
		}
	},

	error: function( xhr ) {
		// Something went wrong. This will display error on form

		const response = jQuery.parseJSON( xhr.responseText );
		const import_form = jQuery( '.cp-import-form' ).find( '.cp-progress' ).parent().parent();
		const notice_wrap = import_form.find( '.notice-wrap' );

		import_form.find( '.button:disabled' ).attr( 'disabled', false );

		if ( response.data.error ) {
			notice_wrap.html( '<div class="update error"><p>' + response.data.error + '</p></div>' );
		} else {
			notice_wrap.remove();
		}
	},

	process_step: function( step, import_data, self ) {
		jQuery.ajax( {
			type: 'POST',
			url: ajaxurl,
			data: {
				form: import_data.form,
				nonce: import_data.nonce,
				class: import_data.class,
				upload: import_data.upload,
				mapping: import_data.mapping,
				action: 'cp_do_ajax_import',
				step: step,
			},
			dataType: 'json',
			success: function( response ) {
				if ( 'done' === response.data.step || response.data.error ) {
					// We need to get the actual in progress form, not all forms on the page
					const import_form = jQuery( '.cp-import-form' ).find( '.cp-progress' ).parent().parent();
					const notice_wrap = import_form.find( '.notice-wrap' );

					import_form.find( '.button:disabled' ).attr( 'disabled', false );

					if ( response.data.error ) {
						import_form.find( '.cp-import-proceed.button-primary' ).removeClass( 'updating-message' )
						notice_wrap.html( '<div class="update error"><p>' + response.data.error + '</p></div>' );
					} else {
						import_form.find( '.cp-import-options' ).hide();
						jQuery( 'html, body' ).animate( {
							scrollTop: import_form.parent().offset().top,
						}, 500 );

						notice_wrap.html( '<div class="updated"><p>' + response.data.message + '</p></div>' );
					}
				} else {
					jQuery( '.cp-progress div' ).animate( {
						width: response.data.percentage + '%',
					}, 50, function() {
						// Animation complete.
					} );

					ChurchPlugins_Import.process_step( parseInt( response.data.step ), import_data, self );
				}
			},
		} ).fail( function( response ) {
			const import_form = jQuery( '.cp-import-form' ).find( '.cp-progress' ).parent().parent();
			const notice_wrap = import_form.find( '.notice-wrap' );
			import_form.find( '.cp-import-proceed.button-primary' ).removeClass( 'updating-message' )

			if ( undefined !== response.responseText ) {
				notice_wrap.html( '<div class="update error"><p>' + response.responseText + '</p></div>' );
			} else {
				notice_wrap.html( '<div class="update error"><p>Something went wrong. Please check your data and try again.</p></div>' );
			}

			if ( window.console && window.console.log ) {
				console.log( response );
			}
		} );
	},
};

jQuery( document ).ready( function( jQuery ) {
	ChurchPlugins_Import.init();
} );
