<?php
/**
 * Security improvements.
 */

namespace Leaves_And_Love\WP_Composer_Stack;

class Security implements Runnable, Singleton_Interface {
	use Singleton_Trait;

	public function run() {
		add_filter( 'xmlrpc_enabled', '__return_false' );
	}
}
