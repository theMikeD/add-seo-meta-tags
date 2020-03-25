<?php

namespace CNMD\SEO_Meta_Tags;


/**
 * Trait Settings
 *
 * Provides a common spot for settings and properties needed by multiple classes.
 *
 * @package CNMD\SEO_Meta_Tags
 */
trait Settings {

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
	 * When the post excerpt is derived from post content, we initially get this many characters.
	 *
	 * @var int
	 */
	protected $excerpt_max_length = 300;

	/**
	 * When the post excerpt is derived from post content, we require at least this many characters.
	 *
	 * @var int
	 */
	protected $excerpt_min_length = 150;


	/**
	 * @var array $mt_seo_fields
	 *
	 * Container for the SEO content and descriptive text for per-page entries
	 */
	private $mt_seo_fields = array();


	/**
	 * Stores the options retrieved from the DB
	 *
	 * @var array
	 */
	private $saved_options;

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

}
