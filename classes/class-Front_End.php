<?php

namespace CNMD\SEO_Meta_Tags;

/**
 * Class Front_End
 *
 * Provide the public/front end functionality for the SEO Meta Tags plugin.
 *
 * @package CNMD\SEO_Meta_Tags
 */
class Front_End extends Base {

	/**
	 * Include/Exclude the "keywords" metatag. If set to false, the 'keywords' meta tag will not be generated.
	 * Has no effect on any other tag, including 'description.'
	 */
	const INCLUDE_KEYWORDS_IN_SINGLE_POSTS = true;

	public function __construct() {
		parent::__construct();
		$this->set_hooks();
	}


	/**
	 * Initialize the required hooks
	 *
	 * @return void
	 */
	public function set_hooks() {
		add_action( 'wp_head', array( $this, 'do_meta_tags' ), 0 );
			add_filter( 'document_title_parts', array( $this, 'filter__the_title_parts' ), 1, 4 );
	}


	/**
	 * Creates and echoes the meta tag block for the page header.
	 *
	 * @return void
	 */
	public function do_meta_tags() {
		global $posts;
		$post = null;
		if ( ! is_array( $posts ) || ! isset( $posts[0] ) || ! is_a( $posts[0], 'WP_Post' ) ) {
			return;
		}
		$post = $posts[0];

		$options        = $this->get_saved_options();
		$site_wide_meta = '';
		if ( isset( $options['site_wide_meta'] ) ) {
			$site_wide_meta = $options['site_wide_meta'];
		}

		$cmpvalues = $this->get_enabled_singular_options( $post->post_type );
		$metatags  = array();

		// Add META tags to Singular pages.
		if ( is_singular() ) {
			if ( ! in_array( '1', $cmpvalues, true ) && ! empty( $options['site_wide_meta'] ) ) {
				return;
			}
			$mt_seo_title            = (string) get_post_meta( $post->ID, 'mt_seo_title', true );
			$mt_seo_description      = (string) get_post_meta( $post->ID, 'mt_seo_description', true );
			$mt_seo_keywords         = (string) get_post_meta( $post->ID, 'mt_seo_keywords', true );
			$mt_seo_google_news_meta = (string) get_post_meta( $post->ID, 'mt_seo_google_news_meta', true );
			$mt_seo_meta             = (string) get_post_meta( $post->ID, 'mt_seo_meta', true );

			if ( '' === $mt_seo_title ) {
				$mt_seo_title = (string) get_post_meta( $post->ID, '_yoast_wpseo_title', true );
			}

			if ( '' === $mt_seo_description ) {
				$mt_seo_description = (string) get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
			}

			/*
			Description. Order of preference:
			1. The post meta value for 'mt_seo_description'
			2. The post excerpt
			*/
			if ( '1' === $cmpvalues['mt_seo_description'] ) {
				$meta_description = '';

				if ( ! empty( $mt_seo_description ) ) {
					$meta_description = $mt_seo_description;
				} elseif ( is_single() ) {
					$meta_description = $this->get_the_excerpt( $post );
				}

				/**
				 * Filter the description for a singular post.
				 *
				 * @param string Contents of the post description field.
				 */
				$meta_description = apply_filters( 'amt_meta_description', $meta_description );

				if ( ! empty( $meta_description ) ) {
					$metatags['description'] = $meta_description;
				}
			}

			// Custom Meta Tags. This is a fully-rendered META tag, so no need to build it up.
			if ( ! empty( $mt_seo_meta ) && '1' === $cmpvalues['mt_seo_meta'] ) {
				// This is a potential difference; no escaping was done on this value in previous versions.
				$metatags['custom'] = $mt_seo_meta;
			}

			// Google News Meta. From post meta field "mt-seo-google-news-meta.
			if ( ! empty( $mt_seo_google_news_meta ) && '1' === $cmpvalues['mt_seo_google_news_meta'] ) {
				$metatags['news_keywords'] = $mt_seo_google_news_meta;
			}

			/*
			Title is handled using filters
			*/

			/*
			Keywords. Created in the following order
			1. The post meta value for 'mt_seo_keywords'
			2. The post's categories and tags.
			*/
			if ( '1' === $cmpvalues['mt_seo_keywords'] ) {
				if ( ( self::INCLUDE_KEYWORDS_IN_SINGLE_POSTS && is_single() ) || is_page() ) {
					if ( ! empty( $mt_seo_keywords ) ) {
						// If there is a custom field, use it.
						if ( is_single() ) {
							// For single posts, the %cat% tag is replaced by the post's categories.
							$mt_seo_keywords = str_replace( '%cats%', $this->get_post_categories(), $mt_seo_keywords );
							// Also, the %tags% tag is replaced by the post's tags.
							$mt_seo_keywords = str_replace( '%tags%', $this->get_post_tags(), $mt_seo_keywords );
						}
						$metatags['keywords'] = $mt_seo_keywords;
					} elseif ( is_single() ) {
						// Add categories and tags for keywords.
						$post_keywords = strtolower( $this->get_post_categories() );
						$post_tags     = strtolower( $this->get_post_tags() );

						$metatags['keywords'] = $post_keywords . ', ' . $post_tags;
					}
				}
			}
		} elseif ( is_home() ) {
			// Add META tags to Home Page.
			// Get set values.
			$site_description = $options['site_description'];
			$site_keywords    = $options['site_keywords'];

			/*
			Description
			*/
			if ( empty( $site_description ) ) {
				// If $site_description is empty, then use the blog description from the options.
				$metatags['description'] = get_bloginfo( 'description' );
			} else {
				// If $site_description has been set, then use it in the description meta-tag.
				$metatags['description'] = $site_description;
			}

			// Keywords.
			if ( empty( $site_keywords ) ) {
				// If $site_keywords is empty, then all the blog's categories are added as keywords.
				$metatags['keywords'] = $this->get_site_categories();
			} else {
				// If $site_keywords has been set, then these keywords are used.
				$metatags['keywords'] = $site_keywords;
			}
		} elseif ( is_tax() || is_tag() || is_category() ) {
			// taxonomy archive page.
			$term_desc = term_description();
			if ( $term_desc ) {
				$metatags['description'] = $term_desc;
			}

			// The keyword is the term name.
			$term_name = single_term_title( '', false );
			if ( $term_name ) {
				$metatags['keywords'] = $term_name;
			}
		}

		if ( $site_wide_meta ) {
			$metatags['site_wide'] = $site_wide_meta;
		}

		/**
		 * Filter the generated meta tags. New filter to allow for easier use by passing an array
		 * instead of a string.
		 *
		 * @param array $metatags   Contains metatag key->value pairs.
		 */
		$metatags = apply_filters( 'amt_metatags_array', $metatags );

		if ( is_array( $metatags ) && ! empty( $metatags ) ) {
			$actual_metatags    = $this->create_metatags( $metatags );
			$metatags_as_string = implode( PHP_EOL, $actual_metatags );

			/**
			 * Filter the generated meta tags. Preserved filter from old code that sends the metatags
			 * as a string.
			 *
			 * @param array $metatags_as_string   Contains each derived metatag as a return-separated string.
			 */
			$metatags_as_string = apply_filters( 'amt_metatags', $metatags_as_string );

			if ( is_string( $metatags_as_string ) ) {
				echo wp_kses( $metatags_as_string . PHP_EOL, $this->get_kses_valid_tags__metatags() );
			}
		}
	}


