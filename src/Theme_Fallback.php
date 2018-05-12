<?php
/**
 * Registers default theme directory if nothing else available.
 */

namespace Leaves_And_Love\WP_Composer_Stack;

class Theme_Fallback implements Runnable, Singleton_Interface {
	use Singleton_Trait;

	public function run() {
		if ( ! defined( 'WP_DEFAULT_THEME' ) ) {
			register_theme_directory( ABSPATH . 'wp-content/themes' );
		}
	}
}
