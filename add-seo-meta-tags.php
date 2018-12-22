<?php // @codingStandardsIgnoreLine: files are named correctly for this project

/*
Plugin Name: Add SEO Meta Tags
Description: Adds the <em>Description</em> and <em>Keywords</em> XHTML META tags to your blog's <em>front page</em> and to each one of the <em>posts</em>, <em>static pages</em> and <em>category archives</em>. This operation is automatic, but the generated META tags can be fully customized. Please read the tips and all other info provided at the <a href="options-general.php?page=amt_options">configuration panel</a>.
Version: 2.0.0
Author: George Notaras, Automattic, @theMikeD
License: Apache License, Version 2.0

This is a rewriteen version of the significantly modified version of the add-meta-tags plugin. The
rewrite was done to bring the code in line with current best practices and standards.

What's new
*  Most if not all method and property names are different
*  Dropped checks for WP < v2.3
*  Updated function calls to use modern functions and filters (such as wp_head -> pre_get_document_title)
*  Clean phpcs
*  Plugin file name is different (and more accurate now)
*  Plugin name as it appears in the Plugin list is different (and more accurate now)
*  Plugin menu name and Options page name is different (and more accurate now)
*  Options page and meta boxes are generated using proper APIs instead of raw HTML
*  Options page and meta box instructions text is clarified
*  So much wp_kses()
*  New filters

What's the same
1. Saved option names
2. Existing filters

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

@todo: make title length a var
@todo: change filter name from amt_desc_value to amt_max_description_length
@todo add message about tags and cats from old single entry options page to meta box
@todo: looks like this applies automatically to CPT that are hidden, such as with seo-auto-linker plugin
@todo: revisit the post types this applies to and add a filter for them

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
	public $mt_seo_fields = array();

	/**
	 * The maximum length for the description field. Filterable via amt_desc_value
	 *
	 * @var string
	 */
	public $max_desc_length = 140;

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
	private $desc_min_length = 150;



	/**
	 * Add_Meta_Tags constructor.
	 *
	 * @theMikeD Pass 1
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );

		// These are the field names and values used on the post edit screen for supported post types.
		$this->mt_seo_fields = array(
			'mt_seo_title'            => array( __( 'Title (optional) :', 'add-meta-tags' ), 'text', __( 'The text entered here will alter the &lt;title&gt; tag using the wp_title() function. Use <code>%title%</code> to include the original title or leave empty to keep original title. i.e.) altered title <code>%title%</code>', 'add-meta-tags' ) ),
			'mt_seo_description'      => array( __( 'Description (optional) :', 'add-meta-tags' ), 'textarea', __( 'This text will be used as description meta information. Left empty a description is automatically generated i.e.) an other description text', 'add-meta-tags' ) ),
			'mt_seo_keywords'         => array( __( 'Keywords (optional) :', 'add-meta-tags' ), 'text', __( 'Provide a comma-delimited list of keywords for your blog. Leave it empty to use the post\'s keywords for the "keywords" meta tag. When overriding the post\'s keywords, the tag <code>%cats%</code> can be used to insert the post\'s categories, add the tag <code>%tags%</code>, to include the post\'s tags i.e. keyword1, keyword2,%tags% %cats%', 'add-meta-tags' ) ), // @codingStandardsIgnoreLine: this is not gettext
			'mt_seo_google_news_meta' => array( __( 'Google News Keywords (optional) :', 'add-meta-tags' ), 'text', __( 'Provide a comma-delimited list of keywords for your blog. You can add up to ten phrases for a given article, and all keywords are given equal value.', 'add-meta-tags' ) ),
			'mt_seo_meta'             => array( __( 'Additional Meta tags (optional) :', 'add-meta-tags' ), 'textarea', __( 'Provide the full XHTML code of META tags you would like to be included in this post/page. i.e.) &lt;meta name="robots" content="index,follow" /&gt;', 'add-meta-tags' ) ),
		);
	}


	/**
	 * Initialize the required hooks
	 *
	 * @theMikeD Pass 1
	 */
	public function init() {
		add_action( 'save_page', array( $this, 'save_singular_meta' ) );
		add_action( 'save_post', array( $this, 'save_singular_meta' ) );

		add_action( 'admin_menu', array( $this, 'amt_add_pages' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );

		add_action( 'wp_head', array( $this, 'do_meta_tags' ), 0 );
		add_action( 'admin_head', array( $this, 'mt_seo_style' ) );
		add_filter( 'pre_get_document_title', array( $this, 'filter_the_title' ), 1, 3 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_action( 'admin_init', array( $this, 'register_settings' ) );

		load_plugin_textdomain( 'add-meta-tags' );
	}


	/**
	 * Enqueue the required script and localize its values.
	 *
	 * @theMikeD Pass 1
	 */
	public function enqueue_scripts() {
		global $pagenow;
		$supported_post_types = $this->get_stored_post_types();

		if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ), true )
			&& in_array( get_post_type(), $supported_post_types, true ) ) {
			wp_register_script( 'add-meta-tags', plugins_url( 'js/add-meta-tags.js', __FILE__ ), array( 'jquery' ), '2.0.0', true );

			/**
			 * Filter the max length of the description. Result is converted to an absint.
			 *
			 * @param int Default max length of the SEO description field
			 */
			$max_desc_length = absint( apply_filters( 'amt_desc_value', $this->max_desc_length ) );

			$amt_values = array( 'max_desc_length' => $max_desc_length );
			wp_localize_script( 'add-meta-tags', 'amt_values', $amt_values );
			wp_enqueue_script( 'add-meta-tags' );
		}
	}


	/**
	 * Adds the post edit meta box for supported post types.
	 *
	 * @theMikeD Pass 1
	 *
	 * @todo: confirm this works with only the specified post types
	 *
	 * @param string  $post_type  The post type of the current edit page.
	 * @param WP_Post $post       The current post object.
	 */
	public function add_meta_box( $post_type, $post ) {
		if ( $this->is_supported_post_type( $post_type ) ) {
			add_meta_box(
				'mt_seo',
				__( 'SEO Options', 'add-meta-tags' ),
				array( $this, 'do_meta_box' ),
				$post_type,
				'normal'
			);
		}
	}


	/**
	 * Adds the options panel under Settings.
	 *
	 * @theMikeD Pass 1
	 */
	public function amt_add_pages() {
		add_options_page(
			__( 'SEO Meta Tags Settings', 'add-meta-tags' ),
			__( 'SEO Meta Tags', 'add-meta-tags' ),
			'administrator',
			$this->slug,
			array( $this, 'do_options_page' )
		);
	}


	/**
	 * Display the options page content.
	 *
	 * @theMikeD Pass 1
	 *
	 * @todo: use var for page title
	 * @since      2.0.0
	 * @return     void
	 */
	public function do_options_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( 'SEO Meta Tags' ); ?></h1>
			<form method="POST" action="options.php">
				<?php settings_fields( $this->options_key ); ?>
				<?php do_settings_sections( $this->slug ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}


	/**
	 * Defines valid options.
	 *
	 * @theMikeD Pass 1
	 *
	 * @since      2.0.0
	 * @return     void
	 */
	public function register_settings() {
		// Three steps to using the settings API.
		// Step 1: register each setting. In our case we will be storing all options for this
		// plugin as an array in a single entry in the options table, so we only need to call
		// this once. This is why both values are the same.
		register_setting( $this->options_key, $this->options_key );

		// Step 2: Create the settings section(s).
		// This is for visual organization only, but at least one is needed.
		add_settings_section( $this->slug . '_site', __( 'Site Settings', 'add-meta-tags' ), array( $this, 'do_section_site' ), $this->slug );
		add_settings_section( $this->slug . '_home', __( 'Homepage Settings', 'add-meta-tags' ), array( $this, 'do_section_home' ), $this->slug );
		add_settings_section( $this->slug . '_single', __( 'Single Post Settings', 'add-meta-tags' ), array( $this, 'do_section_single' ), $this->slug );
		add_settings_section( $this->slug . '_page', __( 'Page Settings', 'add-meta-tags' ), array( $this, 'do_section_page' ), $this->slug );
		add_settings_section( $this->slug . '_notes', __( 'Notes on Other Pages', 'add-meta-tags' ), array( $this, 'do_section_notes' ), $this->slug );
		// add_settings_section( $this->slug . '_reset', __( 'Reset these settings', 'add-meta-tags' ), array( $this, 'do_section_reset' ), $this->slug );
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
			__( 'Site Description', 'add-meta-tags' ),
			array( $this, 'do_site_description_html' ),
			$this->slug,
			$this->slug . '_home'
		);
		add_settings_field(
			'site_keywords',
			__( 'Keywords', 'add-meta-tags' ),
			array( $this, 'do_site_keywords_html' ),
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
		// CPT selectors. These checkboxes are added together as a fieldset.
		add_settings_field(
			'custom_post_types',
			__( 'Enabled Custom Post Types', 'add-meta-tags' ),
			array( $this, 'do_custom_post_types_html' ),
			$this->slug,
			$this->slug . '_single'
		);

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
			'category_archive_notes',
			__( 'Category Archives', 'add-meta-tags' ),
			array( $this, 'do_category_archive_notes_html' ),
			$this->slug,
			$this->slug . '_notes'
		);

	}


	/**
	 * Displays the HTML form element for the custom_post_types option in the admin options page.
	 *
	 * @theMikeD Pass 1
	 */
	public function do_custom_post_types_html() {
		$registered_post_types = $this->get_registered_post_types();

		// This gets a normal array, but validate_checkbox expects a hash. If a CPT is in this this it's an implicit true.
		$saved_post_types = $this->get_stored_post_types( true );

		if ( ! is_array( $registered_post_types ) || empty( $registered_post_types ) ) {
			return;
		}

		echo wp_kses( '<fieldset>', array( 'fieldset' => true ) );
		echo wp_kses(
			'<legend>In addition to regular blog posts, you can enable these options for custom post types. Use the checkboxes below to do so. <br><strong>Note</strong>: to appear in this list, custom post types must be registered with the following parameters: <code>public</code> and  <code>show_ui</code></legend>',
			array(
				'legend' => true,
				'code'   => true,
				'br'     => true,
				'strong' => true,
			)
		);
		echo wp_kses( '<ul>', array( 'ul' => true ) );

		foreach ( $registered_post_types as $post_type ) {
			$checkbox_value = self::validate_checkbox( $saved_post_types, $post_type->name );
			echo wp_kses(
				"<li><input type='checkbox' id='post_type_{$post_type->name}' name='{$this->options_key}[custom_post_types][" . esc_attr( $post_type->name ) . "]' value='1' " . checked( '1', $checkbox_value, false ) . " /><label for='post_type_{$post_type->name}'>" . __( 'Enable ', 'add-meta-tags' ) . wp_strip_all_tags( $post_type->labels->name ) . ' (' . wp_strip_all_tags( $post_type->name ) . ')</label></li>',
				self::get_kses_valid_tags__checkbox()
			);
		}

		echo wp_kses( '</ul>', array( 'ul' => true ) );
		echo wp_kses( '</fieldset>', array( 'fieldset' => true ) );
	}


	/**
	 * Displays the HTML form element for the site_wide_meta option in the admin options page.
	 *
	 * @theMikeD Pass 1
	 *
	 * @todo: remove cols and rows and add css
	 */
	public function do_site_wide_meta_html() {
		$stored_options = $this->get_saved_options();
		echo wp_kses( '<p>' . __( 'Provide the <strong>full XHTML code</strong> of META tags you would like to be included in <strong>all</strong> of your blog pages.', 'add-meta-tags' ) . '</p>', self::get_kses_valid_tags__message() );
		echo wp_kses( "<textarea name='{$this->options_key}[site_wide_meta]' cols='40' rows='10' class='code'>" . esc_textarea( stripslashes( $stored_options['site_wide_meta'] ) ) . '</textarea>', self::get_kses_valid_tags__textarea() );
		echo wp_kses( '<p><strong>' . __( 'Example', 'add-meta-tags' ) . '</strong>: <code>&lt;meta name="robots" content="index,follow" /&gt;</code></p>', self::get_kses_valid_tags__message() );
	}


	/**
	 * Displays the HTML form element for the site_description option in the admin options page.
	 *
	 * @theMikeD Pass 1
	 *
	 * @todo: remove cols and rows and add css
	 * @todo: add note about character limit
	 */
	public function do_site_description_html() {
		$stored_options = $this->get_saved_options();
		echo wp_kses( '<p>' . __( 'The following text will be used in the "description" meta tag on the <strong>homepage only</strong>. If this is left <strong>empty</strong>, then the blog\'s description from the <em>General Options</em> (Tagline) will be used.', 'add-meta-tags' ) . '</p>', self::get_kses_valid_tags__message() );
		echo wp_kses( "<textarea name='{$this->options_key}[site_description]' cols='40' rows='10' class='code'>" . esc_textarea( stripslashes( $stored_options['site_description'] ) ) . '</textarea>', self::get_kses_valid_tags__textarea() );
	}


	/**
	 * Displays the HTML form element for the site_keywords option in the admin options page.
	 *
	 * @theMikeD Pass 1
	 */
	public function do_site_keywords_html() {
		$stored_options = $this->get_saved_options();
		echo wp_kses( '<p>' . __( 'The following keywords will be used for the "keywords" meta tag on the <strong>homepage only</strong>. Provide a comma-delimited list of keywords for your blog. If this field is left <strong>empty</strong>, then all of your blog\'s categories, except for the "Uncategorized" category, will be used as keywords for the "keywords" meta tag.', 'add-meta-tags' ) . '</p>', self::get_kses_valid_tags__message() );
		echo wp_kses( "<textarea name='{$this->options_key}[site_keywords]' cols='40' rows='10' class='code'>" . esc_textarea( stripslashes( $stored_options['site_keywords'] ) ) . '</textarea>', self::get_kses_valid_tags__textarea() );
	}


	/**
	 * Displays the HTML form element for the do_mt_seo_title_html option in the admin options page.
	 *
	 * @theMikeD Pass 1
	 */
	public function do_post_options_html() {
		$stored_options = $this->get_saved_options();

		echo wp_kses( '<fieldset>', array( 'fieldset' => true ) );
		echo wp_kses( '<legend>Select the fields to enable on post and custom post type pages.</legend>', array( 'legend' => true ) );
		echo wp_kses( '<ul>', $this->get_kses_valid_tags__list() );

		$checkbox_value = self::validate_checkbox( $stored_options['post_options'], 'mt_seo_title' );
		echo wp_kses( "<li><input type='checkbox' id='post_mt_seo_title' name='{$this->options_key}[post_options][mt_seo_title]' value='1' " . checked( '1', $checkbox_value, false ) . " /><label for='post_mt_seo_title'>" . __( 'Enable \'Title\'', 'add-meta-tags' ) . '</label></li>', self::get_kses_valid_tags__checkbox() );

		$checkbox_value = self::validate_checkbox( $stored_options['post_options'], 'mt_seo_description' );
		md_log( 'Valid? ' . $checkbox_value );
		echo wp_kses( "<li><input type='checkbox' id='post_mt_seo_description' name='{$this->options_key}[post_options][mt_seo_description]' value='1' " . checked( '1', $checkbox_value, false ) . " /><label for='post_mt_seo_description'>" . __( 'Enable \'Description\'', 'add-meta-tags' ) . '</label></li>', self::get_kses_valid_tags__checkbox() );

		$checkbox_value = self::validate_checkbox( $stored_options['post_options'], 'mt_seo_keywords' );
		echo wp_kses( "<li><input type='checkbox' id='post_mt_seo_keywords' name='{$this->options_key}[post_options][mt_seo_keywords]' value='1' " . checked( '1', $checkbox_value, false ) . " /><label for='post_mt_seo_keywords'>" . __( 'Enable \'Keywords\'', 'add-meta-tags' ) . '</label></li>', self::get_kses_valid_tags__checkbox() );

		$checkbox_value = self::validate_checkbox( $stored_options['post_options'], 'mt_seo_meta' );
		echo wp_kses( "<li><input type='checkbox' id='post_mt_seo_meta' name='{$this->options_key}[post_options][mt_seo_meta]' value='1' " . checked( '1', $checkbox_value, false ) . " /><label for='post_mt_seo_meta'>" . __( 'Enable \'Meta Tags\'', 'add-meta-tags' ) . '</label></li>', self::get_kses_valid_tags__checkbox() );

		$checkbox_value = self::validate_checkbox( $stored_options['post_options'], 'mt_seo_google_news_meta' );
		echo wp_kses( "<li><input type='checkbox' id='post_mt_seo_google_news_meta' name='{$this->options_key}[post_options][mt_seo_google_news_meta]' value='1' " . checked( '1', $checkbox_value, false ) . " /><label for='post_mt_seo_google_news_meta'>" . __( 'Enable \'Google News Meta Tags\'', 'add-meta-tags' ) . '</label></li>', self::get_kses_valid_tags__checkbox() );

		echo wp_kses( '</ul>', $this->get_kses_valid_tags__list() );
		echo wp_kses( '</fieldset>', array( 'fieldset' => true ) );

	}


	/**
	 * Displays the HTML form element for the do_mt_seo_title_html option in the admin options page.
	 *
	 * @theMikeD Pass 1
	 */
	public function do_page_options_html() {
		$stored_options = $this->get_saved_options();

		echo wp_kses( '<fieldset>', array( 'fieldset' => true ) );
		echo wp_kses( '<legend>Select the fields to enable on pages.</legend>', array( 'legend' => true ) );
		echo wp_kses( '<ul>', $this->get_kses_valid_tags__list() );

		$checkbox_value = self::validate_checkbox( $stored_options['page_options'], 'mt_seo_title' );
		echo wp_kses( "<li><input type='checkbox' id='page_mt_seo_title' name='{$this->options_key}[page_options][mt_seo_title]' value='1' " . checked( '1', $checkbox_value, false ) . " /><label for='page_mt_seo_title'>" . __( 'Enable \'Title\'', 'add-meta-tags' ) . '</label></li>', self::get_kses_valid_tags__checkbox() );

		$checkbox_value = self::validate_checkbox( $stored_options['page_options'], 'mt_seo_description' );
		md_log( 'Valid? ' . $checkbox_value );
		echo wp_kses( "<li><input type='checkbox' id='page_mt_seo_description' name='{$this->options_key}[page_options][mt_seo_description]' value='1' " . checked( '1', $checkbox_value, false ) . " /><label for='page_mt_seo_description'>" . __( 'Enable \'Description\'', 'add-meta-tags' ) . '</label></li>', self::get_kses_valid_tags__checkbox() );

		$checkbox_value = self::validate_checkbox( $stored_options['page_options'], 'mt_seo_keywords' );
		echo wp_kses( "<li><input type='checkbox' id='page_mt_seo_keywords' name='{$this->options_key}[page_options][mt_seo_keywords]' value='1' " . checked( '1', $checkbox_value, false ) . " /><label for='page_mt_seo_keywords'>" . __( 'Enable \'Keywords\'', 'add-meta-tags' ) . '</label></li>', self::get_kses_valid_tags__checkbox() );

		$checkbox_value = self::validate_checkbox( $stored_options['page_options'], 'mt_seo_meta' );
		echo wp_kses( "<li><input type='checkbox' id='page_mt_seo_meta' name='{$this->options_key}[page_options][mt_seo_meta]' value='1' " . checked( '1', $checkbox_value, false ) . " /><label for='page_mt_seo_meta'>" . __( 'Enable \'Meta Tags\'', 'add-meta-tags' ) . '</label></li>', self::get_kses_valid_tags__checkbox() );

		$checkbox_value = self::validate_checkbox( $stored_options['page_options'], 'mt_seo_google_news_meta' );
		echo wp_kses( "<li><input type='checkbox' id='page_mt_seo_google_news_meta' name='{$this->options_key}[page_options][mt_seo_google_news_meta]' value='1' " . checked( '1', $checkbox_value, false ) . " /><label for='page_mt_seo_google_news_meta'>" . __( 'Enable \'Google News Meta Tags\'', 'add-meta-tags' ) . '</label></li>', self::get_kses_valid_tags__checkbox() );

		echo wp_kses( '</ul>', $this->get_kses_valid_tags__list() );
		echo wp_kses( '</fieldset>', array( 'fieldset' => true ) );

	}



	/**
	 * Displays the HTML form element for the do_mt_seo_title_html option in the admin options page.
	 *
	 * @todo: does this apply to tags and custom taxonomies as well?
	 * @theMikeD Pass 1
	 */
	public function do_category_archive_notes_html() {
		echo wp_kses(
			'<p>META tags are automatically added to Category Archives as follows:</p>',
			$this->get_kses_valid_tags__message()
		);
		echo wp_kses( '<ol>', $this->get_kses_valid_tags__list() );
		echo wp_kses(
			'<li>The category name is added to the "keywords" META tag.</li>',
			$this->get_kses_valid_tags__list()
		);
		echo wp_kses(
			'<li>If the category has a description, that description is added to the "description" META tag.</li>',
			$this->get_kses_valid_tags__list()
		);
		echo wp_kses( '</ol>', $this->get_kses_valid_tags__list() );
		echo wp_kses( '</p>', $this->get_kses_valid_tags__message() );
	}



	/**
	 * Using the 'checked' function will fail if the option being checked is stored in an array and that array key doesn't
	 * exist. This function will ensure that the value used to compare using 'checked' is always valid.
	 *
	 * @theMikeD Pass 1
	 *
	 * @param array  $stored_options        The array of options as retrieved from the database.
	 * @param string $option_to_check       The particular option to check.
	 * @return string
	 */
	private function validate_checkbox( $stored_options, $option_to_check ) {
		$checkbox_value = '';
		md_log( $stored_options );
		// The retrieval method ensures that the options retrieved are always an array but it doesn't hurt to check here.
		if ( ! is_array( $stored_options ) ) {
			return '';
		}
		if ( array_key_exists( $option_to_check, $stored_options ) && false !== $stored_options[ $option_to_check ] ) {
			$checkbox_value = $stored_options[ $option_to_check ];
		}
		return $checkbox_value;
	}


	/**
	 * Retrieves the options from the database and stores them for later use if not already stored.
	 *
	 * @theMikeD Pass 1
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
	 * Retrieves the enabled SEO options for singular pages, as defined on the main Settings page.
	 *
	 * @param string $post_type  Post type of current post.
	 * @return array             Array of enabled options.
	 */
	public function get_enabled_singular_options( $post_type ) {
		$options   = get_option( $this->options_key );
		$retrieved = array(
			'mt_seo_title'       => true,
			'mt_seo_description' => true,
			'mt_seo_keywords'    => true,
			'mt_seo_meta'        => true,
		);
		if ( $this->is_supported_post_type( $post_type ) ) {
			if ( 'page' === $post_type ) {
				$retrieved = $options['page_options'];
			} else {
				$retrieved = $options['post_options'];
			}
		}
		return $this->make_array_values_boolean( $retrieved );
	}


	/**
	 * Gets the valid tags and attributes for use with <textarea> elements in the admin options page and meta boxes.
	 *
	 * @theMikeD Pass 1
	 *
	 * @return array
	 */
	private function get_kses_valid_tags__checkbox() {
		return array(
			'li'    => true,
			'input' => array(
				'name'    => true,
				'checked' => true,
				'type'    => true,
				'value'   => true,
				'id'      => true,
			),
		);
	}


	/**
	 * Gets the valid tags and attributes for use with <textarea> elements in the admin options page and meta boxes.
	 *
	 * @theMikeD Pass 1
	 *
	 * @return array
	 */
	private function get_kses_valid_tags__list() {
		return array(
			'ul' => true,
			'ol' => true,
			'li' => true,
		);
	}

	/**
	 * Gets the valid tags and attributes for use with <textarea> elements in the admin options page and meta boxes.
	 *
	 * @theMikeD Pass 1
	 *
	 * @return array
	 */
	private function get_kses_valid_tags__textarea() {
		return array(
			'textarea' => array(
				'name'     => true,
				'cols'     => true,
				'rows'     => true,
				'class'    => true,
				'tabindex' => true,
				'id'       => true,
			),
		);
	}


	/**
	 * Gets the valid tags and attributes for use with <input type='text'> elements in the admin options page and meta boxes.
	 *
	 * @theMikeD Pass 1
	 *
	 * @todo: unused?
	 * @return array
	 */
	private function get_kses_valid_tags__text_input() {
		return array(
			'input' => array(
				'type'     => true,
				'class'    => true,
				'tabindex' => true,
				'name'     => true,
				'id'       => true,
				'value'    => true,
			),
		);
	}


	/**
	 * Gets the valid tags and attributes for use with general messages in the admin options page and meta boxes.
	 *
	 * @theMikeD Pass 1
	 * @return array
	 */
	private function get_kses_valid_tags__message() {
		return array(
			'strong' => true,
			'p'      => true,
			'code'   => true,
			'em'     => true,
		);
	}


	/**
	 * Gets the valid tags and attributes for use with elements related to the meta box in the post/page edit screen.
	 *
	 * @theMikeD Pass 1
	 *
	 * @return array
	 */
	private function form_get_kses_valid_tags__metabox() {
		return array(
			'h4'    => true,
			'br'    => true,
			'a'     => array(
				'href'  => true,
				'class' => true,
			),
			'div'   => array(
				'class' => true,
				'id'    => true,
			),
			'span'  => array(
				'class' => true,
			),
			'p'     => array(
				'class' => true,
			),
			'label' => array(
				'for' => true,
			),
		);
	}


	/**
	 * Gets the valid tags and attributes for use with <meta> elements in page source.
	 *
	 * @theMikeD Pass 1
	 *
	 * @return array
	 */
	private function get_kses_valid_tags__metatags() {
		return array(
			'meta' => array(
				'name'    => true,
				'content' => true,
			),
		);
	}


	/**
	 * Validate the entered string.
	 *
	 * @since      0.0.1
	 *
	 * @param      string $input   The string as entered by the user.
	 * @return     string          The filtered string or empty if tests failed.
	 */
	public function validate_options( $input ) {
		return $input;
	}


	/**
	 * Create and echo the descriptive text for the site-wide options section.
	 *
	 * @theMikeD Pass 1
	 */
	public function do_section_site() {
		echo wp_kses( 'These options are site-wide and will apply to every page.', self::get_kses_valid_tags__message() );
	}


	/**
	 * Create and echo the descriptive text for the homepage options section.
	 *
	 * @theMikeD Pass 1
	 */
	public function do_section_home() {
		echo wp_kses( 'These options are for the homepage only.', self::get_kses_valid_tags__message() );
	}


	/**
	 * Create and echo the descriptive text for the single post and custom post type options section.
	 *
	 * @theMikeD Pass 1
	 */
	public function do_section_single() {
		echo wp_kses( 'These options are for posts and any custom post types that are enabled (below).', self::get_kses_valid_tags__message() );
	}


	/**
	 * Create and echo the descriptive text for the page options section.
	 *
	 * @theMikeD Pass 1
	 */
	public function do_section_page() {
		echo wp_kses( 'These options are for pages.', self::get_kses_valid_tags__message() );
	}


	/**
	 * Create and echo the descriptive text for the Notes section.
	 *
	 * @theMikeD Pass 1
	 */
	public function do_section_notes() {
		echo wp_kses( 'Notes on other specific page types.', self::get_kses_valid_tags__message() );
	}

	/*
	// Create and echo the descriptive text for the Reset section.
	// function do_section_reset() {
	// echo wp_kses( 'Clicking the button belo.', self::get_kses_valid_tags__message() );
	// }
	*/

	/**
	 * Get the excerpt while outside the loop. Uses the manually crafted excerpt if found. Otherwise creates a string
	 * based on the post content according to the following rules:
	 *   - Retrieves $excerpt_max_len characters from the post content after stripping shorcodes and HTML.
	 *   - If the derived excerpt contains no period, an ellipsis entitiy is appended and that string is used.
	 *   - If the derived excerpt contains a period and after truncating on that period the excerpt is > $desc_min_length, that
	 *     is used. Otherwise, an ellipsis entity is appended and that string is used.
	 *
	 * Provides a filter amt_get_the_excerpt() to modify the excerpt before returning it.
	 *
	 * @theMikeD Pass 1
	 *
	 * @param int $excerpt_max_len    The maximum excerpt length when it's pulled from content.
	 * @param int $desc_avg_length    The expected length of the excerpt. When teh excerpt is pulled.
	 *                                from the post content, it must be at least this many characters.
	 * @param int $desc_min_length    The minimum length for the excerpt.
	 * @return string                 The excerpt.
	 */
	public function get_the_excerpt( $excerpt_max_len, $desc_avg_length, $desc_min_length ) {
		global $posts;

		if ( ! empty( $posts[0]->post_excerpt ) ) {
			$post_excerpt = $posts[0]->post_excerpt;
		} else {
			$excerpt_max_len = ( $excerpt_max_len ) ? (int) $excerpt_max_len : $this->excerpt_max_length;
			// $desc_avg_length = ( $desc_avg_length ) ? (int) $desc_avg_length : $this->desc_avg_length;
			$desc_min_length = ( $desc_min_length ) ? (int) $desc_min_length : $this->desc_min_length;

			$post_content = wp_strip_all_tags( strip_shortcodes( $posts[0]->post_content ) );
			$post_excerpt = substr( $post_content, 0, $excerpt_max_len );

			// If excerpt is not enough, get more clean data for the description (nasty hack).
			/*
			MD: This code makes no sense.
			if ( strlen( $amt_excerpt ) < $desc_avg_length ) {
				$amt_excerpt = substr( $post_content, 0, (int) ( $excerpt_max_len * 1.5 ) );
				if ( strlen( $amt_excerpt ) < $desc_avg_length ) {
					$amt_excerpt = substr( $post_content, 0, (int) ( $excerpt_max_len * 2 ) );
				}
			}
			*/

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
		return apply_filters( 'amt_get_the_excerpt', $post_excerpt, $posts[0] );
	}


	/**
	 * Get the post categories as a comma-delimited string. False if no categories are found.
	 *
	 * Provides a filter amt_get_the_categories() to modify the categories list before returning it.
	 *
	 * @theMikeD Pass 1
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
	 * @theMikeD Pass 1
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
	 * @theMikeD Pass 1
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
	 * Cleans out unwanted characters for use with meta tags.
	 *
	 * @theMikeD Pass 1
	 *
	 * @param string $text The text to clean.
	 * @return string
	 */
	public function clean_meta_tags( $text ) {
		$text = stripslashes( $text );
		$text = trim( $text );
		return $text;
	}

	/**
	 * Creates and echoes the meta tag block for the page header.
	 *
	 * @theMikeD Pass 1
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

		$my_metatags = '';

		// Add META tags to Singular pages.
		if ( is_singular() ) {
			/*
			// MD: This will never run
			// if ( empty( $cmpvalues ) ) {
			// return;
			// }
			// MD: But this will
			// Only do stuff if any of the enabled options is true.
			*/
			if ( ! in_array( true, $cmpvalues, true ) ) {
				return;
			}

			foreach ( (array) $this->mt_seo_fields as $field_name => $field_data ) {
				${$field_name} = (string) get_post_meta( $post->ID, $field_name, true );

				/*
				// @todo: Do we care about yoast?
				// Back-compat with Yoast SEO meta keys
				// if ( '' == ${$field_name} ) {
				// switch ( $field_name ) {
				// case 'mt_seo_title':
				// $yoast_field_name = '_yoast_wpseo_title';
				// break;
				// case 'mt_seo_description':
				// $yoast_field_name = '_yoast_wpseo_metadesc';
				// break;
				// }
				// if ( isset( $yoast_field_name ) ) {
				// ${$field_name} = (string) get_post_meta( $posts[0]->ID, $yoast_field_name, true );
				// }
				// }
				*/
			}

			/*
			Description. Order of preference:
			1. The post meta value for 'mt_seo_description'
			2. The post excerpt
			*/
			if ( true === $cmpvalues['mt_seo_description'] ) {
				$meta_description = '';

				if ( ! empty( $mt_seo_description ) ) {
					$meta_description = $mt_seo_description;
				} elseif ( is_single() ) {
					$meta_description = $this->get_the_excerpt();
				}

				// @todo: add docblock
				$meta_description = apply_filters( 'amt_meta_description', $meta_description );

				if ( ! empty( $meta_description ) ) {
					$my_metatags .= "\n" . '<meta name="description" content="' . esc_attr( $this->clean_meta_description( $meta_description ) ) . '" />';
				}
			}

			// Custom Meta Tags. This is a fully-rendered META tag, so no need to build it up.
			if ( ! empty( $mt_seo_meta ) && true === $cmpvalues['mt_seo_meta'] ) {
				$my_metatags .= "\n" . $mt_seo_meta;
			}

			// Google News Meta. From post meta field "mt-seo-google-news-meta.
			if ( ! empty( $mt_seo_google_news_meta ) && true === $cmpvalues['mt_seo_google_news_meta'] ) {
				$my_metatags .= '<meta name="news_keywords" content="' . esc_attr( $mt_seo_google_news_meta ) . '" />';
			}

			/*
			Title
			Rewrite the title in case a special title is given
			if ( !empty( $mt_seo_title ) ) {
			see function mt_seo_rewrite_tite() which is added as filter for wp_title
			@md: this is not true
			@todo: add filter to title?
			}
			Keywords. Created in the following order
			1. The post meta value for 'mt_seo_keywords'
			2. The post's categories and tags.
			@todo: add docs for string substitution done in this section
			%cats% is replaced by the post's categories.
			%tags% us replaced by the post's tags.
			NOTE: if self::INCLUDE_KEYWORDS_IN_SINGLE_POSTS is FALSE, then keywords
			metatag is not added to single posts.
			*/
			if ( true === $cmpvalues['mt_seo_keywords'] ) {
				if ( ( self::INCLUDE_KEYWORDS_IN_SINGLE_POSTS && is_single() ) || is_page() ) {
					if ( ! empty( $mt_seo_keywords ) ) {
						// If there is a custom field, use it.
						if ( is_single() ) {
							// For single posts, the %cat% tag is replaced by the post's categories.
							$mt_seo_keywords = str_replace( '%cats%', $this->get_post_categories(), $mt_seo_keywords );
							// Also, the %tags% tag is replaced by the post's tags.
							$mt_seo_keywords = str_replace( '%tags%', $this->get_post_tags(), $mt_seo_keywords );
						}
						$my_metatags .= "\n" . '<meta name="keywords" content="' . esc_attr( strtolower( $mt_seo_keywords ) ) . '" />';
					} elseif ( is_single() ) {
						// Add categories and tags for keywords.
						$post_keywords = strtolower( $this->get_post_categories() );
						$post_tags     = strtolower( $this->get_post_tags() );

						$my_metatags .= "\n" . '<meta name="keywords" content="' . esc_attr( $post_keywords . ', ' . $post_tags ) . '" />';
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
				$my_metatags .= "\n" . '<meta name="description" content="' . esc_attr( $this->clean_meta_description( get_bloginfo( 'description' ) ) ) . '" />';
			} else {
				// If $site_description has been set, then use it in the description meta-tag.
				$my_metatags .= "\n" . '<meta name="description" content="' . esc_attr( $this->clean_meta_description( $site_description ) ) . '" />';
			}

			// Keywords.
			if ( empty( $site_keywords ) ) {
				// If $site_keywords is empty, then all the blog's categories are added as keywords.
				$my_metatags .= "\n" . '<meta name="keywords" content="' . esc_attr( $this->get_site_categories() ) . '" />';
			} else {
				// If $site_keywords has been set, then these keywords are used.
				$my_metatags .= "\n" . '<meta name="keywords" content="' . esc_attr( $site_keywords ) . '" />';
			}
		} elseif ( is_tax() || is_tag() || is_category() ) {
			// taxonomy archive page.
			// @todo: this does work for CPT as well, need to add that to main panel.
			$term_desc = term_description();
			if ( $term_desc ) {
				$my_metatags .= "\n" . '<meta name="description" content="' . esc_attr( $this->clean_meta_description( $term_desc ) ) . '" />';
			}

			// The keyword is the term name.
			$term_name = single_term_title( '', false );
			if ( $term_name ) {
				$my_metatags .= "\n" . '<meta name="keywords" content="' . esc_attr( strtolower( $term_name ) ) . '" />';
			}
		}

		if ( $site_wide_meta ) {
			$my_metatags .= $this->clean_meta_tags( $site_wide_meta );
		}

		// WP.com -- allow filtering of the meta tags.
		// @todo: add docblock.
		$my_metatags = apply_filters( 'amt_metatags', $my_metatags );

		if ( $my_metatags ) {
			// @todo: wp_kses
			echo wp_kses( $my_metatags . PHP_EOL, $this->get_kses_valid_tags__metatags() );
		}
	}


	/**
	 * Creates, populates and adds the per-page meta box.
	 *
	 * @theMikeD Pass 1
	 *
	 * @param WP_Post $post      Post object.
	 * @param string  $meta_box  Unused; kept for compatibility.
	 */
	public function do_meta_box( $post, $meta_box ) {
		global $post_type;
		// $this->mt_seo_fields = apply_filters( 'mt_seo_fields', $this->mt_seo_fields, $post, $meta_box );
		foreach ( (array) $this->mt_seo_fields as $field_name => $field_data ) {
			${$field_name} = (string) get_post_meta( $post->ID, $field_name, true );

			/*
			@todo: Do we care about yoast?
			back-compat with Yoast SEO
			if ( '' == ${$field_name} ) {
			switch ( $field_name ) {
			case 'mt_seo_title':
			$yoast_field_name = '_yoast_wpseo_title';
			break;
			case 'mt_seo_description':
			$yoast_field_name = '_yoast_wpseo_metadesc';
			break;
			}
			if ( isset( $yoast_field_name ) ) {
			${$field_name} = (string) get_post_meta( $post->ID, $yoast_field_name, true );
			}
			}
			*/
		}

		$options = $this->get_saved_options();

		// @todo confirm this yikes
		$global_values = null;
		if ( stristr( $post_type, 'page' ) ) {
			$global_values = $options['page_options'];
		} elseif ( stristr( $post_type, 'post' ) ) {
			$global_values = $options['post_options'];
		}

		// @todo: make this a method and use it in init too
		if ( ! is_array( $global_values ) ) {
			$global_values = array(
				'mt_seo_title'            => true,
				'mt_seo_description'      => true,
				'mt_seo_keywords'         => true,
				'mt_seo_meta'             => true,
				'mt_seo_google_news_meta' => true,
			);
		}

		// Confirm the array contains only booleans.
		$global_values = $this->make_array_values_boolean( $global_values );

		/*
		This code is never false because $mt_seo_title is not set
		$title = ( '' == $mt_seo_title ) ? get_the_title() : $mt_seo_title;
		And so neither does this code
		$title = str_replace( '%title%', get_the_title(), $title );
		*/
		$title = get_the_title();

		// Make the preview area.
		echo wp_kses(
			'<div class="form-field mt_seo_preview form_field">',
			$this->form_get_kses_valid_tags__metabox()
		);
		echo wp_kses(
			'<h4>Preview</h4>',
			$this->form_get_kses_valid_tags__metabox()
		);
		echo wp_kses(
			'<div class="mt-form-field-contents">',
			$this->form_get_kses_valid_tags__metabox()
		);
		echo wp_kses(
			'<div id="mt_snippet">',
			$this->form_get_kses_valid_tags__metabox()
		);
		echo wp_kses(
			'<a href="#" class="title">' . substr( $title, 0, 70 ) . '</a><br>',
			$this->form_get_kses_valid_tags__metabox()
		);
		echo wp_kses(
			'<a href="#" class="url">' . get_permalink() . '</a> - <a href="#" class="util">Cached</a>',
			$this->form_get_kses_valid_tags__metabox()
		);
		echo wp_kses(
			'<p class="desc"><span class="date">' . date( 'd M Y', strtotime( get_the_time( 'r' ) ) ) . '</span> &ndash; <span class="content">' . substr( $mt_seo_description, 0, 140 ) . '</span></p>',
			$this->form_get_kses_valid_tags__metabox()
		);
		echo wp_kses(
			'</div></div></div>',
			$this->form_get_kses_valid_tags__metabox()
		);

		$tabindex       = 5000;
		$tabindex_start = 5000;
		foreach ( (array) $this->mt_seo_fields as $field_name => $field_data ) {
			if ( empty( $global_values[ $field_name ] ) ) {
				continue;
			}

			if ( 'textarea' === $field_data[1] || 'text' === $field_data[1] ) {
				echo wp_kses(
					'<div class="form-field ' . esc_attr( $field_name ) . '"><h4><label for="' . $field_name . '">' . $field_data[0] . '</label></h4><div class="mt-form-field-contents"><p>',
					$this->form_get_kses_valid_tags__metabox()
				);

				if ( 'textarea' === $field_data[1] ) {
					echo wp_kses(
						'<textarea class="wide-seo-box" rows="4" cols="40" tabindex="' . $tabindex . '" name="' . $field_name . '" id="' . $field_name . '">' . esc_textarea( ${$field_name} ) . '</textarea>',
						$this->get_kses_valid_tags__textarea()
					);
				} elseif ( 'text' === $field_data[1] ) {
					echo wp_kses(
						'<input type="text" class="wide-seo-box" tabindex="' . $tabindex . '" name="' . $field_name . '" id="' . $field_name . '" value="' . esc_attr( ${$field_name} ) . '" />',
						$this->get_kses_valid_tags__text_input()
					);
				}
				echo wp_kses(
					'</p><p class="description">' . $field_data[2] . '</p></div></div>',
					$this->form_get_kses_valid_tags__metabox()
				);
			}
			$tabindex++;
		}

		if ( $tabindex === $tabindex_start ) {
			echo wp_kses(
				'<p>' . __( 'No SEO fields were enabled. Please enable post fields in the Meta Tags options page', 'add-meta-tags' ) . '</p>',
				$this->get_kses_valid_tags__message()
			);
		}
		wp_nonce_field( 'mt-seo', 'mt_seo_nonce', false );

		// @todo: Do we care about yoast?
		// Remove old Yoast data
		// delete_post_meta( $post->ID, '_yoast_wpseo_metadesc' );
		// delete_post_meta( $post->ID, '_yoast_wpseo_title' );
	}


	/**
	 * Calls the save routine if required
	 *
	 * @theMikeD Pass 1
	 *
	 * @todo: only if post_type is supported
	 * @param int $post_id The post id the meta will be saved against.
	 */
	public function save_singular_meta( $post_id ) {
		// Bail if not a valid post type.
		if ( ! isset( $_POST['post_type'] ) || ! $this->is_supported_post_type( $_POST['post_type'] ) ) { // @codingStandardsIgnoreLine: this is fine
			return;
		}

		// Checks to make sure we came from the right page.
		if ( ! wp_verify_nonce( $_POST['mt_seo_nonce'], 'mt-seo' ) ) { // @codingStandardsIgnoreLine: this is fine
			return;
		}

		$post_type_object = get_post_type_object( $_POST['post_type'] ); // @codingStandardsIgnoreLine: this is fine
		if ( ! current_user_can( $post_type_object->cap->edit_post, $post_id ) ) {
			return;
		}

		// Checks user caps.
		foreach ( (array) $this->mt_seo_fields as $field_name => $field_data ) {
			$this->save_meta_field( $post_id, $field_name );
		}
	}


	/**
	 * Saves a given meta field for singular post types
	 *
	 * @theMikeD Pass 1
	 *
	 * @param int    $post_id     The post id the meta will be saved against.
	 * @param string $field_name  The field to save.
	 */
	public function save_meta_field( $post_id, $field_name ) {
		// Checks to see if we're POSTing.
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'post' !== strtolower( $_SERVER['REQUEST_METHOD'] ) || ! isset( $_POST[ $field_name ] ) ) { // @codingStandardsIgnoreLine: these are fine
			return;
		}

		// Get old data if it's present.
		$old_data = get_post_meta( $post_id, $field_name, true );

		$data     = isset( $_POST[ $field_name ] ) ? $_POST[ $field_name ] : ''; // @codingStandardsIgnoreLine: this is fine

		// @todo: add doc block
		$data = apply_filters( 'mt_seo_save_meta_field', $data, $field_name, $old_data, $post_id );

		// Sanitize.
		if ( 'mt_seo_meta' === $field_name ) {
			$data = wp_kses(
				trim( stripslashes( $data ) ),
				array(
					'meta' => array(
						'http-equiv' => array(),
						'name'       => array(),
						'property'   => array(),
						'content'    => array(),
					),
				)
			);
		} else {
			$data = wp_filter_post_kses( $data );
			$data = trim( stripslashes( $data ) );
		}

		md_log( "Old: $old_data" );
		md_log( "New: $data" );
		// Nothing new, and we're not deleting the old.
		if ( empty( $data ) && empty( $old_data ) ) {
			return;
		}

		// Nothing new, and we're deleting the old.
		if ( empty( $data ) && ! empty( $old_data ) ) {
			delete_post_meta( $post_id, $field_name );
			return;
		}

		// Nothing to change.
		if ( $data === $old_data ) {
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

		// Remove old Yoast data.
		delete_post_meta( $post_id, '_yoast_wpseo_metadesc' );
		delete_post_meta( $post_id, '_yoast_wpseo_title' );
	}


	/**
	 * Echos the styles used by the in-page meta box, including the Google search result preview
	 *
	 * @theMikeD Pass 1
	 *
	 * @todo: only do this on supported post types
	 * @todo: wp_kses for this?
	 * @return void
	 */
	public function mt_seo_style() {
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
		</style>
		<?php
	}

	/**
	 * Filter the title generated for the document head section and modify it as required.
	 *
	 * @theMikeD Pass 1
	 *
	 * @param string $title         The default post title.
	 * @param string $sep           The separator, used between title fragments.
	 * @param string $seplocation   Where the separator should be used, on the left or right.
	 * @return mixed|string         The adjusted title
	 */
	public function filter_the_title( $title, $sep = '', $seplocation = '' ) {
		md_log( __FUNCTION__ );
		global $posts;
		// @todo: is supported post type
		if ( ! is_single() && ! is_page() ) {
			return $title;
		}

		$cmpvalues = $this->get_enabled_singular_options( $posts[0]->post_type );

		if ( ! isset( $cmpvalues['mt_seo_title'] ) || true !== $cmpvalues['mt_seo_title'] ) {
			return $title;
		}

		$mt_seo_title = (string) get_post_meta( $posts[0]->ID, 'mt_seo_title', true );
		if ( empty( $mt_seo_title ) ) {
			return $title;
		}

		$mt_seo_title = str_replace( '%title%', $title, $mt_seo_title );
		$mt_seo_title = wp_strip_all_tags( $mt_seo_title );

		// @todo: add docblock
		if ( apply_filters( 'mt_seo_title_append_separator', true ) && ! empty( $sep ) ) {
			if ( 'right' === $seplocation ) {
				$mt_seo_title .= " $sep ";
			} else {
				$mt_seo_title = " $sep " . $mt_seo_title;
			}
		}
		return $mt_seo_title;
	}


	/**
	 * Determines if a supplied post type is supported or not.
	 *
	 * @theMikeD Pass 1
	 *
	 * @param string $post_type The post type name.
	 *
	 * @return bool  true if supplied post type is supported; false otherwise.
	 */
	public function is_supported_post_type( $post_type ) {
		$supported = $this->get_stored_post_types();
		return in_array( $post_type, $supported, true );
	}


	/**
	 * Gets the post types stored in the options table
	 *
	 * @theMikeD Pass 1
	 *
	 * @param bool $return_hash     If true, return the hash as taken from the options get.
	 *                              If false, return the post type names as a simple array.
	 * @return array                Array or hash of post type names.
	 */
	public function get_stored_post_types( $return_hash = false ) {
		$stored_options = $this->get_saved_options();

		if ( ! isset( $stored_options['custom_post_types'] ) || empty( $stored_options['custom_post_types'] ) ) {
			$stored_options['custom_post_types'] = array();
		}
		$stored_post_types         = $stored_options['custom_post_types'];
		$stored_post_types['post'] = 1;
		$stored_post_types['page'] = 1;

		// @todo: add doc block
		$supported_post_types = apply_filters( 'add_meta_tags_supported_post_types', $stored_post_types );

		if ( $return_hash ) {
			return $stored_post_types;
		}
		return array_keys( $supported_post_types );

	}


	/**
	 * Gets the list of non-built-in post types enables in the system.
	 *
	 * @theMikeD Pass 1
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
		return apply_filters( 'add_meta_tags_registered_post_types', $registered_post_types );
	}


	/**
	 * Converts all entries of an array into boolean using a simple cast.
	 *
	 * @theMikeD Pass 1
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
	 * Filters the description meta tag text. See code for how.
	 *
	 * @theMikeD Pass 1
	 *
	 * @param string $desc Meta Description.
	 * @return string
	 */
	public function clean_meta_description( $desc ) {
		$desc = stripslashes( $desc );
		$desc = wp_strip_all_tags( $desc );
		$desc = htmlspecialchars( $desc );
		// $desc = preg_replace('/(\n+)/', ' ', $desc);
		// Collapse all whitespace to a single space, in two steps
		$desc = preg_replace( '/([\n \t\r]+)/', ' ', $desc );
		$desc = preg_replace( '/( +)/', ' ', $desc );
		return trim( $desc );
	}



}

$mt_add_meta_tags = new Add_Meta_Tags();

