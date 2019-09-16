<?php

/**
 * WP Dependency Installer
 *
 * A lightweight class to add to WordPress plugins or themes to automatically install
 * required plugin dependencies. Uses a JSON config file to declare plugin dependencies.
 * It can install a plugin from w.org, GitHub, Bitbucket, GitLab, Gitea or direct URL.
 *
 * @package   WP_Dependency_Installer
 * @author    Andy Fragen, Matt Gibbs
 * @license   MIT
 * @link      https://github.com/afragen/wp-dependency-installer
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
		 * Holds the JSON file contents.
		 *
		 * @var array $config
		 */
		protected $config = array();

		/**
		 * Holds the current dependency's slug.
		 *
		 * @var string $current_slug
		 */
		protected $current_slug;

		/**
		 * Holds the calling plugin/theme slug.
		 *
		 * @var string $source
		 */
		protected $source;

		/**
		 * Holds names of installed dependencies for admin notices.
		 *
		 * @var array $notices
		 */
		protected $notices = array();

		/**
		 * Singleton.
		 */
		public static function instance() {
			static $instance = null;
			if ( null === $instance ) {
				$instance = new self();
			}

			return $instance;
		}

		/**
		 * Load hooks.
		 *
		 * @return void
		 */
		public function load_hooks() {
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'admin_footer', array( $this, 'admin_footer' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'wp_ajax_dependency_installer', array( $this, 'ajax_router' ) );

			// Initialize Persist admin Notices Dismissal dependency.
			add_action( 'admin_init', array( 'PAnD', 'init' ) );
		}

		/**
		 * Register wp-dependencies.json
		 *
		 * @param string $plugin_path Path to plugin or theme calling the framework.
		 */
		public function run( $plugin_path ) {
			if ( file_exists( $plugin_path . '/wp-dependencies.json' ) ) {
				$config = file_get_contents( $plugin_path . '/wp-dependencies.json' );
				if ( empty( $config ) ||
					null === ( $config = json_decode( $config, true ) )
				) {
					return;
				}
				$this->source = basename( $plugin_path );
				$this->load_hooks();
				$this->register( $config );
			}
		}

		/**
		 * Register dependencies (supports multiple instances).
		 *
		 * @param array $config JSON config as string.
		 */
		public function register( $config ) {
			foreach ( $config as $dependency ) {
				$dependency['source'] = $this->source;
				$slug                 = $dependency['slug'];
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
				$base          = null;
				$uri           = $dependency['uri'];
				$slug          = $dependency['slug'];
				$api           = parse_url( $uri, PHP_URL_HOST );
				$scheme        = parse_url( $uri, PHP_URL_SCHEME );
				$scheme        = ! empty( $scheme ) ? $scheme . '://' : 'https://';
				$path          = parse_url( $uri, PHP_URL_PATH );
				$owner_repo    = str_replace( '.git', '', trim( $path, '/' ) );

				switch ( $dependency['host'] ) {
					case 'github':
						$base          = null === $api || 'github.com' === $api ? 'api.github.com' : $api;
						$download_link = "{$scheme}{$base}/repos/{$owner_repo}/zipball/{$dependency['branch']}";
						if ( ! empty( $dependency['token'] ) ) {
							$download_link = add_query_arg( 'access_token', $dependency['token'], $download_link );
						}
						break;
					case 'bitbucket':
						$base          = null === $api || 'bitbucket.org' === $api ? 'bitbucket.org' : $api;
						$download_link = "{$scheme}{$base}/{$owner_repo}/get/{$dependency['branch']}.zip";
						break;
					case 'gitlab':
						$base          = null === $api || 'gitlab.com' === $api ? 'gitlab.com' : $api;
						$project_id    = rawurlencode( $owner_repo );
						$download_link = "{$scheme}{$base}/api/v4/projects/{$project_id}/repository/archive.zip";
						$download_link = add_query_arg( 'sha', $dependency['branch'], $download_link );
						if ( ! empty( $dependency['token'] ) ) {
							$download_link = add_query_arg( 'private_token', $dependency['token'], $download_link );
						}
						break;
					case 'gitea':
						$download_link = "{$scheme}{$api}/repos/{$owner_repo}/archive/{$dependency['branch']}.zip";
						if ( ! empty( $dependency['token'] ) ) {
							$download_link = add_query_arg( 'access_token', $dependency['token'], $download_link );
						}
						break;
					case 'wordpress':  // phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled
						$download_link = $this->get_dot_org_latest_download( basename( $owner_repo ) );
						break;
					case 'direct':
						$download_link = filter_var( $uri, FILTER_VALIDATE_URL );
						break;
				}

				/**
				 * Allow filtering of download link for dependency configuration.
				 *
				 * @since 1.4.11
				 *
				 * @param string $download_link Download link.
				 * @param array  $dependency    Dependency configuration.
				 */
				$download_link = apply_filters( 'wp_dependency_download_link', $download_link, $dependency );

				$this->config[ $slug ]['download_link'] = $download_link;
			}
		}

		/**
		 * Get lastest download link from WordPress API.
		 *
		 * @param string $slug Plugin slug.
		 * @return string $download_link
		 */
		private function get_dot_org_latest_download( $slug ) {
			$download_link = get_site_transient( 'wpdi-' . md5( $slug ) );

			if ( ! $download_link ) {
				$url           = 'https://api.wordpress.org/plugins/info/1.1/';
				$url           = add_query_arg(
					array(
						'action'                        => 'plugin_information',
						rawurlencode( 'request[slug]' ) => $slug,
					),
					$url
				);
				$response      = wp_remote_get( $url );
				$response      = json_decode( wp_remote_retrieve_body( $response ) );
				$download_link = empty( $response )
					? "https://downloads.wordpress.org/plugin/{$slug}.zip"
					: $response->download_link;

				set_site_transient( 'wpdi-' . md5( $slug ), $download_link, DAY_IN_SECONDS );
			}

			return $download_link;
		}

		/**
		 * Determine if dependency is active or installed.
		 */
		public function admin_init() {
			// Get the gears turning.
			$this->apply_config();

			// Generate admin notices.
			foreach ( $this->config as $slug => $dependency ) {
				$is_optional = ! ( isset( $dependency['optional'] ) && false === $dependency['optional'] );

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
							/* translators: %s: Plugin name */
							'text'   => sprintf( esc_html__( 'Please activate the %s plugin.' ), $dependency['name'] ),
							'source' => $dependency['source'],
						);

					} else {
						$this->notices[] = $this->activate( $slug );
					}
				} elseif ( $is_optional ) {
					$this->notices[] = array(
						'action' => 'install',
						'slug'   => $slug,
						/* translators: %s: Plugin name */
						'text'   => sprintf( esc_html__( 'The %s plugin is required.' ), $dependency['name'] ),
						'source' => $dependency['source'],
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

			if ( in_array( $method, $whitelist, true ) ) {
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

			$skin     = new WPDI_Plugin_Installer_Skin(
				array(
					'type'  => 'plugin',
					'nonce' => wp_nonce_url( $this->config[ $slug ]['download_link'] ),
				)
			);
			$upgrader = new Plugin_Upgrader( $skin );
			$result   = $upgrader->install( $this->config[ $slug ]['download_link'] );

			if ( is_wp_error( $result ) ) {
				return array(
					'status'  => 'error',
					'message' => $result->get_error_message(),
				);
			}

			if ( null === $result ) {
				return array(
					'status'  => 'error',
					'message' => esc_html__( 'Plugin download failed' ),
				);
			}

			wp_cache_flush();
			if ( ! $this->config[ $slug ]['optional'] ) {
				$this->activate( $slug );

				return array(
					'status'  => 'updated',
					'slug'    => $slug,
					/* translators: %s: Plugin name */
					'message' => sprintf( esc_html__( '%s has been installed and activated.' ), $this->config[ $slug ]['name'] ),
					'source'  => $this->config[ $slug ]['source'],
				);

			}
			if ( 'error' === $result['status'] ) {
				return $result;
			}

			return array(
				'status'  => 'updated',
				/* translators: %s: Plugin name */
				'message' => sprintf( esc_html__( '%s has been installed.' ), $this->config[ $slug ]['name'] ),
				'source'  => $this->config[ $slug ]['source'],
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
				return array(
					'status'  => 'error',
					'message' => $result->get_error_message(),
				);
			}

			return array(
				'status'  => 'updated',
				/* translators: %s: Plugin name */
				'message' => sprintf( esc_html__( '%s has been activated.' ), $this->config[ $slug ]['name'] ),
				'source'  => $this->config[ $slug ]['source'],
			);
		}

		/**
		 * Dismiss admin notice for a week.
		 *
		 * @return array Empty Message.
		 */
		public function dismiss() {
			return array(
				'status'  => 'updated',
				'message' => '',
			);
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
					$action   = esc_attr( $notice['action'] );
					$message  = esc_html( $notice['text'] );
					$message .= ' <a href="javascript:;" class="wpdi-button" data-action="' . $action . '" data-slug="' . $notice['slug'] . '">' . ucfirst( $action ) . ' Now &raquo;</a>';
				}
				if ( ! empty( $notice['status'] ) ) {
					$message = esc_html( $notice['message'] );
				}

				/**
				 * Filters the dismissal timeout.
				 *
				 * @since 1.4.1
				 *
				 * @param string|int '7'           Default dismissal in days.
				 * @param string $notice['source'] Plugin slug of calling plugin.
				 * @return string|int              Dismissal timeout in days.
				 */
				$timeout     = '-' . apply_filters( 'wp_dependency_timeout', '7', $notice['source'] );
				$dismissible = isset( $notice['slug'] )
					? 'dependency-installer-' . dirname( $notice['slug'] ) . $timeout
					: null;
				if ( class_exists( '\PAnd' ) && ! \PAnD::is_admin_notice_active( $dismissible ) ) {
					continue;
				}
				?>
				<div data-dismissible="<?php echo $dismissible; ?>" class="<?php echo $status; ?> notice is-dismissible dependency-installer">
					<p><?php echo '<strong>[' . esc_html__( 'Dependency' ) . ']</strong> ' . $message; ?></p>
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
			add_action(
				'after_plugin_row_' . $plugin_file,
				function( $plugin_file ) {
					print( '<script>jQuery(".inactive[data-plugin=\'' . $plugin_file . '\']").attr("class", "active");</script>' );
					print( '<script>jQuery(".active[data-plugin=\'' . $plugin_file . '\'] .check-column input").remove();</script>' );
				}
			);
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

			/* translators: %s: opening and closing span tags */
			$actions = array_merge( array( 'required-plugin' => sprintf( esc_html__( '%1$sPlugin dependency%2$s' ), '<span class="network_active">', '</span>' ) ), $actions );

			return $actions;
		}

		/**
		 * Get the configuration.
		 *
		 * @since 1.4.11
		 *
		 * @return array The configuration.
		 */
		public function get_config() {
			return $this->config;
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
		public function feedback( $string, ...$args ) {}
	}

}