	/**
	 * Filters the page title used in the document head. Filtered via document_title_parts().
	 *
	 * @param array $title The array of title fragments. See wp_get_document_title() for array elements
	 * @return array
	 */
	public function filter__the_title_parts( $title ) {
		global $posts;

		if ( ! is_single() && ! is_page() || ! $this->is_supported_post_type( $posts[0]->post_type ) ) {
			return $title;
		}

		$cmpvalues = $this->get_enabled_singular_options( $posts[0]->post_type );
		if ( ! isset( $cmpvalues['mt_seo_title'] ) || ! $cmpvalues['mt_seo_title'] ) {
			return $title;
		}

		$mt_seo_title = (string) get_post_meta( $posts[0]->ID, 'mt_seo_title', true );
		if ( empty( $mt_seo_title ) ) {
			return $title;
		}

		$mt_seo_title = $this->do_title_placeholder_substitutions( $title['title'], $mt_seo_title );
		$mt_seo_title = wp_strip_all_tags( $mt_seo_title );

		$title['title'] = $mt_seo_title;
		return $title;
	}


	/**
	 * Get the excerpt while outside the loop. Uses the manually crafted excerpt if found. Otherwise creates a string
	 * based on the post content according to the following rules:
	 *   - Retrieves $excerpt_max_len characters from the post content after stripping shorcodes and HTML.
	 *   - If the derived excerpt contains no period, an ellipsis entity is appended and that string is used.
	 *   - If the derived excerpt contains a period and after truncating on that period the excerpt is > $desc_min_length, that
	 *     is used. Otherwise, an ellipsis entity is appended and that string is used.
	 *
	 * Provides a filter amt_get_the_excerpt() to modify the excerpt before returning it.
	 *
	 * @param object $post               The post object.
	 * @param int    $excerpt_max_len    The maximum excerpt length when it's pulled from content.
	 *                                   from the post content, it must be at least this many characters.
	 * @param int    $desc_min_length    The minimum length for the excerpt.
	 * @return string                    The excerpt.
	 */
	public function get_the_excerpt( $post, $excerpt_max_len = null, $desc_min_length = null ) {

		if ( ! is_object( $post ) || ! is_a( $post, 'WP_Post' ) ) {
			return '';
		}

		if ( ! empty( $post->post_excerpt ) ) {
			$post_excerpt = $post->post_excerpt;
		} else {
			$excerpt_max_len = ( $excerpt_max_len ) ? (int) $excerpt_max_len : $this->excerpt_max_length;
			$desc_min_length = ( $desc_min_length ) ? (int) $desc_min_length : $this->excerpt_min_length;

			$post_content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
			$post_excerpt = substr( $post_content, 0, $excerpt_max_len );

			$excerpt_period_position = strrpos( $post_excerpt, '.' );
			if ( $excerpt_period_position ) {
				$excerpt_ending_on_period = substr( $post_excerpt, 0, $excerpt_period_position + 1 );

				// If the description would be too small, then use an ellipsis.
				if ( strlen( $excerpt_ending_on_period ) < $desc_min_length ) {
					$post_excerpt .= '&hellip;';
				} else {
					$post_excerpt = $excerpt_ending_on_period;
				}
			} else {
				$post_excerpt .= '&hellip;';
			}
		}
		/**
		 * Filter the excerpt as derived by this function.
		 *
		 * @param string  $post_excerpt     The derived excerpt.
		 * @param WP_Post object $posts[0]  The post object being considered.
		 */
		return apply_filters( 'amt_get_the_excerpt', $post_excerpt, $post );
	}


