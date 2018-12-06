<?php
/**
 * Handles everything that is usually done by wp-config.php
 */

namespace Leaves_And_Love\WP_Composer_Stack;

class Config implements Runnable, Singleton_Interface {
	use Singleton_Trait;

	/**
	 * Data required for constants definition.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * Information about project (read from Composer).
	 *
	 * @var array
	 */
	protected $info;

	/**
	 * Additional settings (read from Composer).
	 *
	 * @var array
	 */
	protected $settings;

	public function run() {
		if ( ! defined( 'WP_CORE_DIRNAME' ) ) {
			define( 'WP_CORE_DIRNAME', 'core' );
		}

		$this->data = $this->info = $this->settings = array();

		$this->data['content_dir'] = str_replace( '/mu-plugins/wp-composer-stack-lib/src', '', dirname( __FILE__ ) );
		$this->data['webroot_dir'] = dirname( $this->data['content_dir'] );
		$this->data['root_dir'] = dirname( $this->data['webroot_dir'] );

		$this->data['server_protocol'] = self::get_current_protocol();
		$this->data['server_name'] = self::get_current_domain();
		$this->data['server_port'] = self::get_current_port();

		$this->data['server_url'] = $this->data['server_protocol'] . '://' . $this->data['server_name'] . ( ! empty( $this->data['server_port'] ) ? ':' . $this->data['server_port'] : '' );

		$this->data['required'] = $this->get_required_constants();
		$this->data['protected'] = $this->get_protected_constants();

		$this->load_dotenv();
		$this->load_composer();

		$this->data['wp_env'] = $this->get_constant_setting( 'WP_ENV' );
		if ( false === $this->data['wp_env'] || ! in_array( $this->data['wp_env'], array( 'production', 'staging', 'development' ), true ) ) {
			$this->data['wp_env'] = 'production';
		}

		if ( 'development' !== $this->data['wp_env'] ) {
			ini_set( 'display_errors', 0 );
		}

		$this->define_constants();

		// Redirect to SSL/Non-SSL if constant is defined, we're not in multisite, and it's not a CLI request.
		if ( defined( 'IS_SSL' ) && ( ! defined( 'MULTISITE' ) || ! MULTISITE ) && ( ! defined( 'WP_CLI' ) || ! WP_CLI ) ) {
			$desired_protocol = IS_SSL ? 'https' : 'http';

			if ( $desired_protocol !== $this->data['server_protocol'] ) {
				header( 'Location: ' . $desired_protocol . '://' . $this->data['server_name'] . self::get_current_path(), true, 301 );
				exit;
			}
		}
	}

	public function get_info( $field = null ) {
		if ( $field !== null ) {
			if ( isset( $this->info[ $field ] ) ) {
				return $this->info[ $field ];
			}
			return false;
		}

		return $this->info;
	}

	public function get_setting( $key, $default = false ) {
		if ( isset( $this->settings[ $key ] ) ) {
			return $this->settings[ $key ];
		}
		return $default;
	}

	protected function load_dotenv() {
		$dotenv = new \Dotenv\Dotenv( $this->data['root_dir'] );
		if ( file_exists( $this->data['root_dir'] . '/.env' ) ) {
			try {
				$dotenv->load();
				$dotenv->required( $this->data['required'] );
			} catch( \Exception $e ) {
				$this->bail( $e->getMessage() );
			}
		}
	}

