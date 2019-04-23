<?php // @codingStandardsIgnoreLine: files are named correctly for this project

/*
Plugin Name: Add SEO Meta Tags
Plugin URI: https://github.com/theMikeD/add-seo-meta-tags
Description: Adds the <em>Description</em> and <em>Keywords</em> XHTML META tags to your blog's <em>front page</em> and to each one of the <em>posts</em>, <em>static pages</em> and <em>category archives</em>. This operation is automatic, but the generated META tags can be fully customized. Please read the tips and all other info provided at the <a href="options-general.php?page=amt_options">configuration panel</a>.
Version: 2.0.1
Author: George Notaras, Automattic, @theMikeD
License: Apache License, Version 2.0

This is a rewritten version of the significantly modified version of the add-meta-tags plugin. The
rewrite was done to bring the code in line with current best practices and standards.

What's new
 * Most if not all method and property names are different
 * Dropped checks for WP < v2.3
 * Updated function calls to use modern functions and filters (such as wp_head -> pre_get_document_title)
 * Clean phpcs
 * Plugin file name is different (and more accurate now)
 * Plugin name as it appears in the Plugin list is different (and more accurate now)
 * Plugin menu name and Options page name is different (and more accurate now)
 * Options page and meta boxes are generated using proper APIs instead of raw HTML
 * Options page and meta box instructions text is clarified
 * Output Escaping
 * New filters
 * Removed the reset button on the options panel. Too dangerous.
 * Removes hardcoded strings in the JS and replaces them with localized strings.
 * JS counter for title now works correctly when a blank title is in place
 * Filter mt_seo_title_append_separator() was removed because wp_title() is deprecated and the replacement filters
   don't provide a way to specify which side the separator appears on.

What's the same
1. Saved option names
2. Existing filters except mt_seo_title_append_separator() (see note in What's New)

---
Rewrite by @theMikeD
---
Original plugin by George Notaras (http://www.g-loaded.eu).
Additional contributions by Thorsten Ott, Josh Betz, and others.
Version 2 re-written by Mike Dickson @theMikeD
---
Copyright 2007 George Notaras <gnot [at] g-loaded.eu>, CodeTRAX.org

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

---

Things I'd like to do but would be breaking changes
@todo: change filter names to be more descriptive. ex.: amt_desc_value to amt_max_description_length
@todo: add separate sections for each custom post type (right now CPT gets treated the same as post)
@todo: there is a case for duplicate tags if site_wide and per-page custom are set to the same thing. This should be accounted for
@todo: we use both %title% and %%TITLE%% as string substitution. Doh.
*/

/**
 * Class Add_Meta_Tags
 */
class Add_Meta_Tags {
	/**
	 * Include/Exclude the "keywords" metatag. If set to false, the 'keywords' meta tag will not be generated.
	 * Has no effect on any other tag, including 'description.'
	 */
	const INCLUDE_KEYWORDS_IN_SINGLE_POSTS = true;

	/**
	 * @var array $mt_seo_fields
	 *
	 * Container for the SEO content and descriptive text for per-page entries
	 */
	private $mt_seo_fields = array();

	/**
	 * The maximum length for the description field. Filterable via amt_desc_value
	 *
	 * @var int
	 */
	private $max_desc_length = 141;

	/**
	 * The maximum length for the SEO title. Filterable via amt_title_length()
	 *
	 * @var int
	 */
	private $max_title_length = 71;

	/**
	 * The option key used to save and retrieve our settings in the options table
	 *
	 * @var string
	 */
	private $options_key = 'add_meta_tags_opts';

	/**
	 * Slug for this class.
	 *
	 * @var string
	 */
	private $slug = 'amt_options';

	/**
	 * Stores the options retrieved from the DB
	 *
	 * @var array
	 */
	private $saved_options;

	/**
	 * When the post excerpt is derived from post content, we initially get this many characters.
	 *
	 * @var int
	 */
	private $excerpt_max_length = 300;

	/**
	 * When the post excerpt is derived from post content, we require at least this many characters.
	 *
	 * @var int
	 */
	private $excerpt_min_length = 150;

	/**
	 * The string used for the options page name and admin menu item.
	 *
	 * @var string
	 */
	private $options_page_name = '';

	/**
	 * The string used for the admin menu item.
	 *
	 * @var string
	 */
	private $options_page_menu_name = '';

	/**
	 * Stores the defaults for singular options
	 *
	 * @var array
	 */
	private $default_singular_options = array(
		'mt_seo_title'            => false,
		'mt_seo_description'      => false,
		'mt_seo_keywords'         => false,
		'mt_seo_google_news_meta' => false,
		'mt_seo_meta'             => false,
	);

