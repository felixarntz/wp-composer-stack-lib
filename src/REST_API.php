<?php
/**
 * REST API URL adjustment.
 */

namespace Leaves_And_Love\WP_Composer_Stack;

class REST_API implements Runnable, Singleton_Interface {
	use Singleton_Trait;

	public function run() {
		add_filter( 'rest_url_prefix', array( $this, 'get_url_prefix' ) );
		add_filter( 'subdirectory_reserved_names', array( $this, 'adjust_url_prefix_reserved_directories' ) );
	}

	public function get_url_prefix() {
		return 'api';
	}

	public function adjust_url_prefix_reserved_directories( $names ) {
		$names[] = 'api';

		return array_diff( $names, array( 'wp-json' ) );
	}
}
