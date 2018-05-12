<?php
/**
 * Handles site and network detection (called from sunrise.php).
 *
 * This class is invoked in sunrise.php.
 * Note that the variables in this code use the modern Multisite terminology (networks of sites).
 */

namespace Leaves_And_Love\WP_Composer_Stack;

class Sunrise implements Runnable, Singleton_Interface {
	use Singleton_Trait;

	public function run() {
		if ( ! is_multisite() ) {
			// skip if not a multisite
			return;
		}

		if ( ! is_subdomain_install() ) {
			// die if a subdirectory install
			wp_die( 'This multisite does not support a subdirectory installation.', 'Multisite Error', array( 'response' => 500 ) );
			exit;
		}

		if ( function_exists( 'wp_cache_add_global_groups' ) ) {
			wp_cache_add_global_groups( array( 'sunrise' ) );
		}

		$domain = Config::get_current_domain();

		$current = wp_cache_get( $domain, 'sunrise' );
		if ( false === $current ) {
			$current = $this->detect_current( $domain );

			$current_store = array( null, null );
			if ( $current[0] ) {
				$current_store[0] = new \stdClass();
				$current_store[0]->blog_id = $current[0]->blog_id;
				$current_store[0]->domain  = $current[0]->domain;
				$current_store[0]->path    = $current[0]->path;
				$current_store[0]->site_id = $current[0]->site_id;
				$current_store[0]->is_ssl  = $current[0]->is_ssl;
			}
			if ( $current[1] ) {
				$current_store[1] = new \stdClass();
				$current_store[1]->id     = $current[1]->id;
				$current_store[1]->domain = $current[1]->domain;
				$current_store[1]->path   = $current[1]->path;
			}

			wp_cache_set( $domain, $current_store, 'sunrise' );
		} else {
			if ( $current[0] ) {
				$current[0] = new WP_Site( $current[0] );
			}
			if ( $current[1] ) {
				$current[1] = new WP_Network( $current[1] );
			}
		}

		$site    = $current[0];
		$network = $current[1];

		if ( ! $network ) {
			$this->fail_gracefully( $domain, 'network' );
			exit;
		}

		if ( ! $site ) {
			$this->fail_gracefully( $domain, 'site' );
			exit;
		}

		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			if ( $site->domain !== $domain ) {
				$this->redirect( $site->domain, $site->is_ssl );
				exit;
			}

			$protocol = Config::get_current_protocol();
			if ( 'http' === $protocol && $site->is_ssl ) {
				$this->redirect( $site->domain, $site->is_ssl );
				exit;
			} elseif ( 'https' === $protocol && ! $site->is_ssl ) {
				$this->redirect( $site->domain, $site->is_ssl );
				exit;
			}
		}

