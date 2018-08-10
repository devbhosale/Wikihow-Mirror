/*global WH, mw*/
WH.VideoBrowser.IndexComponent = WH.Render.createComponent( {
	create: function () {
		this.lists = [];
		this.onViewportChange = this.onViewportChange.bind( this );
	},
	onAttach: function () {
		this.lists = WH.VideoBrowser.catalog.categories()
			.order( 'rank desc' )
			.get()
			.map( function ( category ) {
				return new WH.VideoBrowser.ListComponent( { category: category } );
			} );
		window.addEventListener( 'resize', this.onViewportChange );
		window.addEventListener( 'scroll', this.onViewportChange );
	},
	onDetach: function () {
		window.removeEventListener( 'resize', this.onViewportChange );
		window.removeEventListener( 'scroll', this.onViewportChange );
	},
	onViewportChange: function () {
		var i, len, video, bounding,
			videos = document.querySelectorAll( '.videoBrowser-item video' );
		for ( i = 0, len = videos.length; i < len; i++ ) {
			video = videos[i];
			bounding = video.getBoundingClientRect();
			if (
				bounding.bottom >= 0 &&
				bounding.right >= 0 &&
				bounding.top <= ( window.innerHeight || document.documentElement.clientHeight ) &&
				bounding.left <= ( window.innerWidth || document.documentElement.clientWidth )
			) {
				// Be extra safe for IE support
				var promise = video.play();
				if ( promise && typeof promise['catch'] === 'function' ) {
					promise['catch']( function () {} );
				}
			} else {
				video.pause();
			}
		}
	},
	render: function () {
		return [ 'div.videoBrowser-index' ].concat( this.lists );
	}
} );
