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
 * @license   MIT
 * @link      https://github.com/afragen/wp-dependency-installer
 * @version   1.3.0
 */

/**
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'WP_Dependency_Installer' ) ) {

	/**
	 * Class WP_Dependency_Installer
	 */
	class WP_Dependency_Installer {

		/**
		 * Holds the singleton instance.
		 *
		 * @var
		 */
		private static $instance;

		/**
		 * Holds the JSON file contents.
		 *
		 * @var
		 */
		protected $config = array();

		/**
		 * Holds the current dependency's slug.
		 *
		 * @var
		 */
		protected $current_slug;

		/**
		 * Holds names of installed dependencies for admin notices.
		 *
		 * @var
		 */
		protected $notices = array();

		/**
		 * WP_Dependency_Installer constructor.
		 */
		public function __construct() {
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'admin_footer', array( $this, 'admin_footer' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'wp_ajax_dependency_installer', array( $this, 'ajax_router' ) );
		}

		/**
		 * Singleton.
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * Register wp-dependencies.json
		 *
		 * @param string $plugin_path Path to plugin or theme calling the framework.
		 */
		public function run( $plugin_path ) {
			if ( file_exists( $plugin_path . '/wp-dependencies.json' ) ) {
				$config = file_get_contents( $plugin_path . '/wp-dependencies.json' );
				$this->register( $config );
			}
		}

		/**
		 * Register dependencies (supports multiple instances).
		 *
		 * @param string $config JSON config as string.
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
		 * Process the registered dependencies.
		 */
		public function apply_config() {
			foreach ( $this->config as $dependency ) {
				$download_link = null;
				$uri           = $dependency['uri'];
				$slug          = $dependency['slug'];
				$host          = explode( '.', parse_url( $uri, PHP_URL_HOST ) );
				$host          = isset( $dependency['host'] ) ? $dependency['host'] : $host[0];
				$path          = parse_url( $uri, PHP_URL_PATH );
				$owner_repo    = str_replace( '.git', '', trim( $path, '/' ) );

				switch ( $host ) {
					case ( 'github' ):
						$download_link = 'https://api.github.com/repos/' . $owner_repo . '/zipball/' . $dependency['branch'];
						if ( ! empty( $dependency['token'] ) ) {
							$download_link = add_query_arg( 'access_token', $dependency['token'], $download_link );
						}
						break;
					case ( 'bitbucket' ):
						$download_link = 'https://bitbucket.org/' . $owner_repo . '/get/' . $dependency['branch'] . '.zip';
						break;
					case ( 'gitlab' ):
						$download_link = 'https://gitlab.com/' . $owner_repo . '/repository/archive.zip';
						$download_link = add_query_arg( 'ref', $dependency['branch'], $download_link );
						if ( ! empty( $dependency['token'] ) ) {
							$download_link = add_query_arg( 'private_token', $dependency['token'], $download_link );
						}
						break;
					case( 'wordpress' ):
						$download_link = 'https://downloads.wordpress.org/plugin/' . basename( $owner_repo ) . '.zip';
						break;
				}

				$this->config[ $slug ]['download_link'] = $download_link;
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
				$is_optional = isset( $dependency['optional'] ) && false === $dependency['optional']
					? false
					: true;

				if ( ! $is_optional ) {
					$this->hide_plugin_action_links( $slug );
				}

				if ( is_plugin_active( $slug ) ) {
					continue;
				}

				if ( $this->is_installed( $slug ) ) {
					if ( $is_optional ) {
						$this->notices[] = array(
							'action' => 'activate',
							'slug'   => $slug,
							'text'   => sprintf( __( 'Please activate the %s plugin.' ), $dependency['name'] ),
						);

					} else {
						$this->notices[] = $this->activate( $slug );
					}

				} elseif ( $is_optional ) {
					$this->notices[] = array(
						'action' => 'install',
						'slug'   => $slug,
						'text'   => sprintf( __( 'The %s plugin is required.' ), $dependency['name'] ),
					);
				} else {
					$this->notices[] = $this->install( $slug );
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
								slug  : $this.attr('data-slug')
							}, function (response) {
								$parent.html(response);
							});
						});
						$(document).on('click', '.dependency-installer .notice-dismiss', function () {
							var $this = $(this);
							$.post(ajaxurl, {
								action: 'dependency_installer',
								method: 'dismiss',
								slug  : $this.attr('data-slug')
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
			$method    = isset( $_POST['method'] ) ? $_POST['method'] : '';
			$slug      = isset( $_POST['slug'] ) ? $_POST['slug'] : '';
			$whitelist = array( 'install', 'activate', 'dismiss' );

			if ( in_array( $method, $whitelist ) ) {
				$response = $this->$method( $slug );
				echo $response['message'];
			}
			wp_die();
		}

		/**
		 * Is dependency installed?
		 *
		 * @param string $slug Plugin slug.
		 *
		 * @return boolean
		 */
		public function is_installed( $slug ) {
			$plugins = get_plugins();

			return isset( $plugins[ $slug ] );
		}

		/**
		 * Install and activate dependency.
		 *
		 * @param string $slug Plugin slug.
		 *
		 * @return bool|array false or Message.
		 */
		public function install( $slug ) {
			if ( $this->is_installed( $slug ) || ! current_user_can( 'update_plugins' ) ) {
				return false;
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
			if ( ! $this->config[ $slug ]['optional'] ) {
				$this->activate( $slug );

				return array(
					'status'  => 'updated',
					'slug'    => $slug,
					'message' => sprintf( __( '%s has been installed and activated.' ), $this->config[ $slug ]['name'] ),
				);

			}
			if ( 'error' == $result['status'] ) {
				return $result;
			}

			return array(
				'status'  => 'updated',
				'message' => sprintf( __( '%s has been installed.' ), $this->config[ $slug ]['name'] ),
			);
		}

		/**
		 * Activate dependency.
		 *
		 * @param string $slug Plugin slug.
		 *
		 * @return array Message.
		 */
		public function activate( $slug ) {

			// network activate only if on network admin pages.
			$result = is_network_admin() ? activate_plugin( $slug, null, true ) : activate_plugin( $slug );

			if ( is_wp_error( $result ) ) {
				return array( 'status' => 'error', 'message' => $result->get_error_message() );
			}

			return array(
				'status'  => 'updated',
				'message' => sprintf( __( '%s has been activated.' ), $this->config[ $slug ]['name'] ),
			);
		}

		/**
		 * Dismiss admin notice for a week.
		 *
		 * @return array Empty Message.
		 */
		public function dismiss() {
			return array( 'status' => 'updated', 'message' => '' );
		}

		/**
		 * Correctly rename dependency for activation.
		 *
		 * @param string $source
		 * @param string $remote_source
		 *
		 * @return string $new_source
		 */
		public function upgrader_source_selection( $source, $remote_source ) {
			global $wp_filesystem;
			$new_source = trailingslashit( $remote_source ) . dirname( $this->current_slug );
			$wp_filesystem->move( $source, $new_source );

			return trailingslashit( $new_source );
		}

		/**
		 * Display admin notices / action links.
		 *
		 * @return bool/string false or Admin notice.
		 */
		public function admin_notices() {
			if ( ! current_user_can( 'update_plugins' ) ) {
				return false;
			}
			$message = null;
			foreach ( $this->notices as $notice ) {
				$status = empty( $notice['status'] ) ? 'updated' : $notice['status'];

				if ( ! empty( $notice['action'] ) ) {
					$action  = esc_attr( $notice['action'] );
					$message = esc_html( $notice['text'] );
					$message .= ' <a href="javascript:;" class="wpdi-button" data-action="' . $action . '" data-slug="' . $notice['slug'] . '">' . ucfirst( $action ) . ' Now &raquo;</a>';
				}
				if ( ! empty( $notice['status'] ) ) {
					$message = esc_html( $notice['message'] );
				}
				$dismissible = isset( $notice['slug'] )
					? 'dependency-installer-' . dirname( $notice['slug'] ) . '-7'
					: null;
				if ( class_exists( '\PAnd' ) && ! \PAnD::is_admin_notice_active( $dismissible ) ) {
					continue;
				}
				?>
				<div data-dismissible="<?php echo $dismissible ?>" class="<?php echo $status ?> notice is-dismissible dependency-installer">
					<p><?php echo '<strong>[' . __( 'Dependency' ) . ']</strong> ' . $message; ?></p>
				</div>
				<?php
			}
		}

		/**
		 * Hide links from plugin row.
		 *
		 * @param $plugin_file
		 */
		public function hide_plugin_action_links( $plugin_file ) {
			add_filter( 'network_admin_plugin_action_links_' . $plugin_file, array( $this, 'unset_action_links' ) );
			add_filter( 'plugin_action_links_' . $plugin_file, array( $this, 'unset_action_links' ) );
			add_action( 'after_plugin_row_' . $plugin_file,
				function( $plugin_file ) {
					print( '<script>jQuery(".inactive[data-plugin=\'' . $plugin_file . '\']").attr("class", "active");</script>' );
					print( '<script>jQuery(".active[data-plugin=\'' . $plugin_file . '\'] .check-column input").remove();</script>' );
				} );
		}

		/**
		 * Unset plugin action links so mandatory plugins can't be modified.
		 *
		 * @param $actions
		 *
		 * @return mixed
		 */
		public function unset_action_links( $actions ) {
			if ( isset( $actions['edit'] ) ) {
				unset( $actions['edit'] );
			}
			if ( isset( $actions['delete'] ) ) {
				unset( $actions['delete'] );
			}
			if ( isset( $actions['deactivate'] ) ) {
				unset( $actions['deactivate'] );
			}

			return array_merge( array( 'required-plugin' => __( 'Plugin dependency' ) ), $actions );
		}

	}

	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	/**
	 * Class WPDI_Plugin_Installer_Skin
	 */
	class WPDI_Plugin_Installer_Skin extends Plugin_Installer_Skin {
		public function header() {}
		public function footer() {}
		public function error( $errors ) {}
		public function feedback( $string ) {}
	}

}
