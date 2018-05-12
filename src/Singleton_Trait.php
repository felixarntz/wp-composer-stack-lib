<?php
/**
 * Trait for a singleton class.
 */

namespace Leaves_And_Love\WP_Composer_Stack;

trait Singleton_Trait {

	/**
	 * Main instance of the class.
	 *
	 * @since 1.0.0
	 *
	 * @var Singleton_Interface|null
	 */
	private static $instance = null;

	/**
	 * Gets the main instance of the class.
	 *
	 * @since 1.0.0
	 *
	 * @return Singleton_Interface Main instance.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
