<?php
/**
 * WP Composer Stack runner.
 */

namespace Leaves_And_Love\WP_Composer_Stack;

class WP_Composer_Stack implements Runnable, Singleton_Interface {
	use Singleton_Trait;

	public function run() {
		Sunrise_Insurance::instance()->run();
		Theme_Fallback::instance()->run();
		URL_Fixer::instance()->run();
		Plugin_Autoloader::instance()->run();
		Security::instance()->run();
		REST_API::instance()->run();
		Cleaner::instance()->run();
		HTML5_Support::instance()->run();
	}
}
