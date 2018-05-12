<?php
/**
 * Adds login branding using the site icon.
 */

namespace Leaves_And_Love\WP_Composer_Stack;

class Branded_Login implements Runnable, Singleton_Interface {
	use Singleton_Trait;

	public function run() {
		add_action( 'login_init', array( $this, 'maybe_initialize' ) );
	}

	public function maybe_initialize() {
		if ( ! has_site_icon() ) {
			return;
		}

		add_filter( 'login_headerurl', array( $this, 'get_url' ) );
		add_filter( 'login_headertitle', array( $this, 'get_title' ) );
		add_action( 'login_head', array( $this, 'print_styles' ) );
	}

	public function get_url() {
		return home_url( '/' );
	}

	public function get_title() {
		return get_bloginfo( 'name', 'display' );
	}

	public function print_styles() {
		$relative_icon_url = str_replace( WP_CONTENT_URL, '../../' . basename( WP_CONTENT_URL ), get_site_icon_url( 192 ) );

		?>
		<style type="text/css">
			.login h1 a {
				width: 96px;
				height: 96px;
				background-image: none, url('<?php echo $relative_icon_url; ?>');
				background-size: 96px;
			}
		</style>
		<?php
	}
}
