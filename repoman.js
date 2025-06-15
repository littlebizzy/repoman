jQuery( document ).ready( function ( $ ) {
	// fallback if global variable is missing
	if ( typeof repomanStarsData !== 'object' ) {
		window.repomanStarsData = {};
	}

	// loop every second to catch dynamically loaded cards
	setInterval( function () {
		$( '.plugin-card' ).each( function () {
			var $card = $( this );

			// skip if already injected
			if ( $card.find( '.repoman-star-count' ).length ) {
				return;
			}

			// get plugin slug from class
			var classAttr = $card.attr( 'class' );
			var slugMatch = classAttr && classAttr.match( /plugin-card-([\w\-]+)/ );
			if ( ! slugMatch ) {
				return;
			}

			var slug = slugMatch[1];

			// only apply to repoman plugins
			if ( ! slug.startsWith( 'repoman-' ) ) {
				return;
			}

			// fallback to zero stars if missing
			var stars = parseInt( repomanStarsData[slug] || '0', 10 );
			if ( isNaN( stars ) ) {
				stars = 0;
			}

			// find bottom section and inject stars
			var bottom = $card.find( '.plugin-card-bottom' );
			if ( ! bottom.length ) {
				return;
			}

			var starsDiv = $( '<div class="repoman-star-count" style="margin-top:4px; font-size:13px; opacity:0.75;"></div>' );
			starsDiv.text( '★ ' + stars.toLocaleString() + ' GitHub stars' );

			bottom.prepend( starsDiv );
		} );
	}, 1000 );
} );
