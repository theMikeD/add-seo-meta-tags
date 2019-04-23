(function($) {

	/**
	 * Adds a counter to the Title and Description meta sections to track the number of characters used
	 * and remaining. Used on the post edit screens and the main options panel.
	 */
	var counter = {

		/**
		 * Do initial counter setup.
		 */
		init : function() {
			var t  = this,
			$title = $( '#mt_seo_title' ),
			$desc  = $( '#mt_seo_description' );

			if ( $title.length && 'singular' === amt_values.viewing_page ) {
				t.buildCounter( $title, parseInt( amt_values.max_title_length ), amt_values.title_label );

				$title.keyup(
					function() {
						t.updateTitle();
					}
				);

				$title.on(
					'change',
					function() {
						t.updateTitle();
					}
				);
			}

			if ( $desc.length ) {
				t.buildCounter( $desc, parseInt( amt_values.max_desc_length ) , amt_values.desc_label );

				$desc.keyup(
					function() {
						t.updateDesc();
					}
				);

				$desc.on(
					'change',
					function() {
						t.updateDesc();
					}
				);
			}
		},

		/**
		 * Builds the counter HTML using localized strings for all text.
		 *
		 * @param el      The element being updated
		 * @param limit   The localized counter limit
		 * @param label   The localized label
		 */
		buildCounter : function( el, limit, label ) {
			var t    = this,
				$counter = $( "<div>" )
					.attr( "class", "mt_counter" )
					.attr( "data-limit", limit );

			el.after( $counter );

			var labels = JSON.parse( amt_values.counter_label );
			var pre    = labels[0];
			pre        = pre.replace( /%%TITLE%%/, label );
			pre        = pre.replace( /%%LIMIT%%/, limit );
			var $cnt   = $( "<span>" ).text( limit ).attr( "class", "count" );

			$counter.append( pre ).append( $cnt ).append( labels[1] );

			if ( 'singular' === amt_values.viewing_page ) {
				t.updateTitle();
			}
			t.updateDesc();
		},

		/**
		 * When the title text area is updated, adjusts the counter for remaining characters.
		 */
		updateTitle : function() {
		 	var t  = this,
		 	$title = $( '#mt_seo_title' );

			var limit = $title.attr( 'data-limit' ) || amt_values.max_title_length;

			// The title, taken from the post edit screen.
			// @todo: this is not working with Gutenburg, I think because the title block is not rendered yet
			var originalTitle;
			if ( $( '#title' ).length ) {
				originalTitle = $( '#title' ).val();
			} else if ( $( '#post-title-0' ).length ) {
				originalTitle = $('#post-title-0').val();
			}
		 	if ( $title.val() === '' ) {
		 		// If the title text entry area is blank, then we use the page title
		 		$( "#mt_snippet .title" ).text(  originalTitle );
		 		var count = originalTitle.length;
		 	} else {
		 		// Otherwise, use what we have after subbing in the page title for the %title% placeholder
				var new_title = $title.val().replace( /%title%/, originalTitle );
		 		$( "#mt_snippet .title" ).text( new_title.substring( 0, limit ) );
		 		var count = new_title.length;
		 	}
		 	$title.siblings( '.mt_counter' ).find( '.count' ).replaceWith( t.updateCounter( count, limit ) );
		},

		/**
		 * When the description text area is updated, adjusts the counter for remaining characters.
		 */
		updateDesc : function() {
			var t = this,
			$desc = $( '#mt_seo_description' );

			if ( ! $desc.length ) {
				return;
			}

			var count = $desc.val().length,
			limit     = $desc.attr( 'data-limit' ) || parseInt( amt_values.max_desc_length );

			$desc.siblings( '.mt_counter' ).find( '.count' ).replaceWith( t.updateCounter( count, limit ) );

			$( '#mt_snippet .content' ).append( "p" ).text( $desc.val().substring( 0, limit ) );
		},

		/**
		 * Updates the counter number and relevant HTML, adding a class to indicate when the user has exceeded the limit.
		 *
		 * @param count  The current length of the text
		 * @param limit  The text length limit
		 * @returns {jQuery}
		 */
		updateCounter : function( count, limit ) {
			var $counter = $( '<span class="count" />' ),
			left         = limit - count;

			$counter.text( left );

			if ( left > 0 ) {
				$counter.removeClass( 'negative' ).addClass( 'positive' );
			} else {
				$counter.removeClass( 'positive' ).addClass( 'negative' );
			}

			return $( '<b>' ).append( $counter ).html();
		}
	};

	$( document ).ready( function(){counter.init();} );
})( jQuery );
