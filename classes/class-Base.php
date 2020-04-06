<?php

namespace CNMD\SEO_Meta_Tags;

/**
 * Class Base
 *
 * The base class for the plugin, providing common methods and properties for Admin{} and Front_End{}.
 *
 * @package CNMD\SEO_Meta_Tags
 */
class Base {

	use Settings;

	/**
	 * Base constructor.
	 */
	public function __construct() {
		load_plugin_textdomain( 'add-meta-tags', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}


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
		$supported_post_types = apply_filters( 'amt_supported_post_types', $stored_post_types );

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
		return apply_filters( 'amt_registered_post_types', $registered_post_types );
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
	 * Gets the valid tags and attributes for use with <meta> elements in page source.
	 *
	 * @return array
	 */
	protected function get_kses_valid_tags__metatags() {
		return array(
				'meta' => array(
						'http-equiv' => array(),
						'name'       => array(),
						'property'   => array(),
						'content'    => array(),
				),
		);
	}

}