		// if we reach this point, everything has been detected successfully
		$this->define_additional_constants( $site, $network );
		$this->expose_globals( $site, $network );
	}

	protected function detect_current( $domain ) {
		// workaround for CLI
		if ( 'example.com' === $domain ) {
			$site = $this->get_default_site();
		} else {
			$domains = array( $domain );
			if ( 0 === strpos( $domain, 'www.' ) ) {
				$domains[] = substr( $domain, 4 );
			} elseif ( 1 === substr_count( $domain, '.' ) ) {
				$domains[] = 'www.' . $domain;
			}

			$site = $this->detect_site( $domains );
		}

		if ( $site ) {
			$site = $this->detect_site_ssl( $site );

			$network = $this->detect_network( $site );
		} else {
			// try to detect network another way if no site is found
			$network = $this->detect_network( $domains );
			if ( $network ) {
				$site = get_site( $network->site_id );
				if ( $site ) {
					$site = $this->detect_site_ssl( $site );
				}
			}
		}

		return array(
			$site,
			$network,
		);
	}

	protected function detect_site( $domains = array() ) {
		$sites = get_sites( array(
			'domain__in' => $domains,
			'path'       => '/',
			'orderby'    => 'domain_length',
			'order'      => 'DESC',
			'number'     => 1,
		) );

		if ( ! empty( $sites ) ) {
			return $sites[0];
		}

		return null;
	}

	protected function detect_network( $domains_or_site = array() ) {
		if ( is_object( $domains_or_site ) && $domains_or_site instanceof WP_Site ) {
			return get_network( $domains_or_site->network_id );
		}

		$networks = get_networks( array(
			'domain__in' => $domains,
			'path'       => '/',
			'orderby'    => 'domain_length',
			'order'      => 'DESC',
			'number'     => 1,
		) );

		if ( ! empty( $networks ) ) {
			return $networks[0];
		}

		return null;
	}

	protected function get_default_site() {
		$main_network_id = get_main_network_id();
		$main_site_id    = get_main_site_id( $main_network_id );

		if ( $main_site_id ) {
			return get_site( $main_site_id );
		}

		return null;
	}

	protected function detect_site_ssl( $site ) {
		global $wpdb;

		if ( function_exists( 'get_option' ) && function_exists( 'untrailingslashit' ) ) {
			$wpdb->set_blog_id( $site->id, $site->network_id );

			if ( function_exists( 'wp_cache_switch_to_blog' ) ) {
				wp_cache_switch_to_blog( $site->id );
			}

			$home   = get_option( 'home', '' );
		} else {
			$table_name = $wpdb->base_prefix . ( 1 === $site->id ? '' : (string) $site->id . '_' ) . 'options';

			$suppress = $wpdb->suppress_errors();
			$result   = $wpdb->get_row( "SELECT option_value FROM $table_name WHERE option_name = 'home' LIMIT 1" );
			$wpdb->suppress_errors( $suppress );

			$home = is_object( $result ) ? $result->option_value : '';
		}

		$scheme = parse_url( $home, PHP_URL_SCHEME );

		$site->is_ssl = 'https' === $scheme;

		return $site;
	}

	protected function fail_gracefully( $domain, $mode = 'site' ) {
		if ( 'network' === $mode ) {
			do_action( 'ms_network_not_found', $domain, '/' );
		} elseif ( defined( 'NOBLOGREDIRECT' ) && '%siteurl%' !== NOBLOGREDIRECT ) {
			header( 'Location: ' . NOBLOGREDIRECT );
			exit;
		}

		ms_not_installed( $domain, '/' );
	}

	protected function define_additional_constants( $site, $network ) {
		$protocol = $site->is_ssl ? 'https' : 'http';

		if ( ! defined( 'WP_CONTENT_URL' ) ) {
			define( 'WP_CONTENT_URL', $protocol . '://' . $site->domain . '/' . basename( WP_CONTENT_DIR ) );
		}

		if ( ! defined( 'FORCE_SSL_ADMIN' ) ) {
			define( 'FORCE_SSL_ADMIN', $site->is_ssl );
		}
		if ( ! defined( 'FORCE_SSL_LOGIN' ) ) {
			define( 'FORCE_SSL_LOGIN', $site->is_ssl );
		}

		if ( ! defined( 'COOKIEPATH' ) ) {
			define( 'COOKIEPATH', '/' );
		}
		if ( ! defined( 'SITECOOKIEPATH' ) ) {
			define( 'SITECOOKIEPATH', '/' . WP_CORE_DIRNAME . '/' );
		}
		if ( ! defined( 'ADMIN_COOKIE_PATH' ) ) {
			define( 'ADMIN_COOKIE_PATH', '/' . WP_CORE_DIRNAME . '/wp-admin' );
		}
		if ( ! defined( 'COOKIE_DOMAIN' ) ) {
			if ( strlen( $site->domain ) - strlen( $network->domain ) === strpos( $site->domain, $network->domain ) ) {
				// site is a subdomain of the network domain
				define( 'COOKIE_DOMAIN', '.' . $network->domain );
			} else {
				// site is not a subdomain or a subdomain where its second level domain is not the network domain
				define( 'COOKIE_DOMAIN', '.' . $site->domain );
			}
		}
	}

	protected function expose_globals( $site, $network ) {
		global $current_blog, $current_site, $blog_id, $site_id, $public;

		$current_blog = $site;
		$current_site = $network;

		$blog_id = $site->id;
		$site_id = $site->network_id;

		$public = $site->public;

		wp_load_core_site_options( $site_id );
	}

	protected function redirect( $domain, $is_ssl = false ) {
		$protocol = $is_ssl ? 'https' : 'http';
		$path = Config::get_current_path();

		header( 'Location: ' . $protocol . '://' . $domain . $path, true, 301 );
		exit;
	}
}