	protected function load_composer() {
		$composer = array();
		try {
			$_composer = file_get_contents( $this->data['root_dir'] . '/composer.json' );
			$composer = json_decode( $_composer, true );
			switch ( json_last_error() ) {
				case JSON_ERROR_DEPTH:
					$message = 'Maximum stack exceeded.';
					break;
				case JSON_ERROR_CTRL_CHAR:
					$message = 'Unexpected control character found.';
					break;
				case JSON_ERROR_SYNTAX:
					$message = 'Syntax error in file.';
					break;
				default:
					$message = '';
			}
			if ( ! empty( $message ) ) {
				$this->bail( 'JSON Parse Error - ' . $message );
			}
		} catch ( \Exception $e ) {
			$this->bail( $e->getMessage() );
		}

		$info_fields = array(
			'name', 'version', 'description', 'type', 'license', 'homepage', 'authors', 'keywords'
		);
		foreach ( $info_fields as $info_field ) {
			if ( isset( $composer[ $info_field ] ) ) {
				$this->info[ $info_field ] = $composer[ $info_field ];
			}
		}

		$this->data['composer'] = array();
		if ( isset( $composer['extra'] ) ) {
			if ( isset( $composer['extra']['constants'] ) ) {
				$constants = $this->normalize_constants( $composer['extra']['constants'] );
				foreach ( $constants as $constant => $value ) {
					if ( ! is_array( $value ) ) {
						$this->data['composer'][ $constant ] = $value;
					}
				}
			}

			if ( isset( $composer['extra']['settings'] ) ) {
				foreach ( $composer['extra']['settings'] as $setting => $value ) {
					$this->settings[ $setting ] = $this->normalize_value( $value );
				}
			}

			$environment = $this->get_constant_setting( 'WP_ENV' );
			if ( null !== $environment ) {
				$environment = $this->normalize_value( $environment );
			} else {
				$environment = 'production';
			}

			if ( isset( $composer['extra'][ 'settings_' . $environment ] ) ) {
				foreach ( $composer['extra'][ 'settings_' . $environment ] as $setting => $value ) {
					$this->settings[ $setting ] = $this->normalize_value( $value );
				}
			}
		}
	}

	protected function define_constants() {
		$constants = $this->get_default_constants();

		foreach ( $constants as $constant => $default ) {
			if ( ! in_array( $constant, $this->data['protected'], true ) ) {
				$value = $this->get_constant_setting( $constant );
				if ( null !== $value ) {
					$value = $this->normalize_value( $value );
					define( $constant, $value );
				}
			} elseif ( defined( $constant ) ) {
				$this->bail( sprintf( 'The constant %s must not be defined.', $constant ) );
			}

			if ( ! defined( $constant ) && null !== $default ) {
				define( $constant, $default );
			}
		}

		foreach ( $_ENV as $constant => $value ) {
			if ( false === strpos( $constant, '-' ) && strtoupper( $constant ) === $constant ) {
				if ( ! in_array( $constant, $this->data['protected'], true ) && ! defined( $constant ) ) {
					define( $constant, $this->normalize_value( $value ) );
				}
			}
		}

		foreach ( $this->data['composer'] as $constant => $value ) {
			if ( ! in_array( $constant, $this->data['protected'], true ) && ! defined( $constant ) ) {
				define( $constant, $this->normalize_value( $value ) );
			}
		}

		define( 'WP_CONTENT_DIR', $this->data['content_dir'] );

		if ( defined( 'MULTISITE' ) && MULTISITE ) {
			define( 'SUBDOMAIN_INSTALL', true );
			define( 'ALLOW_SUBDIRECTORY_INSTALL', false );
			define( 'SUNRISE', true );
			// further constants are defined in the Sunrise class
		} else {
			if ( ! defined( 'WP_HOME' ) ) {
				define( 'WP_HOME', $this->data['server_url'] );
			}
			define( 'WP_SITEURL', WP_HOME . '/' . WP_CORE_DIRNAME );
			define( 'WP_CONTENT_URL', WP_HOME . '/' . basename( WP_CONTENT_DIR ) );

			if ( 0 === strpos( WP_HOME, 'https://' ) ) {
				define( 'FORCE_SSL_ADMIN', true );
				define( 'FORCE_SSL_LOGIN', true );
			}
		}
	}

	protected function get_required_constants() {
		return array(
			'DB_NAME',
			'DB_USER',
			'DB_PASSWORD',
		);
	}

	protected function get_protected_constants() {
		$protected_constants = array(
			'WP_SITEURL',
			'WP_CONTENT_DIR',
			'WP_CONTENT_URL',
			'FORCE_SSL_ADMIN',
			'FORCE_SSL_LOGIN',
			'WP_DEBUG',
			'WP_DEBUG_LOG',
			'WP_DEBUG_DISPLAY',
			'SCRIPT_DEBUG',
			'SAVEQUERIES',
			'SUBDOMAIN_INSTALL',
			'ALLOW_SUBDIRECTORY_INSTALL',
			'DOMAIN_CURRENT_SITE',
			'PATH_CURRENT_SITE',
			'SITE_ID_CURRENT_SITE',
			'BLOG_ID_CURRENT_SITE',
			'COOKIEPATH',
			'SITECOOKIEPATH',
			'ADMIN_COOKIE_PATH',
			'COOKIE_DOMAIN',
			'SUNRISE',
		);

		// prevent the MULTISITE constant from being set if we're installing Multisite via CLI
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$wp_cli_args = \WP_CLI::get_runner()->arguments;
			if ( array( 'core', 'multisite-install' ) == array_slice( $wp_cli_args, 0, 2 ) && ! defined( 'WPINC' ) && isset( $GLOBALS['current_blog'] ) && isset( $GLOBALS['current_site'] ) ) {
				$protected_constants[] = 'MULTISITE';
			}
		}

