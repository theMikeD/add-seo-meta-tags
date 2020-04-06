<?php

namespace CNMD\SEO_Meta_Tags;

use WP_Post;

/**
 * Class Admin
 *
 * Provide the admin area functionality for the SEO Meta Tags plugin.
 *
 * @package CNMD\SEO_Meta_Tags
 */
class Admin extends Base {

	use Settings;

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
	 * Add_Meta_Tags constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$this->set_hooks();
		$this->set_properties();
	}


	/**
	 * Initialize the required hooks
	 *
	 * @return void
	 */
	public function set_hooks() {
		// Admin hooks.
		// Loaded automatically; shown in order of load.
		add_action( 'admin_menu', array( $this, 'add_options_panel' ) );
		add_action( 'admin_init', array( $this, 'create_options_page_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts_and_styles' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );

		add_action( 'save_page', array( $this, 'save_meta_for_singular_pages' ) );
		add_action( 'save_post', array( $this, 'save_meta_for_singular_pages' ) );
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

			wp_register_script( 'add-seo-meta-tags', CNMD_SMT_URL . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'add-seo-meta-tags.js', array( 'jquery' ), '2.0.0', true );
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
		echo '<p>' . esc_html__( 'Provide the full XHTML code for each META tag to add to all pages.', 'add-meta-tags' ) . '</p>'
			. '<textarea  name="' . esc_attr( $this->options_key ) . '[site_wide_meta]" class="code">'
			. esc_textarea( stripslashes( $stored_options['site_wide_meta'] ) )
			. '</textarea>'
			. '<p><strong>' . esc_html__( 'Example', 'add-meta-tags' ) . '</strong>: <code>&lt;meta name="robots" content="index,follow" /&gt;</code></p>';
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
		echo '<p>' . esc_html__( 'Provide a comma-separated list of keywords for the homepage only. If empty, all categories (except for the "Uncategorized" category) will be used.', 'add-meta-tags' ) . '</p>';
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
			echo '<p>' . wp_kses( sprintf( __( 'No SEO fields were enabled. Please enable post fields in the %1$s Meta Tags options page %2$s', 'add-meta-tags' ), '<a href="' . esc_url( get_admin_url() . 'options-general.php?page=' . $this->slug ) . '">', '</a>' ), array( 'a' => array( 'href' => true ) ) ) . '</p>';
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
	 * Methods related to saving options for the main options panel and the post meta box.
	 */

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

		// If this is doing an autosave, our form has not been submitted so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		    return;
		}

        // If post is being previewed, don't save the meta.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
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
		// @codingStandardsIgnoreStart
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || ! isset( $_POST[ $field_name ] ) ) {
			return;
		}

        $server_post       = strtolower( sanitize_text_field( $_SERVER['REQUEST_METHOD'] ) );
		if ( ! $server_post ) {
			return;
        }

		// Get old data if it's present.
		$old_data          = get_post_meta( $post_id, $field_name, true );

		$server_field_name = sanitize_text_field( $_POST[$field_name] );
		$data              = '';
		if ( $server_field_name ) {
		    $data = $server_field_name;
        }
        // @codingStandardsIgnoreEnd

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


	/**
	 * Set the field names and values used on the post edit screen for supported post types.
	 */
	public function set_properties() {
		// @todo: assign directly? Or maybe can't because of __()
		$this->options_page_name      = __( 'SEO Meta Tags Options', 'add-meta-tags' );
		$this->options_page_menu_name = __( 'SEO Options', 'add-meta-tags' );
		$this->mt_seo_fields          = array(
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

}

