<?php
/**
 * Better HTML5 support.
 */

namespace Leaves_And_Love\WP_Composer_Stack;

class HTML5_Support implements Runnable, Singleton_Interface {
	use Singleton_Trait;

	public function run() {
		add_action( 'init', array( $this, 'improve_html5_support' ) );
	}

	public function improve_html5_support() {
		if ( ! current_theme_supports( 'html5' ) ) {
			return;
		}

		add_filter( 'style_loader_tag', array( $this, 'clean_style_tag' ), 10, 3 );
		add_filter( 'script_loader_tag', array( $this, 'clean_script_tag' ), 10, 3 );

		add_filter( 'post_thumbnail_html', array( $this, 'remove_self_closing_tag' ) );
		add_filter( 'get_image_tag', array( $this, 'remove_self_closing_tag' ) );
		add_filter( 'get_avatar', array( $this, 'remove_self_closing_tag' ) );
		add_filter( 'comment_id_fields', array( $this, 'remove_self_closing_tag' ) );
		add_filter( 'style_loader_tag', array( $this, 'remove_self_closing_tag' ) );
	}

	public function clean_style_tag( $html, $handle, $src ) {
		$html = preg_replace( "/(rel|id|title|type|href|media)='(.*)'/U", '$1="$2"', $html );

		return str_replace( array(
			' type="text/css"',
			' media="all"',
		), '', $html );
	}

	public function clean_script_tag( $html, $handle, $src ) {
		$html = preg_replace( "/(type|src)='(.*)'/U", '$1="$2"', $html );

		return str_replace( ' type="text/javascript"', '', $html );
	}

	public function remove_self_closing_tag( $html ) {
		return str_replace( '/>', '>', str_replace( ' />', '>', $html ) );
	}
}
