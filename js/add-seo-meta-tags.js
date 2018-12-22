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

			if ( $title.length ) {
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
			$counter = $( "<div class='mt_counter' data-limit=" + limit + " />" );
			el.after( $counter );

			var html = amt_values.counter_label;
			html     = html.replace( /%%TITLE%%/, label );
			html     = html.replace( /%%LIMIT%%/, limit );
			html     = html.replace( /%%COUNT%%/, '<span class="count">' + limit + '</span>' );
			$counter.html( html );

			t.updateTitle();
			t.updateDesc();
		},

		/**
		 * When the title text area is updated, adjusts the counter for remaining characters.
		 */
		updateTitle : function() {
			var t  = this,
			$title = $( '#mt_seo_title' );

			if ( ! $title.length ) {
				return;
			}

			// The title, taken from the post edit screen.
			var originalTitle = $( '#title' ).val();

			// If the placeholder for the page title is used, sub that in before counting.
			var count = $title.val().replace( '%title%', originalTitle ).length;

			var limit = $title.attr( 'data-limit' ) || amt_values.max_title_length;
			$title.siblings( '.mt_counter' ).find( '.count' ).replaceWith( t.updateCounter( count, limit ) );
			$( "#mt_snippet .title" ).html( jQuery( '<p>' + $title.val().replace( '%title%', originalTitle ).substring( 0, limit ) + '</p>' ).text() );
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
			$( '#mt_snippet .content' ).html( jQuery( '<p>' + $desc.val().substring( 0, limit ) + '</p>' ).text() );
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
