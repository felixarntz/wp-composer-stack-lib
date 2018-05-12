<?php
/**
 * Fixes home URL and site URL (especially for multisite).
 */

namespace Leaves_And_Love\WP_Composer_Stack;

class URL_Fixer implements Runnable, Singleton_Interface {
	use Singleton_Trait;

	public function run() {
		add_filter( 'option_home', array( $this, 'fix_home_url' ) );
		add_filter( 'sanitize_option_home', array( $this, 'fix_home_url' ) );

		add_filter( 'option_siteurl', array( $this, 'fix_site_url' ) );
		add_filter( 'sanitize_option_siteurl', array( $this, 'fix_site_url' ) );

		add_filter( 'network_site_url', array( $this, 'fix_network_site_url' ), 10, 3 );
	}

	public function fix_home_url( $value ) {
		if ( '/' . WP_CORE_DIRNAME === substr( $value, -5 ) ) {
			$value = substr( $value, 0, -5 );
		}
		return $value;
	}

	public function fix_site_url( $value ) {
		if ( '/' . WP_CORE_DIRNAME !== substr( $value, -5 ) ) {
			$value .= '/' . WP_CORE_DIRNAME;
		}
		return $value;
	}

	public function fix_network_site_url( $url, $path, $scheme ) {
		$path = ltrim( $path, '/' );
		$url = substr( $url, 0, strlen( $url ) - strlen( $path ) );

		if ( WP_CORE_DIRNAME . '/' !== substr( $url, -5 ) ) {
			$url .= WP_CORE_DIRNAME . '/';
		}

		return $url . $path;
	}
}