	/**
	 * Add_Meta_Tags constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );

		// These are the field names and values used on the post edit screen for supported post types.
		$this->mt_seo_fields = array(
			'mt_seo_title'            => array(
				'title'      => __( 'Title', 'add-meta-tags' ),
				'input_type' => 'text',
				'desc'       => __( 'If empty, the post title will be used. <br><b>To customize:</b> The <code>%title%</code> placeholder will be replaced with the post title.', 'add-meta-tags' ),
			),
			'mt_seo_description'      => array(
				'title'      => __( 'Description', 'add-meta-tags' ),
				'input_type' => 'textarea',
				'desc'       => __( 'If empty, the post excerpt will be used.', 'add-meta-tags' ),
			),
			'mt_seo_keywords'         => array(
				'title'      => __( 'Keywords', 'add-meta-tags' ),
				'input_type' => 'text',
				'desc'       =>__( 'Provide a comma-delimited list of keywords. If empty, the post\'s categories and tags will be used. <br><b>To customize:</b> The <code>%cats%</code> placeholder will be replaced with the post\'s categories, and the <code>%tags%</code> placeholder will be replaced with the post\'s tags.', 'add-meta-tags' ) ), // @codingStandardsIgnoreLine: this is not gettext
			'mt_seo_google_news_meta' => array(
				'title'      => __( 'Google News Keywords', 'add-meta-tags' ),
				'input_type' => 'text',
				'desc'       => __( 'Provide a comma-delimited list of up to ten keywords. All keywords are given equal value. If empty, this tag will be skipped.', 'add-meta-tags' ),
			),
			'mt_seo_meta'             => array(
				'title'      => __( 'Additional Meta tags', 'add-meta-tags' ),
				'input_type' => 'textarea',
				'desc'       => __( 'Provide the full XHTML code for each META tag to add. For example: <code>&lt;meta name="robots" content="index,follow" /&gt;</code>', 'add-meta-tags' ),
			),
		);
	}


	/**
	 * Initialize the required hooks
	 *
	 * @return void
	 */
	public function init() {
		// Admin hooks.
		// Loaded automatically; shown in order of load.
		add_action( 'admin_menu', array( $this, 'add_options_panel' ) );
		add_action( 'admin_init', array( $this, 'create_options_page_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts_and_styles' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );

		add_action( 'save_page', array( $this, 'save_meta_for_singular_pages' ) );
		add_action( 'save_post', array( $this, 'save_meta_for_singular_pages' ) );

		// Front end hooks.
		add_action( 'wp_head', array( $this, 'do_meta_tags' ), 0 );
		add_filter( 'document_title_parts', array( $this, 'filter__the_title_parts' ), 1, 4 );

		load_plugin_textdomain( 'add-meta-tags', false, basename( dirname( __FILE__ ) ) . '/languages' );

		// @todo: assign directly? Or maybe can't because of __()
		$this->options_page_name      = __( 'SEO Meta Tags Options', 'add-meta-tags' );
		$this->options_page_menu_name = __( 'SEO Options', 'add-meta-tags' );
	}


	/**
	 * Enqueue the admin CSS and script, including setting up the script's localized values.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts_and_styles() {
		global $pagenow;
		$supported_post_types = $this->get_supported_post_types();

		$viewing_page = '';
		// Register the script for the general options page
		if ( in_array( $pagenow, array( 'options-general.php' ), true ) ) {
			$viewing_page = 'general';
		}

		// Register the script for the singular pages
		if ( ( in_array( $pagenow, array( 'post.php', 'post-new.php', true ), true ) && in_array( get_post_type(), $supported_post_types, true ) ) ) {
			$viewing_page = 'singular';
		}

		// Enqueue the CSS, and enqueue and localize the JS, if we're on a supported post type page, or the option page.
		if ( 'general' === $viewing_page || 'singular' === $viewing_page ) {

			add_action( 'admin_head', array( $this, 'do_inline_styles' ) );

			wp_register_script( 'add-seo-meta-tags', plugins_url( 'js/add-seo-meta-tags.js', __FILE__ ), array( 'jquery' ), '2.0.0', true );
			wp_enqueue_script( 'add-seo-meta-tags' );

			/**
			 * Filter the max length of the description. Result is converted to an absint.
			 *
			 * @param int Max length of the SEO description field
			 */
			$max_desc_length = absint( apply_filters( 'amt_desc_value', $this->max_desc_length ) );

			/**
			 * Filter the max length of the title. Result is converted to an absint.
			 *
			 * @param int Max length of the SEO title field
			 */
			$max_title_length = absint( apply_filters( 'amt_title_length', $this->max_title_length ) );

			$label = array(
				__( 'The %%TITLE%% is limited to %%LIMIT%% characters by search engines.', 'add-meta-tags' ) . ' ',
				' ' . __( 'characters remaining.', 'add-meta-tags' ),
			);

			$label_json = wp_json_encode( $label );

			$values_to_send = array(
				'counter_label'    => $label_json,
				'desc_label'       => __( 'description', 'add-meta-tags' ),
				'title_label'      => __( 'title', 'add-meta-tags' ),
				'max_desc_length'  => $max_desc_length,
				'max_title_length' => $max_title_length,
				'viewing_page'     => $viewing_page,
			);
			wp_localize_script( 'add-seo-meta-tags', 'amt_values', $values_to_send );
		}
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
		return '<meta name="' . esc_attr( $tag  ) . '" content="' . esc_attr( $clean_data ) . '" />';
	}


	/*******************************************************************************************************************
	 * Methods related to the Options screen.
	 */

	/**
	 * Adds the options panel under Settings.
	 *
	 * @return void
	 */
	public function add_options_panel() {
		add_options_page(
			$this->options_page_name,
			$this->options_page_menu_name,
			'administrator',
			$this->slug,
			array( $this, 'do_options_page' )
		);
	}


	/**
	 * Display the options page content.
	 *
	 * @return void
	 */
	public function do_options_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->options_page_name ); ?></h1>
			<form method="POST" action="options.php">
				<?php settings_fields( $this->options_key ); ?>
				<?php do_settings_sections( $this->slug ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}


	/**
	 * Set up the options page fields and callbacks.
	 *
	 * @return void
	 */
	public function create_options_page_fields() {
		/*
		 * Three steps to using the settings API.
		 *
		 * Step 1: register each setting. In our case we will be storing all options for this
		 * plugin as an array in a single entry in the options table, so we only need to call
		 * this once. This is why both values are the same.
		 */
		register_setting( $this->options_key, $this->options_key );

		/*
		 * Step 2: Create the settings section(s).
		 * This is for visual organization only, but at least one is needed.
		 */
		add_settings_section( $this->slug . '_site', __( 'Site-Wide Settings', 'add-meta-tags' ), array( $this, 'do_section_site_wide' ), $this->slug );
		add_settings_section( $this->slug . '_home', __( 'Homepage Settings', 'add-meta-tags' ), array( $this, 'do_section_home' ), $this->slug );
		add_settings_section( $this->slug . '_single', __( 'Post Settings', 'add-meta-tags' ), array( $this, 'do_section_post' ), $this->slug );
		add_settings_section( $this->slug . '_page', __( 'Page Settings', 'add-meta-tags' ), array( $this, 'do_section_page' ), $this->slug );
		add_settings_section( $this->slug . '_notes', __( 'Notes on Other Pages', 'add-meta-tags' ), array( $this, 'do_section_notes' ), $this->slug );

		/*
		Step 3: For each option to be saved, add the settings GUI and callback to the
		appropriate section. One of these for every setting item in our array.
		*/

		// Site options.
		add_settings_field(
			'site_wide_meta',
			__( 'Site-wide META tags', 'add-meta-tags' ),
			array( $this, 'do_site_wide_meta_html' ),
			$this->slug,
			$this->slug . '_site'
		);

		// Home page options.
		add_settings_field(
			'site_description',
			__( 'Homepage Description', 'add-meta-tags' ),
			array( $this, 'do_home_description_html' ),
			$this->slug,
			$this->slug . '_home'
		);
		add_settings_field(
			'site_keywords',
			__( 'Homepage Keywords', 'add-meta-tags' ),
			array( $this, 'do_home_keywords_html' ),
			$this->slug,
			$this->slug . '_home'
		);

		// Post/Page Single options. These checkboxes are added together as a fieldset.
		add_settings_field(
			'mt_seo_title',
			__( 'Enabled Sections', 'add-meta-tags' ),
			array( $this, 'do_post_options_html' ),
			$this->slug,
			$this->slug . '_single'
		);
		// CPT selectors. These checkboxes are added together as a fieldset, but only if there are in fact custom post types..
		if ( $this->valid_custom_post_types_are_present() ) {
			add_settings_field(
				'custom_post_types',
				__( 'Enabled Custom Post Types', 'add-meta-tags' ),
				array( $this, 'do_custom_post_types_html' ),
				$this->slug,
				$this->slug . '_single'
			);
		}

		// Page selectors. These checkboxes are added together as a fieldset.
		add_settings_field(
			'page_options',
			__( 'Enabled Sections', 'add-meta-tags' ),
			array( $this, 'do_page_options_html' ),
			$this->slug,
			$this->slug . '_page'
		);

		// Notes for category archives.
		add_settings_field(
			'taxonomy_archive_notes',
			__( 'Taxonomy Archives', 'add-meta-tags' ),
			array( $this, 'do_taxonomy_archive_notes_html' ),
			$this->slug,
			$this->slug . '_notes'
		);
	}


	/**
	 * Create and echo the descriptive text for the site-wide options section.
	 *
	 * @return void
	 */
	public function do_section_site_wide() {
		echo 'These options are site-wide and will apply to every page.';
	}


	/**
	 * Displays the HTML form element for the site_wide_meta option in the admin options page.
	 *
	 * @return void
	 */
	public function do_site_wide_meta_html() {
		$stored_options = $this->get_saved_options();
		echo '<p>' . esc_attr( __( 'Provide the full XHTML code for each META tag to add to all pages.', 'add-meta-tags' ) ) . '</p>'
			. '<textarea  name="' . esc_attr( $this->options_key ) . '[site_wide_meta]" class="code">'
			. esc_textarea( stripslashes( $stored_options['site_wide_meta'] ) )
			. '</textarea>'
			. '<p><strong>' . esc_attr( __( 'Example', 'add-meta-tags' ) ) . '</strong>: <code>&lt;meta name="robots" content="index,follow" /&gt;</code></p>';
	}


	/**
	 * Create and echo the descriptive text for the homepage options section.
	 *
	 * @return void
	 */
	public function do_section_home() {
		echo 'These options are for the homepage only.';
	}


	/**
	 * Displays the HTML form element for the site_description option in the admin options page.
	 *
	 * @return void
	 */
	public function do_home_description_html() {
		$stored_options = $this->get_saved_options();
		echo '<p>' . esc_html__( 'This text will be used in the "description" meta tag on the homepage only. If empty, the tagline (found on the General Options page) will be used.', 'add-meta-tags' ) . '</p>'
			. '<textarea name="' . esc_attr( $this->options_key ) . '[site_description]" class="code" id="mt_seo_description">'
			. esc_textarea( stripslashes( $stored_options['site_description'] ) )
			. '</textarea>';

	}


	/**
	 * Displays the HTML form element for the site_keywords option in the admin options page.
	 *
	 * @return void
	 */
	public function do_home_keywords_html() {
		$stored_options = $this->get_saved_options();
		echo '<p>' . esc_html__( 'Provide a comma-delimited list of keywords for the homepage only. If empty, all categories (except for the "Uncategorized" category) will be used.', 'add-meta-tags' ) . '</p>';
		echo '<textarea name="' . esc_attr( $this->options_key ) . '[site_keywords]" class="code">' . esc_textarea( stripslashes( $stored_options['site_keywords'] ) ) . '</textarea>';
	}


	/**
	 * Create and echo the descriptive text for the single post and custom post type options section.
	 *
	 * @return void
	 */
	public function do_section_post() {
		echo 'These options are for posts and any custom post types that are enabled (below).';
	}


	/**
	 * Displays the HTML form element for the do_mt_seo_title_html option in the admin options page.
	 *
	 * @return void
	 */
	public function do_post_options_html() {
		$stored_options = $this->get_saved_options();

		if ( ! isset( $stored_options['post_options'] ) ) {
			$stored_options['post_options'] = $this->get_enabled_singular_options( 'post' );
		}

		echo '<fieldset>';
		echo '<legend>' . esc_html__( 'Select the fields to enable on post and custom post type pages.', 'add-meta-tags' ) . '</legend>';
		echo '<ul>';

		$checkbox_value = self::validate_checkbox( $stored_options['post_options'], 'mt_seo_title' );
		echo '<li><input type="checkbox" id="post_mt_seo_title" name="' . esc_attr( $this->options_key ) . '[post_options][mt_seo_title]" value="1" ' . checked( '1', $checkbox_value, false ) . ' />'
			. '<label for="post_mt_seo_title">' . esc_html__( 'Enable \'Title\'', 'add-meta-tags' ) . '</label></li>';

		$checkbox_value = self::validate_checkbox( $stored_options['post_options'], 'mt_seo_description' );
		echo '<li><input type="checkbox" id="post_mt_seo_description" name="' . esc_attr( $this->options_key ) . '[post_options][mt_seo_description]" value="1" ' . checked( '1', $checkbox_value, false ) . ' />'
			. '<label for="post_mt_seo_description">' . esc_html__( 'Enable \'Description\'', 'add-meta-tags' ) . '</label></li>';

		$checkbox_value = self::validate_checkbox( $stored_options['post_options'], 'mt_seo_keywords' );
		echo '<li><input type="checkbox" id="post_mt_seo_keywords" name="' . esc_attr( $this->options_key ) . '[post_options][mt_seo_keywords]" value="1" ' . checked( '1', $checkbox_value, false ) . ' />'
			. '<label for="post_mt_seo_keywords">' . esc_html__( 'Enable \'Keywords\'', 'add-meta-tags' ) . '</label></li>';

		$checkbox_value = self::validate_checkbox( $stored_options['post_options'], 'mt_seo_meta' );
		echo '<li><input type="checkbox" id="post_mt_seo_meta" name="' . esc_attr( $this->options_key ) . '[post_options][mt_seo_meta]" value="1" ' . checked( '1', $checkbox_value, false ) . ' />'
			. '<label for="post_mt_seo_meta">' . esc_html__( 'Enable \'Meta Tags\'', 'add-meta-tags' ) . '</label></li>';

		$checkbox_value = self::validate_checkbox( $stored_options['post_options'], 'mt_seo_google_news_meta' );
		echo '<li><input type="checkbox" id="post_mt_seo_google_news_meta" name="' . esc_attr( $this->options_key ) . '[post_options][mt_seo_google_news_meta]" value="1" ' . checked( '1', $checkbox_value, false ) . ' />'
			. '<label for="post_mt_seo_google_news_meta">' . esc_html__( 'Enable \'Google News Meta Tags\'', 'add-meta-tags' ) . '</label></li>';

		echo '</ul>';
		echo '</fieldset>';
	}


	/**
	 * Displays the HTML form element for the custom_post_types option in the admin options page if
	 * any valid custom post types are present.
	 *
	 * @return void
	 */
	public function do_custom_post_types_html() {

		if ( ! $this->valid_custom_post_types_are_present() ) {
			return;
		}

		echo '<fieldset>';
		echo '<legend>' . esc_html__( 'You can enable the Post Settings for custom post types too. Use the checkboxes below to do so.', 'add-meta-tags' ) . '</legend>';
		echo '<ul>';

		$registered_post_types = $this->get_registered_post_types();
		$supported_post_types  = $this->get_supported_post_types( true );
		foreach ( $registered_post_types as $post_type ) {
			$checkbox_value = self::validate_checkbox( $supported_post_types, $post_type->name );
			echo '<li><input type="checkbox" id="post_type_' . esc_attr( $post_type->name ) . '" name="' . esc_attr( $this->options_key ) . '[custom_post_types][' . esc_attr( $post_type->name ) . ']" value="1" ' . checked( '1', $checkbox_value, false ) . ' />'
				. '<label for="post_type_' . esc_attr( $post_type->name ) . '">' . esc_html__( 'Apply Post Settings to ', 'add-meta-tags' ) . esc_attr( $post_type->labels->name ) . ' (<code>' . esc_attr( $post_type->name ) . '</code>)</label></li>';
		}

		echo '</ul>';
		echo '</fieldset>';
	}


	/**
	 * Create and echo the descriptive text for the page options section.
	 *
	 * @return void
	 */
	public function do_section_page() {
		echo 'These options are for pages.';
	}


	/**
	 * Displays the HTML form element for the page_options option in the admin options page.
	 *
	 * @return void
	 */
	public function do_page_options_html() {
		$stored_options = $this->get_saved_options();

		if ( ! isset( $stored_options['post_options'] ) ) {
			$stored_options['post_options'] = $this->get_enabled_singular_options( 'page' );
		}

		echo '<fieldset>';
		echo '<legend>' . esc_html__( 'Select the fields to enable on pages.', 'add-meta-tags' ) . '</legend>';
		echo '<ul>';

		$checkbox_value = self::validate_checkbox( $stored_options['page_options'], 'mt_seo_title' );
		echo '<li><input type="checkbox" id="page_mt_seo_title" name="' . esc_attr( $this->options_key ) . '[page_options][mt_seo_title]" value="1" ' . checked( '1', $checkbox_value, false ) . ' />'
			. '<label for="page_mt_seo_title">' . esc_html__( 'Enable \'Title\'', 'add-meta-tags' ) . '</label></li>';

		$checkbox_value = self::validate_checkbox( $stored_options['page_options'], 'mt_seo_description' );
		echo '<li><input type="checkbox" id="page_mt_seo_description" name="' . esc_attr( $this->options_key ) . '[page_options][mt_seo_description]" value="1" ' . checked( '1', $checkbox_value, false ) . ' />'
			. '<label for="page_mt_seo_description">' . esc_html__( 'Enable \'Description\'', 'add-meta-tags' ) . '</label></li>';

		$checkbox_value = self::validate_checkbox( $stored_options['page_options'], 'mt_seo_keywords' );
		echo '<li><input type="checkbox" id="page_mt_seo_keywords" name="' . esc_attr( $this->options_key ) . '[page_options][mt_seo_keywords]" value="1" ' . checked( '1', $checkbox_value, false ) . ' />'
			. '<label for="page_mt_seo_keywords">' . esc_html__( 'Enable \'Keywords\'', 'add-meta-tags' ) . '</label></li>';

		$checkbox_value = self::validate_checkbox( $stored_options['page_options'], 'mt_seo_meta' );
		echo '<li><input type="checkbox" id="page_mt_seo_meta" name="' . esc_attr( $this->options_key ) . '[page_options][mt_seo_meta]" value="1" ' . checked( '1', $checkbox_value, false ) . ' />'
			. '<label for="page_mt_seo_meta">' . esc_html__( 'Enable \'Meta Tags\'', 'add-meta-tags' ) . '</label></li>';

		$checkbox_value = self::validate_checkbox( $stored_options['page_options'], 'mt_seo_google_news_meta' );
		echo '<li><input type="checkbox" id="page_mt_seo_google_news_meta" name="' . esc_attr( $this->options_key ) . '[page_options][mt_seo_google_news_meta]" value="1" ' . checked( '1', $checkbox_value, false ) . ' />'
			. '<label for="page_mt_seo_google_news_meta">' . esc_html__( 'Enable \'Google News Meta Tags\'', 'add-meta-tags' ) . '</label></li>';

		echo '</ul>';
		echo '</fieldset>';
	}


	/**
	 * Create and echo the descriptive text for the Notes section.
	 *
	 * @return void
	 */
	public function do_section_notes() {
		echo esc_html__( 'Notes on other specific page types.', 'add-meta-tags' );
	}


	/**
	 * Displays the HTML form element for the taxonomy_archive_notes option in the admin options page.
	 *
	 * @return void
	 */
	public function do_taxonomy_archive_notes_html() {
		echo '<p>' . esc_html__( 'META tags are automatically added to Category, Tag, and Custom Taxonomy Archive pages as follows:', 'add-meta-tags' ) . '</p>';
		echo '<ol>';
		echo '<li>' . esc_html__( 'The term name is set as the "keywords" META tag.', 'add-meta-tags' ) . '</li>';
		echo '<li>' . esc_html__( 'If the term has a description, that description is set as the "description" META tag.', 'add-meta-tags' ) . '</li>';
		echo '</ol>';
		echo '</p>';
	}



	/*******************************************************************************************************************
	 * Methods related to the post edit page meta box.
	 */

	/**
	 * Adds the post edit meta box for supported post types.
	 *
	 * @param string  $post_type  The post type of the current edit page.
	 * @param WP_Post $post       The current post object.
	 *
	 * @return void
	 */
	public function add_meta_box( $post_type, $post ) {
		if ( $this->is_supported_post_type( $post_type ) ) {
			add_meta_box(
				'mt_seo',
				$this->options_page_name,
				array( $this, 'do_meta_box' ),
				$post_type,
				'normal'
			);
		}
	}

	/**
	 * Creates, populates and adds the per-page meta box.
	 *
	 * @param WP_Post $post      Post object.
	 */
	public function do_meta_box( $post ) {
		global $post_type;

		// Bail if this is not a supported post type
		if ( ! $this->is_supported_post_type( $post_type ) ) {
			return;
		}

		$global_values = $this->get_enabled_singular_options( $post_type );

		// Show message if nothing is enabled, then bail
		if ( ! in_array( '1', $global_values, true ) ) {
			// translators: %1$s is replaced with the opening <a> tag with the href set to the options panel, %2$s is the closing <a> tag
			echo '<p>' . esc_html( sprintf( __( 'No SEO fields were enabled. Please enable post fields in the %1$s Meta Tags options page %2$s', 'add-meta-tags' ), '<a href="' . esc_url( get_admin_url() . 'options-general.php?page=' . $this->slug ) . '">', '</a>' ) ) . '</p>';
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

		// Set up the title.
		$title = '';
		if ( '' !== $mt_seo_title ) { // @codingStandardsIgnoreLine: var is defined indirectly in foreach loop above
			$title = str_replace( '%title%', get_the_title(), $mt_seo_title );
		}

		// Make the preview area.
		echo '<div class="form-field mt_seo_preview form_field"><h4>Preview</h4><div class="mt-form-field-contents"><div id="mt_snippet">';
		echo '<a href="#" class="title">' . esc_html( substr( $title, 0, $this->max_title_length ) ) . '</a><br>';
		echo '<a href="#" class="url">' . esc_url( get_permalink() ) . '</a> - <a href="#" class="util">Cached</a>';
		echo '<p class="desc"><span class="date">' . date( 'd M Y', strtotime( get_the_time( 'r' ) ) ) . '</span> &ndash; <span class="content">' . wp_kses_post( substr( $mt_seo_description, 0, 140 ) ) . '</span></p>'; // @codingStandardsIgnoreLine: var is defined indirectly in foreach loop above
		echo '</div></div></div>';

		$tabindex = 5000;
		foreach ( (array) $this->mt_seo_fields as $field_name => $field_data ) {
			if ( empty( $global_values[ $field_name ] ) ) {
				continue;
			}

			if ( 'textarea' === $field_data['input_type'] || 'text' === $field_data['input_type'] ) {
				echo '<div class="form-field ' . esc_attr( $field_name ) . '"><h4><label for="' . esc_attr( $field_name ) . '">' . esc_html( $field_data['title'] ) . '</label></h4><div class="mt-form-field-contents"><p>';

				if ( 'textarea' === $field_data['input_type'] ) {
					echo '<textarea class="wide-seo-box" rows="4" cols="40" tabindex="' . esc_attr( $tabindex ) . '" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_name ) . '">' . esc_textarea( ${$field_name} ) . '</textarea>';
				} elseif ( 'text' === $field_data['input_type'] ) {
					echo '<input type="text" class="wide-seo-box" tabindex="' . esc_attr( $tabindex ) . '" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_name ) . '" value="' . esc_attr( ${$field_name} ) . '" />';
				}
				echo wp_kses(
					'</p><p class="description">' . $field_data['desc'] . '</p></div></div>',
					$this->form_get_kses_valid_tags__metabox_description()
				);
			}
			$tabindex++;
		}

		wp_nonce_field( 'mt-seo', 'mt_seo_nonce', false );

		/*
		 * yoast functionality code is preserved from previous versions, updated only for CS. It is untested.
		*/
		// Remove old Yoast data
		delete_post_meta( $post->ID, '_yoast_wpseo_metadesc' );
		delete_post_meta( $post->ID, '_yoast_wpseo_title' );
	}



	/*******************************************************************************************************************
	 * Methods related to getting, checking and saving options for the main options panel and the post meta box.
	 */

	/**
	 * Retrieves the options from the database and stores them for later use if not already stored.
	 *
	 * @return array
	 */
	public function get_saved_options() {
		if ( empty( $this->saved_options ) ) {
			$saved_options = get_option( $this->options_key );
			if ( ! is_array( $saved_options ) ) {
				$saved_options = array();
			}
			$this->saved_options = $saved_options;
		}
		return $this->saved_options;
	}


	/**
	 * Retrieves the enabled SEO options for singular pages, as defined on the main Settings page. Defaults to everything
	 * disabled.
	 *
	 * @param string $post_type  Post type of current post.
	 * @return array             Array of enabled options.
	 */
	public function get_enabled_singular_options( $post_type ) {
		$options  = get_option( $this->options_key );
		$defaults = $this->default_singular_options;

		$retrieved = array();
		if ( $this->is_supported_post_type( $post_type ) ) {
			if ( 'page' === $post_type && is_array( $options ) && isset( $options['page_options'] ) && is_array( $options['page_options'] ) ) {
				$retrieved = $options['page_options'];
				$this->make_array_values_boolean( $retrieved );
			} elseif ( is_array( $options ) && isset( $options['post_options'] ) && is_array( $options['post_options'] ) ) {
				$retrieved = $options['post_options'];
				$this->make_array_values_boolean( $retrieved );
			}
		}
		return wp_parse_args( $retrieved, $defaults );
	}


	/**
	 * Calls the save routine if required. For backwards-compatibility reasons, each metatag's post meta value is
	 * saved individually instead of as an array with the general options.
	 *
	 * @param int $post_id The post id the meta will be saved against.
	 */
	public function save_meta_for_singular_pages( $post_id ) {
		// Bail if not a valid post type.
		if ( ! isset( $_POST['post_type'] ) || ! $this->is_supported_post_type( $_POST['post_type'] ) ) { // @codingStandardsIgnoreLine: this is fine
			return;
		}

		// Checks to make sure we came from the right page.
		if ( ! wp_verify_nonce( $_POST['mt_seo_nonce'], 'mt-seo' ) ) { // @codingStandardsIgnoreLine: this is fine
			return;
		}

		// If this is an autosave, our form has not been submitted so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Checks user caps.
		$post_type_object = get_post_type_object( $_POST['post_type'] ); // @codingStandardsIgnoreLine: this is fine
		if ( ! current_user_can( $post_type_object->cap->edit_post, $post_id ) ) {
			return;
		}

		foreach ( array_keys( $this->mt_seo_fields ) as $field_name ) {
			self::save_meta_field( $post_id, $field_name );
		}
	}


	/**
	 * Saves a given post meta field for singular post types.
	 *
	 * @param int    $post_id     The post id the meta will be saved against.
	 * @param string $field_name  The field to save.
	 */
	private function save_meta_field( $post_id, $field_name ) {
		// Checks to see if we're POSTing.
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'post' !== strtolower( $_SERVER['REQUEST_METHOD'] ) || ! isset( $_POST[ $field_name ] ) ) { // @codingStandardsIgnoreLine: these are fine
			return;
		}

		// Get old data if it's present.
		$old_data = get_post_meta( $post_id, $field_name, true );

		$data     = isset( $_POST[ $field_name ] ) ? $_POST[ $field_name ] : ''; // @codingStandardsIgnoreLine: this is fine

		/**
		 * Filter the data about to be saved.
		 *
		 * @param string $data         The newly entered data
		 * @param string $field_name   The field to save
		 * @param string $old_data     The previously saved data
		 * @param int    $post_id      The post ID
		 */
		$data = apply_filters( 'mt_seo_save_meta_field', $data, $field_name, $old_data, $post_id );

		// Sanitize.
		if ( 'mt_seo_meta' === $field_name ) {
			$data = wp_kses(
				trim( stripslashes( $data ) ),
				$this->get_kses_valid_tags__metatags()
			);
		} else {
			$data = wp_filter_post_kses( $data );
			$data = trim( stripslashes( $data ) );
		}

		// Nothing new, and we're not deleting the old.
		if ( empty( $data ) && empty( $old_data ) ) {
			return;
		}

		// Nothing to change.
		if ( $data === $old_data ) {
			return;
		}

		// Nothing new, and we're deleting the old.
		if ( empty( $data ) && ! empty( $old_data ) ) {
			delete_post_meta( $post_id, $field_name );
			return;
		}

		// Save the data.
		if ( $old_data ) {
			update_post_meta( $post_id, $field_name, $data );
		} else {
			if ( ! add_post_meta( $post_id, $field_name, $data, true ) ) {
				update_post_meta( $post_id, $field_name, $data ); // Just in case it was deleted and saved as "".
			}
		}

		/*
		 * yoast functionality code is preserved from previous versions, updated only for CS. It is untested.
		*/
		// Remove old Yoast data.
		delete_post_meta( $post_id, '_yoast_wpseo_metadesc' );
		delete_post_meta( $post_id, '_yoast_wpseo_title' );
	}



	/*******************************************************************************************************************
	 * Helper methods.
	 */


	/**
	 * Gets the valid tags and attributes for use with elements related to the meta box in the post/page edit screen.
	 *
	 * @return array
	 */
	private function form_get_kses_valid_tags__metabox_description() {
		return array(
			'b'    => true,
			'br'   => true,
			'code' => true,
			'p'    => array(
				'class' => true,
			),
			'div'  => array(
				'class' => true,
				'id'    => true,
			),
			'span' => array(
				'class' => true,
			),
		);
	}


	/**
	 * Gets the valid tags and attributes for use with <meta> elements in page source.
	 *
	 * @return array
	 */
	private function get_kses_valid_tags__metatags() {
		return array(
			'meta' => array(
				'http-equiv' => array(),
				'name'       => array(),
				'property'   => array(),
				'content'    => array(),
			),
		);
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
			wp_cache_add( 'amt_get_all_categories', $popular_category_names, 'category' );
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
	 * Echos the styles used by the in-page meta box, including the Google search result preview
	 *
	 * @return void
	 */
	public function do_inline_styles() {
		?>
		<style type="text/css">
			.wide-seo-box {	margin: 0; width: 98%; }
			#mt_snippet { width: 540px; background: white; padding: 10px; border: 1px solid #ddd;}
			#mt_snippet .title { color: #11c; font-size: 16px; line-height: 19px; }
			#mt_snippet .url { font-size: 13px; color: #282; line-height: 15px; text-decoration: none; }
			#mt_snippet .util { color: #4272DB; text-decoration: none; }
			#mt_snippet .url:hover, #mt_snippet .util:hover { text-decoration: underline; }
			#mt_snippet .desc { font-size: 13px; color: #000; line-height: 15px; margin: 0; }
			#mt_snippet .date { color: #666; }
			#mt_snippet .content { color: #000; }
			.mt_counter .count { font-weight: bold; }
			.mt_counter .positive { color: green; }
			.mt_counter .negative { color: red; }
			.settings_page_amt_options textarea.code { width: 100%; height: 4em; max-width: 40em; }
		</style>
		<?php
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
	 * Does all required string substitutions for the title string.
	 *
	 * @param string $title      Original page title
	 * @param string $seo_title  Title string taken from post meta
	 * @return string
	 */
	public function do_title_placeholder_substitutions( $title, $seo_title ) {
		return (string) str_replace( '%title%', $title, $seo_title );
	}


	/**
	 * Determines if a supplied post type is supported or not.
	 *
	 * @param string $post_type The post type name.
	 *
	 * @return bool  true if supplied post type is supported; false otherwise.
	 */
	public function is_supported_post_type( $post_type ) {
		$supported = $this->get_supported_post_types();
		return in_array( $post_type, $supported, true );
	}


	/**
	 * Gets the post types stored in the options table
	 *
	 * @param bool $return_hash     If true, return the hash as taken from the options get.
	 *                              If false, return the post type names as a simple array.
	 * @return array                Array or hash of post type names.
	 */
	public function get_supported_post_types( $return_hash = false ) {
		$stored_options = $this->get_saved_options();

		if ( ! isset( $stored_options['custom_post_types'] ) || empty( $stored_options['custom_post_types'] ) ) {
			$stored_options['custom_post_types'] = array();
		}
		$stored_post_types         = $stored_options['custom_post_types'];
		$stored_post_types['post'] = 1;
		$stored_post_types['page'] = 1;

		/**
		 * Modify the list of supported post types. The array contains the list of post types that were set
		 * as supported in the options panel UI.
		 *
		 * @param array $stored_post_types Array of post type objects
		 */
		$supported_post_types = apply_filters( 'add_meta_tags_supported_post_types', $stored_post_types );

		if ( $return_hash ) {
			return $stored_post_types;
		}
		return array_keys( $supported_post_types );

	}


	/**
	 * Gets the list of non-built-in post types enables in the system.
	 *
	 * @return array
	 */
	public function get_registered_post_types() {
		$registered_post_types = get_post_types(
			array(
				'public'   => true,
				'show_ui'  => true,
				'_builtin' => false,
			),
			'objects'
		);

		/**
		 * Modify the list of registered post types. The array contains the non-built-in post types that have
		 * public = true and show_ui = true.
		 *
		 * @param array $registered_post_types Array of post type objects
		 */
		return apply_filters( 'add_meta_tags_registered_post_types', $registered_post_types );
	}


	/**
	 * Small helper function to indicate if valid custom post types are present. Aids in options panel creation.
	 *
	 * @return bool
	 */
	public function valid_custom_post_types_are_present() {
		$registered_post_types = $this->get_registered_post_types();
		if ( ! is_array( $registered_post_types ) || empty( $registered_post_types ) ) {
			return false;
		} else {
			return true;
		}
	}


	/**
	 * Converts all entries of an array into boolean using a simple cast.
	 *
	 * @param array $array Source array.
	 * @return array
	 */
	public function make_array_values_boolean( $array ) {
		$clean = array();
		foreach ( $array as $key => $value ) {
			$clean[ $key ] = (bool) $value;
		}
		return $clean;
	}


	/**
	 * Using the 'checked' function will fail if the option being checked is stored in an array and that array key doesn't
	 * exist. This function will ensure that the value used to compare using 'checked' is always valid.
	 *
	 * @param array  $stored_options        The array of options as retrieved from the database.
	 * @param string $option_to_check       The particular option to check.
	 * @return string
	 */
	private function validate_checkbox( $stored_options, $option_to_check ) {
		$checkbox_value = '';
		// The retrieval method ensures that the options retrieved are always an array but it doesn't hurt to check here.
		if ( ! is_array( $stored_options ) ) {
			return '';
		}
		if ( array_key_exists( $option_to_check, $stored_options ) && false !== $stored_options[ $option_to_check ] ) {
			$checkbox_value = $stored_options[ $option_to_check ];
		}
		return $checkbox_value;
	}

}

$mt_add_meta_tags = new Add_Meta_Tags();

