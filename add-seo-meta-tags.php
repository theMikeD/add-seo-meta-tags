<?php // @codingStandardsIgnoreLine: files are named correctly for this project

/*
Plugin Name: Add SEO Meta Tags
Plugin URI: https://github.com/theMikeD/add-seo-meta-tags
Description: Adds the <em>Description</em> and <em>Keywords</em> XHTML META tags to your blog's <em>front page</em> and to each one of the <em>posts</em>, <em>static pages</em> and <em>category archives</em>. This operation is automatic, but the generated META tags can be fully customized. Please read the tips and all other info provided at the <a href="options-general.php?page=amt_options">configuration panel</a>.
Version: 2.1.0
Author: @theMikeD, Automattic
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
@todo: create_metatag() dumps out whatever is put into the Additional Meta Tagas section without any sanitization

Things I'd like to do but would be breaking changes
@todo: change filter names to be more descriptive. ex.: amt_desc_value to amt_max_description_length
@todo: add separate sections for each custom post type (right now CPT gets treated the same as post)
@todo: there is a case for duplicate tags if site_wide and per-page custom are set to the same thing. This should be accounted for
@todo: we use both %title% and %%TITLE%% as string substitution. Doh.
*/


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'CNMD_SMT_DIR', plugin_dir_path( __FILE__ ) );
define( 'CNMD_SMT_URL', plugins_url( 'add-seo-meta-tags' ) );

/**
 * The class autoloader.
 */
spl_autoload_register( function( $class ) {
	// We only care about CNMD-namespaced classes
	if ( ! preg_match( '/^CNMD/', $class ) ) {
		return;
	}
	$classname_parts = explode( '\\', $class );
	$classname       = array_pop( $classname_parts );

	// If it's the Settings.php file, it's stored in the plugin root.
	if ( 'Settings' === $classname ) {
		$filename = CNMD_SMT_DIR . DIRECTORY_SEPARATOR . $classname . '.php';
		if ( file_exists( $filename ) ) {
			include_once $filename;
			return;
		}
	}

	// Otherwise, look in the classes/ folder.
	$filename        = CNMD_SMT_DIR . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'class-' . $classname . '.php';
	if ( file_exists( $filename ) ) {
		include_once $filename;
		return;
	}

});


/**
 * Initialize the plugin.
 */
function init_cnmd_seo_meta_tags() {
	if ( is_admin() ) {
		$admin = new CNMD\SEO_Meta_Tags\Admin();
	} else {
		$front_end = new CNMD\SEO_Meta_Tags\Front_End();
	}
}
init_cnmd_seo_meta_tags();