		return $protected_constants;
	}

	protected function get_default_constants() {
		return array(
			// Database Settings
			'DB_NAME'						=> 'wp',
			'DB_USER'						=> 'root',
			'DB_PASSWORD'					=> '',
			'DB_HOST'						=> 'localhost',
			'DB_PREFIX'						=> 'wp_',
			'DB_CHARSET'					=> 'utf8',
			'DB_COLLATE'					=> '',
			// Salt Keys
			'AUTH_KEY'						=> '',
			'SECURE_AUTH_KEY'				=> '',
			'LOGGED_IN_KEY'					=> '',
			'NONCE_KEY'						=> '',
			'AUTH_SALT'						=> '',
			'SECURE_AUTH_SALT'				=> '',
			'LOGGED_IN_SALT'				=> '',
			'NONCE_SALT'					=> '',
			// Site Data
			'WPLANG'						=> '',
			'WP_HOME'						=> null,
			'WP_SITEURL'					=> null,
			'WP_CONTENT_DIR'				=> null,
			'WP_CONTENT_URL'				=> null,
			'FORCE_SSL_ADMIN'				=> null,
			'FORCE_SSL_LOGIN'				=> null,
			// Environment
			'WP_ENV'						=> $this->data['wp_env'],
			'WP_DEBUG'						=> 'production' !== $this->data['wp_env'],
			'WP_DEBUG_LOG'					=> 'production' !== $this->data['wp_env'],
			'WP_DEBUG_DISPLAY'				=> 'development' === $this->data['wp_env'],
			'SCRIPT_DEBUG'					=> 'development' === $this->data['wp_env'],
			'SAVEQUERIES'					=> 'development' === $this->data['wp_env'],
			// Custom Settings
			'DISALLOW_FILE_EDIT'			=> true,
			'DISALLOW_FILE_MODS'			=> true,
			'AUTOMATIC_UPDATER_DISABLED'	=> true,
			'AUTOSAVE_INTERVAL'				=> null,
			'COMPRESS_CSS'					=> null,
			'COMPRESS_SCRIPTS'				=> null,
			'CONCATENATE_SCRIPTS'			=> null,
			'CORE_UPGRADE_SKIP_NEW_BUNDLED'	=> null,
			'DISABLE_WP_CRON'				=> null,
			'EMPTY_TRASH_DAYS'				=> null,
			'ENFORCE_GZIP'					=> null,
			'IMAGE_EDIT_OVERWRITE'			=> null,
			'MEDIA_TRASH'					=> null,
			'WP_CACHE'						=> null,
			'WP_DEFAULT_THEME'				=> null,
			'WP_CRON_LOCK_TIMEOUT'			=> null,
			'WP_MAIL_INTERVAL'				=> null,
			'WP_POST_REVISIONS'				=> null,
			'WP_MAX_MEMORY_LIMIT'			=> null,
			'WP_MEMORY_LIMIT'				=> null,
			'ALLOW_UNFILTERED_UPLOADS'		=> null,
			// Multisite
			'WP_ALLOW_MULTISITE'			=> null,
			'MULTISITE'						=> null,
			'NOBLOGREDIRECT'				=> null,
			// Multisite Advanced
			'SUBDOMAIN_INSTALL'				=> null,
			'ALLOW_SUBDIRECTORY_INSTALL'	=> null,
			'DOMAIN_CURRENT_SITE'			=> null,
			'PATH_CURRENT_SITE'				=> null,
			'SITE_ID_CURRENT_SITE'			=> null,
			'BLOG_ID_CURRENT_SITE'			=> null,
			'COOKIEPATH'					=> null,
			'SITECOOKIEPATH'				=> null,
			'ADMIN_COOKIE_PATH'				=> null,
			'COOKIE_DOMAIN'					=> null,
			'SUNRISE'						=> null,
			// WordPress Bootstrap
			'ABSPATH'						=> $this->data['webroot_dir'] . '/' . WP_CORE_DIRNAME . '/'
		);
	}

	protected function get_constant_setting( $name ) {
		switch ( true ) {
			case array_key_exists( $name, $_ENV ):
				return $_ENV[ $name ];
			case array_key_exists( $name, $_SERVER ):
				return $_SERVER[ $name ];
			case getenv( $name ) !== false:
				return getenv( $name );
			case array_key_exists( $name, $this->data['composer'] ):
				return $this->data['composer'][ $name ];
			default:
				return null;
		}
	}

	protected function normalize_constants( $constants ) {
		$constants = $this->flatten( $constants );

		$keys = array_map( array( $this, 'normalize_constant' ), array_keys( $constants ) );
		$values = array_values( $constants );

		return array_combine( $keys, $values );
	}

	protected function normalize_constant( $constant ) {
		return strtoupper( str_replace( array( '-', ' ' ), '_', $constant ) );
	}

	protected function normalize_value( $value ) {
		if ( is_array( $value ) ) {
			$normalized = array();
			foreach ( $value as $k => $v ) {
				$normalized[ $k ] = $this->normalize_value( $v );
			}
			return $normalized;
		}

		switch ( $value ) {
			case 'TRUE':
			case 'true':
				return true;
			case 'FALSE':
			case 'false':
				return false;
			default:
				if ( is_numeric( $value ) ) {
					if ( intval( $value ) == floatval( $value ) ) {
						return intval( $value );
					}
					return floatval( $value );
				}
				return $value;
		}
	}

	protected function flatten( $arr, $prefix = '', $separator = '_' ) {
		$result = array();

		if ( is_object( $arr ) ) {
			$arr = get_object_vars( $arr );
		}

		foreach ( $arr as $key => $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				$result = $result + $this->flatten( $value, $prefix . $key . $separator, $separator );
			} else {
				$result[ $prefix . $key ] = $value;
			}
		}

		return $result;
	}

	protected function bail( $message = '', $title = '' ) {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', $this->data['webroot_dir'] . '/' . WP_CORE_DIRNAME . '/' );
		}

		if ( ! defined( 'WPINC' ) ) {
			define( 'WPINC', 'wp-includes' );
		}

		require_once ABSPATH . WPINC . '/load.php';
		require_once ABSPATH . WPINC . '/default-constants.php';
		require_once ABSPATH . WPINC . '/compat.php';
		require_once ABSPATH . WPINC . '/functions.php';
		require_once ABSPATH . WPINC . '/plugin.php';

		if ( ! function_exists( '__' ) ) {
			wp_load_translations_early();
		}

		wp_die( '<strong>Initialization failed:</strong> ' . $message, $title );
	}

	public static function get_current_domain() {
		if ( defined( 'WP_CLI' ) && WP_CLI && ! isset( $_SERVER['HTTP_HOST'] ) ) {
			return 'example.com';
		}

		$domain = strtolower( stripslashes( $_SERVER['HTTP_HOST'] ) );
		if ( ':80' === substr( $domain, -3 ) ) {
			$domain = substr( $domain, 0, -3 );
			$_SERVER['HTTP_HOST'] = substr( $_SERVER['HTTP_HOST'], 0, -3 );
		} elseif ( ':443' === substr( $domain, -4 ) ) {
			$domain = substr( $domain, 0, -4 );
			$_SERVER['HTTP_HOST'] = substr( $_SERVER['HTTP_HOST'], 0, -4 );
		}

		return $domain;
	}

	public static function get_current_path() {
		if ( defined( 'WP_CLI' ) && WP_CLI && ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return '/';
		}

		return stripslashes( $_SERVER['REQUEST_URI'] );
	}

	public static function get_current_protocol() {
		if ( function_exists( 'is_ssl' ) ) {
			if ( is_ssl() ) {
				return 'https';
			}
			return 'http';
		}

		if ( ( isset( $_SERVER['HTTPS'] ) && ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) || isset( $_SERVER['SERVER_PORT'] ) && $_SERVER['SERVER_PORT'] == '443' ) {
			return 'https';
		}
		return 'http';
	}

	public static function get_current_port() {
		if ( isset( $_SERVER['SERVER_PORT'] ) && ! in_array( intval( $_SERVER['SERVER_PORT'] ), array( 80, 443 ), true ) ) {
			return $_SERVER['SERVER_PORT'];
		}
		return '';
	}
}