	/**
	 * Get the post categories as a comma-delimited string. False if no categories are found.
	 *
	 * Provides a filter amt_get_the_categories() to modify the categories list before returning it.
	 *
	 * @return bool|string  Comma-separated list of post's categories
	 */
	public function get_post_categories() {
		global $posts;
		$categories_as_string = '';

		$categories = get_the_category( $posts[0]->ID );
		if ( is_array( $categories ) && ! empty( $categories ) ) {
			$category_names       = wp_list_pluck( $categories, 'cat_name' );
			$categories_as_string = implode( ', ', $category_names );
		}

		/**
		 * Filter the categories as derived by this function.
		 *
		 * @param string  $categories_as_string     The derived category list. Comma-separated list.
		 * @param array   $categories               The array of post category objects.
		 */
		return apply_filters( 'amt_get_the_categories', $categories_as_string, $categories );
	}


	/**
	 * Get the post tags as a comma-delimited string. False if no tags are found.
	 *
	 * Provides a filter amt_get_the_tags() to modify the tags list before returning it.
	 *
	 * @return bool|string  Comma-separated list of post's tags
	 */
	public function get_post_tags() {
		global $posts;
		$tags_as_string = '';

		$tags = get_the_tags( $posts[0]->ID );
		if ( is_array( $tags ) && ! empty( $tags ) ) {
			$tag_names      = wp_list_pluck( $tags, 'name' );
			$tags_as_string = implode( ', ', $tag_names );
		}
		// MD: This is done to tags but not categories, Should not be done here IMHO.
		// $tag_list = strtolower( rtrim( $tag_list, ' ,' ) );

		/**
		 * Filter the categories as derived by this function.
		 *
		 * @param string  $tags_as_string     The derived tag list. Comma-separated list.
		 * @param array   $tags               The array of post tag objects.
		 */
		return apply_filters( 'amt_get_the_tags', $tags_as_string, $tags );
	}


