( function( M ) {
	var OverlayNew = M.require( 'OverlayNew' ),
		ProgressBar = M.require( 'widgets/progress-bar' ),
		PhotoUploadProgress;

	PhotoUploadProgress = OverlayNew.extend( {
		defaults: {
			uploadingMsg: mw.msg( 'mobile-frontend-image-uploading' ),
			closeMsg: mw.msg( 'cancel' )
		},
		template: M.template.get( 'uploads/PhotoUploadProgress' ),
		fullScreen: false,

		initialize: function( options ) {
			this._super( options );
			this.progressBar = new ProgressBar();
		},

		hide: function( force ) {
			if ( force ) {
				return this._super();
			} else if ( window.confirm( mw.msg( 'mobile-frontend-image-cancel-confirm' ) ) ) {
				this.emit( 'cancel' );
				return this._super();
			} else {
				return false;
			}
		},

		setValue: function( value ) {
			var $uploading = this.$( '.uploading' );
			// only add progress bar if we're getting progress events
			if ( $uploading.length && $uploading.text() !== '' ) {
				$uploading.text( '' );
				this.progressBar.appendTo( $uploading );
				this.$( '.right' ).remove();
			}
			this.progressBar.setValue( value );
		}
	} );

	M.define( 'modules/uploads/PhotoUploadProgress', PhotoUploadProgress );

}( mw.mobileFrontend ) );
