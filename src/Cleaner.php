<?php
/**
 * WP head cleanup.
 */

namespace Leaves_And_Love\WP_Composer_Stack;

class Cleaner implements Runnable, Singleton_Interface {
	use Singleton_Trait;

	public function run() {

		// Clean feed links.
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );

		// Clean generator.
		remove_action( 'wp_head', 'wp_generator' );
		foreach ( array( 'rss2_head', 'commentsrss2_head', 'rss_head', 'rdf_header', 'atom_head', 'comments_atom_head', 'opml_head', 'app_head' ) as $action ) {
			remove_action( $action, 'the_generator' );
		}

		// Clean recent comments style.
		add_action( 'widgets_init', array( $this, 'remove_recent_comments_style' ) );

		// Clean API link.
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );

		// Clean emoji scripts.
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		add_filter( 'tiny_mce_plugins', array( $this, 'disable_emojicons_tinymce' ) );
	}

	public function remove_recent_comments_style() {
		global $wp_widget_factory;

		if ( isset( $wp_widget_factory->widgets['WP_Widget_Recent_Comments'] ) ) {
			remove_action( 'wp_head', array( $wp_widget_factory->widgets['WP_Widget_Recent_Comments'], 'recent_comments_style' ) );
		}
	}

	public function disable_emojicons_tinymce( $plugins ) {
		if ( ! is_array( $plugins ) ) {
			return array();
		}

		return array_diff( $plugins, array( 'wpemoji' ) );
	}
}