	/**
	 * Get the 20 most popular categories, optionally excluding 'Uncategorized' from the list.
	 *
	 * @param bool $no_uncategorized    If true, skip 'Uncategorized' Otherwise, include it.
	 * @return string                   Comma-separated list of site's 20 top categories, or empty string.
	 */
	public function get_site_categories( $no_uncategorized = true ) {
		$popular_category_names = wp_cache_get( 'amt_get_all_categories', 'category' );
		if ( ! $popular_category_names ) {
			$popular_category_names = get_terms(
				array(
					'taxonomy' => 'category',
					'fields'   => 'names',
					'get'      => 'all',
					'number'   => 20, // limit to 20 to avoid killer queries.
					'orderby'  => 'count',
				)
			);
			wp_cache_add( 'amt_get_all_categories', $popular_category_names, 'category', 'WEEK_IN_SECONDS' );
		}

		if ( empty( $popular_category_names ) && ! is_array( $popular_category_names ) ) {
			$categories_as_string = '';
		} else {
			if ( $no_uncategorized ) {
				$uncategorized_position = array_search( 'Uncategorized', $popular_category_names, true );
				if ( false !== $uncategorized_position ) {
					unset( $popular_category_names[ $uncategorized_position ] );
				}
			}
			$categories_as_string = implode( ', ', $popular_category_names );
		}

		/**
		 * Filter the categories as derived by this function.
		 *
		 * @param string  $categories_as_string     The derived category list. Comma-separated list.
		 * @param array   $popular_category_names   The array of found category names.
		 */
		return apply_filters( 'amt_get_all_the_categories', $categories_as_string, $popular_category_names );
	}


	/**
	 * Given a hash of tag->data pairs, returns an array of created metatags.
	 *
	 * @param array $metatags   Array of key -> data pairs for metatags.
	 * @return array
	 */
	public function create_metatags( $metatags ) {
		$actual_metatags = array();
		if ( is_array( $metatags ) && ! empty( $metatags ) ) {
			foreach ( $metatags as $tag => $data ) {
				$actual_metatags[] = $this->create_metatag( $tag, $data );
			}
		}
		return $actual_metatags;
	}


	/**
	 * Create the XML for a single meta tag, escaping and cleaning as we go.
	 *
	 * @param string $tag    The tag name
	 * @param string $data   The tag data
	 * @return string   the XML metatag string
	 */
	public function create_metatag( $tag, $data ) {
		if ( ! is_string( $tag ) || empty( $tag ) || ! is_string( $data ) || empty( $data ) ) {
			return '';
		}

		if ( 'custom' === $tag || 'site_wide' === $tag ) {
			return trim( stripslashes( $data ) );
		}

		// First clean the data
		$clean_data = trim( stripslashes( $data ) );
		// Then to special stuff with particular tags
		switch ( $tag ) {
			case 'keywords':
			case 'news_keywords':
				$clean_data = strtolower( $clean_data );
				break;
			case 'description':
				$clean_data = stripslashes( $clean_data );
				$clean_data = wp_strip_all_tags( $clean_data );
				$clean_data = htmlspecialchars( $clean_data );
				// Collapse all whitespace to a single space, in two steps
				$clean_data = preg_replace( '/([\n \t\r]+)/', ' ', $clean_data );
				$clean_data = preg_replace( '/( +)/', ' ', $clean_data );
				$clean_data = trim( $clean_data );
				break;
		}
		// Finally build up the actual XML, except for the ones we get already rendered
		return '<meta name="' . esc_attr( $tag ) . '" content="' . esc_attr( $clean_data ) . '" />';
	}


	/**
	 * Does all required string substitutions for the title string.
	 *
	 * @param string $title      Original page title
	 * @param string $seo_title  Title string taken from post meta
	 * @return string
	 */
	public function do_title_placeholder_substitutions( $title, $seo_title ) {
		return (string) str_replace( '%title%', $title, $seo_title );
	}

}
