<?php

/**
 * WP Install Dependencies
 *
 * @package   WP_Install_Dependencies
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/wp-install-dependencies
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

/**
 * Instantiate class.
 */
add_action( 'plugins_loaded', function() {
	if ( file_exists( __DIR__ . '/wp-dependencies.json' ) ) {
		$config = file_get_contents( __DIR__ . '/wp-dependencies.json' );
	}
	new WP_Install_Dependencies( $config );
} );

if ( ! class_exists( 'WP_Install_Dependencies' ) ) {

	/**
	 * Class WP_Install_Dependencies
	 */
	class WP_Install_Dependencies {

		protected $config;

		/**
		 * Holds plugin dependency data from wp-dependencies.json
		 *
		 * @var
		 */
		protected $dependency;

		/**
		 * Holds names of installed dependencies for admin notices.
		 *
		 * @var
		 */
		protected static $notices;

		/**
		 * Holds data of uninstalled dependencies.
		 *
		 * @var
		 */
		protected $not_installed;

		/**
		 * WP_Install_Dependencies constructor.
		 *
		 * @param $config
		 */
		public function __construct( $config ) {
			/*
			 * Only run on plugin pages.
			 */
			global $pagenow;
			if ( false === strstr( $pagenow, 'plugin' ) ) {
				return false;
			}

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';

			$config = ! empty( $config ) ? json_decode( $config ) : null;
			/*
			 * Exit for malformed json files.
			 */
			if ( is_null( $config ) ) {
				return array( 'status' => 'error', 'message' => 'JSON file format error.' );
			}
			$this->config = $this->prepare_json( $config );

			add_action( 'admin_footer', array( $this, 'admin_footer' ) );
			add_action( 'wp_ajax_github_updater', array( $this, 'ajax_router' ) );

			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );
		}

		/**
		 * Determine if dependency is active or installed
		 */
		function admin_init() {
			if ( get_transient( 'github_updater_dismiss_notice' ) ) {
				return;
			}
			if ( $this->is_installed() ) {
				if ( ! is_plugin_active( $this->dependency->slug ) ) {
					$this->message = array(
						'action' => 'activate',
						'text'   => sprintf( __( 'Please activate the %s plugin.' ), $this->dependency->name )
					);
				}
			} else {
				$this->message = array(
					'action' => 'install',
					'text'   => sprintf( __( 'The %s plugin is required.' ), $this->dependency->name )
				);
			}
		}

		/**
		 * Register jQuery AJAX
		 */
		function admin_footer() {
			?>
			<script>
				(function ($) {
					$(function () {
						$(document).on('click', '.ghu-button', function () {
							var $this = $(this);
							$('.github-updater p').html('Running...');
							$.post(ajaxurl, {
								action: 'github_updater',
								method: $this.attr('data-action')
							}, function (response) {
								$('.github-updater p').html(response);
							});
						});
						$(document).on('click', '.github-updater .notice-dismiss', function () {
							$.post(ajaxurl, {
								action: 'github_updater',
								method: 'dismiss'
							});
						});
					});
				})(jQuery);
			</script>
			<?php
		}

		/**
		 * AJAX router
		 */
		function ajax_router() {
			$method    = isset( $_POST['method'] ) ? $_POST['method'] : '';
			$whitelist = array( 'install', 'activate', 'dismiss' );
			if ( in_array( $method, $whitelist ) ) {
				$response = $this->$method();
				echo $response['message'];
			}
			wp_die();
		}

		/**
		 * Prepare json data from wp-dependencies.json for use.
		 *
		 * @param $config
		 *
		 * @return bool
		 */
		protected function prepare_json( $config ) {
			$dependent_plugin = null;
			foreach ( $config as $dependency ) {
				if ( ! $dependency instanceof \stdClass ) {
					$dependent_plugin = $dependency;
					continue;
				}

				$download_link                = null;
				$dependency->dependent_plugin = $dependent_plugin;
				$path                         = parse_url( $dependency->uri, PHP_URL_PATH );
				$owner_repo                   = trim( $path, '/' );  // strip surrounding slashes
				$owner_repo                   = str_replace( '.git', '', $owner_repo ); //strip incorrect URI ending

				switch ( $dependency->git ) {
					case 'github':
						$download_link = 'https://api.github.com/repos/' . $owner_repo . '/zipball/' . $dependency->branch;
						if ( ! empty( $dependency->token ) ) {
							$download_link = add_query_arg( 'access_token', $dependency->token, $download_link );
						}
						$dependency->download_link = $download_link;
						break;
					case 'bitbucket':
						$download_link             = 'https://bitbucket.org/' . $owner_repo . '/get/' . $dependency->branch . '.zip';
						$dependency->download_link = $download_link;
						break;
					case 'gitlab':
						$download_link = 'https://gitlab.com/' . $owner_repo . '/repository/archive.zip';
						$download_link = add_query_arg( 'ref', $dependency->branch, $download_link );
						if ( ! empty( $dependency->token ) ) {
							$download_link = add_query_arg( 'private_token', $dependency->token, $download_link );
						}
						$dependency->download_link = $download_link;
						break;
				}

				$this->dependency            = $dependency;
				$this->dependency->installed = $this->is_installed();

				if ( ! $this->is_installed() ) {
					$this->not_installed[ dirname( $dependency->slug ) ]['name'] = $dependency->name;
					$this->not_installed[ dirname( $dependency->slug ) ]['link'] = $dependency->download_link;
				}

				$this->dependency->optional ? $this->optional_install() : $this->install();
			}

		}

		/**
		 * Is dependency installed?
		 */
		function is_installed() {
			$plugins = get_plugins();
			return isset( $plugins[ $this->dependency->slug ] );
		}

		/**
		 * Install and activate dependency.
		 */
		public function install() {
			if ( ! $this->is_installed() ) {
				add_filter( 'upgrader_source_selection', array( &$this, 'upgrader_source_selection' ), 10, 2 );

				$skin     = new WPID_Plugin_Installer_Skin( array(
					'type'  => 'plugin',
					'nonce' => wp_nonce_url( $this->dependency->download_link ),
				) );
				$upgrader = new Plugin_Upgrader( $skin );
				$result   = $upgrader->install( $this->dependency->download_link );

				if ( is_wp_error( $result ) ) {
					return array( 'status' => 'error', 'message' => $result->get_error_message() );
				}
				wp_cache_flush();
				$result = $this->activate();
				if ( 'error' == $result['status'] ) {
					return $result;
				}


				if ( ! $this->dependency->optional ) {
					activate_plugin( $this->dependency->slug, null, true );
				}

				if ( is_admin() && ! defined( 'DOING_AJAX' ) &&
				     $upgrader->skin->result
				) {
					self::$notices[] = $this->dependency->name;
					add_action( 'admin_notices', array( &$this, 'message' ) );
					add_action( 'network_admin_notices', array( &$this, 'message' ) );
				}

				unset( $this->not_installed[ dirname( $this->dependency->slug ) ] );
				$this->dependency->installed = true;

				return array( 'status' => 'ok', 'message' => sprintf( __( '%s has been installed and activated.' ), $this->dependency->name ) );
			}
		}

		/**
		 * Install but don't activate optional dependencies.
		 * Label dependent plugin.
		 */
		public function optional_install() {
			//$this->install();

			if ( ! is_multisite() || is_network_admin() && ! $this->is_installed() ) {
				add_action( 'after_plugin_row_' . $this->dependency->dependent_plugin, array( &$this, 'optional_install_plugin_row' ), 10, 0 );

			}
		}

		/**
		 * Add plugin theme row meta to plugin that has dependencies.
		 *
		 * @TODO Figure out how to install plugin from here.
		 *
		 * @return bool
		 */
		public function optional_install_plugin_row() {
			$wp_list_table = _get_list_table( 'WP_MS_Themes_List_Table' );

			foreach ( $this->not_installed as $not_installed ) {
				echo '<tr class="plugin-update-tr" data-slug="' . dirname( $this->dependency->dependent_plugin ) . '" data-plugin="' . $this->dependency->dependent_plugin . '"><td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange"><div class="update-message update-ok">';
				print( $not_installed['name'] . ' ' . esc_html__( 'is listed as an optional dependency.' ) . ' ' );
				print( '<a href="' . $not_installed['link'] . '">' . esc_html__( 'Download Now' ) . '</a><br>' );

				echo '</div></td></tr>';
			}
			print( '<script>jQuery(".active[data-plugin=\'' . $this->dependency->dependent_plugin . '\']").addClass("update");</script>' );
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
			$new_source = trailingslashit( $remote_source ) . dirname( $this->dependency->slug );
			$wp_filesystem->move( $source, $new_source );

			return trailingslashit( $new_source );
		}

		/**
		 * Show dependency installation message.
		 */
		public function message() {
			foreach ( self::$notices as $notice ) {
				?>
				<div class="updated notice is-dismissible">
					<p>
						<?php echo $notice;
						_e( ' has been installed and activated as a dependency.' ) ?>
					</p>
				</div>
				<?php
			}
		}

		/**
		 * Activate GHU
		 */
		public function activate() {
			$result = activate_plugin( $this->dependency->slug );
			if ( is_wp_error( $result ) ) {
				return array( 'status' => 'error', 'message' => $result->get_error_message() );
			}

			return array( 'status' => 'ok', 'message' => sprintf( __( '%s has been activated.' ), $this->dependency->name ) );
		}

		/**
		 * Dismiss admin notice for a week
		 */
		public function dismiss() {
			//set_transient( 'github_updater_dismiss_notice', 'yes', ( 60 * 60 * 24 * 7 ) );

			return array( 'status' => 'ok', 'message' => '' );
		}

		/**
		 * Display admin notices / action links
		 */
		public function admin_notices() {
			if ( ! empty( $this->message ) ) {
				$action = $this->message['action'];
				$notice = $this->message['text'];
				$notice .= ' <a href="javascript:;" class="ghu-button" data-action="' . $action . '">' . ucfirst( $action ) . ' Now &raquo;</a>';
				?>
				<div class="updated notice is-dismissible github-updater">
					<p><?php echo $notice; ?></p>
				</div>
				<?php
			}
		}
	}

	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	class WPID_Plugin_Installer_Skin extends Plugin_Installer_Skin {
		public function header() {}
		public function footer() {}
		public function error( $errors ) {}
		public function feedback( $string ) {}
	}

}
