<?php

/**
 * WP Dependency Installer
 *
 * A lightweight class to add to WordPress plugins or themes to automatically install
 * required plugin dependencies. Uses a JSON config file to declare plugin dependencies.
 * It can install a plugin from w.org, GitHub, Bitbucket, or GitLab.
 *
 * @package   WP_Dependency_Installer
 * @author    Andy Fragen
 * @author    Matt Gibbs
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/wp-dependency-installer
 * @version   0.5
 */

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*
 * Don't run during heartbeat.
 */
if ( isset( $_REQUEST['action'] ) && 'heartbeat' === $_REQUEST['action'] ) {
	return false;
}

if ( ! class_exists( 'WP_Dependency_Installer' ) ) {

	/**
	 * Class WP_Dependency_Installer
	 */
	class WP_Dependency_Installer {

		/**
		 * Holds the singleton instance
		 * @var
		 */
		private static $instance;

		/**
		 * Holds the JSON file contents.
		 * @var
		 */
		protected $config = array();

		/**
		 * Holds the current dependency's slug
		 * @var
		 */
		protected $current_slug;

		/**
		 * Holds names of installed dependencies for admin notices.
		 * @var
		 */
		protected $notices = array();

		/**
		 * WP_Dependency_Installer constructor.
		 *
		 * @param $config
		 */
		public function __construct() {
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'admin_footer', array( $this, 'admin_footer' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'wp_ajax_dependency_installer', array( $this, 'ajax_router' ) );
		}

		/**
		 * Singleton
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self;
			}
			return self::$instance;
		}

		/**
		 * Register dependencies (supports multiple instances)
		 */
		public function register( $config ) {
			if ( empty( $config ) ) {
				return;
			}

			if ( null === ( $config = json_decode( $config, true ) ) ) {
				return;
			}

			// Register dependency if new or required
			foreach ( $config as $dependency ) {
				$slug = $dependency['slug'];
				if ( ! isset( $this->config[ $slug ] ) || ! $dependency['optional'] ) {
					$this->config[ $slug ] = $dependency;
				}
			}
		}

		/**
		 * Process the registered dependencies
		 */
		public function apply_config() {
			foreach ( $this->config as $dependency ) {
				$uri = $dependency['uri'];
				$slug = $dependency['slug'];
				$path = parse_url( $uri, PHP_URL_PATH );
				$owner_repo = str_replace( '.git', '', trim( $path, '/' ) );

				if ( false !== strpos( $uri, 'github.com' ) ) {
					$download_link = 'https://api.github.com/repos/' . $owner_repo . '/zipball/' . $dependency['branch'];
					if ( ! empty( $dependency['token'] ) ) {
						$download_link = add_query_arg( 'access_token', $dependency['token'], $download_link );
					}
				}
				elseif ( false !== strpos( $uri, 'bitbucket.org' ) ) {
					$download_link = 'https://bitbucket.org/' . $owner_repo . '/get/' . $dependency['branch'] . '.zip';
				}
				elseif ( false !== strpos( $uri, 'gitlab.com' ) ) {
					$download_link = 'https://gitlab.com/' . $owner_repo . '/repository/archive.zip';
					$download_link = add_query_arg( 'ref', $dependency['branch'], $download_link );
					if ( ! empty( $dependency['token'] ) ) {
						$download_link = add_query_arg( 'private_token', $dependency['token'], $download_link );
					}
				}
				elseif ( false !== strpos( $uri, 'wordpress.org' ) ) {
					$download_link = 'https://downloads.wordpress.org/plugin/' . basename( $owner_repo ) . '.zip';
				}

				$this->config[ $slug ]['download_link'] = $download_link;

				// Install required dependencies
				if ( ! $dependency['optional'] ) {
					$this->notices[] = $this->install( $slug );
				}
			}
		}

		/**
		 * Determine if dependency is active or installed.
		 */
		public function admin_init() {

			// Get the gears turning
			$this->apply_config();

			// Generate admin notices
			foreach ( $this->config as $slug => $dependency ) {
				if ( is_plugin_active( $slug ) ) {
					continue;
				}

				if ( $this->is_installed( $slug ) ) {
					$this->notices[] = array(
						'action'	=> 'activate',
						'slug'		=> $slug,
						'text'		=> sprintf( __( 'Please activate the %s plugin.' ), $dependency['name'] )
					);
				}
				else {
					$this->notices[] = array(
						'action'	=> 'install',
						'slug'		=> $slug,
						'text'		=> sprintf( __( 'The %s plugin is required.' ), $dependency['name'] )
					);
				}
			}
		}

		/**
		 * Register jQuery AJAX.
		 */
		public function admin_footer() {
			?>
			<script>
				(function ($) {
					$(function () {
						$(document).on('click', '.wpdi-button', function () {
							var $this = $(this);
							var $parent = $(this).closest('p');
							$parent.html('Running...');
							$.post(ajaxurl, {
								action: 'dependency_installer',
								method: $this.attr('data-action'),
								slug: $this.attr('data-slug')
							}, function (response) {
								$parent.html(response);
							});
						});
						$(document).on('click', '.dependency-installer .notice-dismiss', function () {
							var $this = $(this);
							$.post(ajaxurl, {
								action: 'dependency_installer',
								method: 'dismiss',
								slug: $this.attr('data-slug')
							});
						});
					});
				})(jQuery);
			</script>
			<?php
		}

		/**
		 * AJAX router.
		 */
		public function ajax_router() {
			$method		= isset( $_POST['method'] ) ? $_POST['method'] : '';
			$slug 		= isset( $_POST['slug'] ) ? $_POST['slug'] : '';
			$whitelist	= array( 'install', 'activate', 'dismiss' );

			if ( in_array( $method, $whitelist ) ) {
				$response = $this->$method( $slug );
				echo $response['message'];
			}
			wp_die();
		}

		/**
		 * Is dependency installed?
		 */
		public function is_installed( $slug ) {
			$plugins = get_plugins();
			return isset( $plugins[ $slug ] );
		}

		/**
		 * Install and activate dependency.
		 */
		public function install( $slug ) {
			if ( $this->is_installed( $slug ) ) {
				return;
			}

			$this->current_slug = $slug;
			add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection' ), 10, 2 );

			$skin     = new WPDI_Plugin_Installer_Skin( array(
				'type'  => 'plugin',
				'nonce' => wp_nonce_url( $this->config[ $slug ]['download_link'] ),
			) );
			$upgrader = new Plugin_Upgrader( $skin );
			$result   = $upgrader->install( $this->config[ $slug ]['download_link'] );

			if ( is_wp_error( $result ) ) {
				return array( 'status' => 'error', 'message' => $result->get_error_message() );
			}

			wp_cache_flush();
			$result = $this->activate( $slug );
			if ( 'error' == $result['status'] ) {
				return $result;
			}

			return array(
				'status' => 'updated',
				'message' => sprintf( __( '%s has been installed and activated.' ), $this->config[ $slug ]['name'] )
			);
		}

		/**
		 * Activate dependency.
		 */
		public function activate( $slug ) {
			$result = activate_plugin( $slug ); // should this be network-wide?

			if ( is_wp_error( $result ) ) {
				return array( 'status' => 'error', 'message' => $result->get_error_message() );
			}

			return array( 'status' => 'updated', 'message' => sprintf( __( '%s has been activated.' ), $this->config[ $slug ]['name'] ) );
		}

		/**
		 * Dismiss admin notice for a week.
		 */
		public function dismiss() {
			return array( 'status' => 'updated', 'message' => '' );
		}

		/**
		 * Correctly rename dependency for activation.
		 *
		 * @param $source
		 * @param $remote_source
		 *
		 * @return string
		 */
		public function upgrader_source_selection( $source, $remote_source ) {
			global $wp_filesystem;
			$new_source = trailingslashit( $remote_source ) . dirname( $this->current_slug );
			$wp_filesystem->move( $source, $new_source );
			return trailingslashit( $new_source );
		}

		/**
		 * Display admin notices / action links.
		 */
		public function admin_notices() {
			foreach ( $this->notices as $notice ) {
				$status = empty( $notice['status'] ) ? 'updated' : $notice['status'];

				if ( ! empty( $notice['action'] ) ) {
					$action 	= esc_attr( $notice['action'] );
					$message 	= esc_html( $notice['text'] );
					$message 	.= ' <a href="javascript:;" class="wpdi-button" data-action="' . $action . '" data-slug="' . $notice['slug'] . '">' . ucfirst( $action ) . ' Now &raquo;</a>';
				}
				if ( ! empty( $notice['status'] ) ) {
					$message = esc_html( $notice['message'] );
				}
?>
				<div class="<?php echo $status ?> notice is-dismissible dependency-installer">
					<p><?php echo '<strong>[' . esc_html__( 'Dependency' ) . ']</strong> ' . $message; ?></p>
				</div>
<?php
			}
		}
	}

	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	class WPDI_Plugin_Installer_Skin extends Plugin_Installer_Skin {
		public function header() {}
		public function footer() {}
		public function error( $errors ) {}
		public function feedback( $string ) {}
	}

	function WPDI() {
		return WP_Dependency_Installer::instance();
	}

	WPDI();
}

/**
 * Register wp-dependencies.json
 */
if ( file_exists( __DIR__ . '/wp-dependencies.json' ) ) {
	$config = file_get_contents( __DIR__ . '/wp-dependencies.json' );
	WPDI()->register( $config );
}
