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

		/**
		 * Holds plugin dependency data from wp-dependencies.json
		 * @var
		 */
		protected static $dependency;

		/**
		 * Holds names of installed dependencies for admin notices.
		 * @var
		 */
		protected static $notices;

		/**
		 * WP_Install_Dependencies constructor.
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

			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';

			$config = ! empty( $config ) ? json_decode( $config ) : null;
			/*
			 * Exit for malformed json files.
			 */
			if ( empty( $config ) ) {
				return false;
			}
			$this->prepare_json( $config );
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
				if ( file_exists( WP_PLUGIN_DIR . '/' . $dependency->slug ) ) {
					if ( is_plugin_inactive( $dependency->slug ) && ! $dependency->optional ) {
						activate_plugin( $dependency->slug, null, true );
					}
				}
				$download_link = null;
				$dependency->dependent_plugin = $dependent_plugin;
				$path = parse_url( $dependency->uri, PHP_URL_PATH );
				$owner_repo = trim( $path, '/' );  // strip surrounding slashes
				$owner_repo = str_replace( '.git', '', $owner_repo ); //strip incorrect URI ending

				switch ( $dependency->git ) {
					case 'github':
						$download_link = 'https://api.github.com/repos/' . $owner_repo . '/zipball/' . $dependency->branch;
						if ( ! empty( $dependency->token ) ) {
							$download_link = add_query_arg( 'access_token', $dependency->token, $download_link );
						}
						$dependency->download_link = $download_link;
						break;
					case 'bitbucket':
						$download_link = 'https://bitbucket.org/' . $owner_repo . '/get/' . $dependency->branch . '.zip';
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

				self::$dependency = $dependency;
				self::$dependency->optional ? $this->optional_install() : $this->install();
				$this->dependency( $config );
			}
		}

		/**
		 * Install and activate dependency.
		 */
		public function install() {
			if ( ! file_exists( WP_PLUGIN_DIR . '/' . self::$dependency->slug ) ) {
				$type     = 'plugin';
				$nonce    = wp_nonce_url( self::$dependency->download_link );
				$upgrader = new \Plugin_Upgrader( $skin = new \Plugin_Installer_Skin( compact( 'type', 'title', 'url', 'nonce', 'plugin', 'api' ) ) );

				add_filter( 'install_plugin_complete_actions', array( &$this, 'install_plugin_complete_actions' ), 10, 0 );
				add_filter( 'upgrader_source_selection', array( &$this, 'upgrader_source_selection' ), 10, 2 );

				$upgrader->install( self::$dependency->download_link );
				wp_cache_flush();

				if ( ! self::$dependency->optional ) {
					activate_plugin( self::$dependency->slug, null, true );
				}

				if ( is_admin() && ! defined( 'DOING_AJAX' ) &&
				     $upgrader->skin->result
				) {
					self::$notices[] = self::$dependency->name;
					add_action( 'admin_notices', array( __CLASS__, 'message' ) );
					add_action( 'network_admin_notices', array( __CLASS__, 'message' ) );
				}
			}
		}

		/**
		 * Install but don't activate optional dependencies.
		 * Label dependent plugin.
		 */
		public function optional_install() {
			$this->install();

			if ( ! is_multisite() || is_network_admin() ) {
				add_action( 'after_plugin_row_' . self::$dependency->dependent_plugin, array( &$this, 'optional_install_plugin_row' ), 10, 0 );
				add_filter( 'network_admin_plugin_action_links_' . self::$dependency->dependent_plugin, function( $actions ){
					return array_merge( array( 'dependency' => esc_html__( 'Dependent Plugin' ) ), $actions );
				});
				add_filter( 'plugin_action_links_' . self::$dependency->dependent_plugin, function( $actions ){
					return array_merge( array( 'dependency' => esc_html__( 'Dependent Plugin' ) ), $actions );
				});
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
			if ( file_exists( WP_PLUGIN_DIR . '/' . self::$dependency->slug ) ) {
				return false;
			}
			$wp_list_table = _get_list_table( 'WP_MS_Themes_List_Table' );

			echo '<tr class="plugin-update-tr" data-slug="' . dirname( self::$dependency->dependent_plugin ) . '" data-plugin="' . self::$dependency->dependent_plugin . '"><td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange"><div class="update-message update-ok">';

			print( self::$dependency->name . ' ' . esc_html__( 'is listed as an optional dependency.' ) . ' ' );

			print( '<a href="#?install_dependency=true">' . esc_html__( 'Install Now' ) . '</a>' );

			echo '</div></td></tr>';

			print('<script>jQuery(".active[data-plugin=\'' . self::$dependency->dependent_plugin . '\']").addClass("update");</script>');
		}

		/**
		 * Remove links and checkbox from plugins page via hooks.
		 * @param $config
		 */
		public function dependency( $config ) {
			foreach ( $config as $dependency ) {
				if ( ! $dependency instanceof \stdClass ||
				     ! file_exists( WP_PLUGIN_DIR . '/' . $dependency->slug )
				) {
					continue;
				}
				if ( ! $dependency->optional ) {
					add_filter( 'network_admin_plugin_action_links_' . $dependency->slug, array( &$this, 'dependency_active' ) );
					add_filter( 'plugin_action_links_' . $dependency->slug, array( &$this, 'dependency_active' ) );
					add_action( 'after_plugin_row_' . $dependency->slug, array( &$this, 'hide_dependency_plugin_row_info' ), 10, 1 );
				}

				if ( $dependency->optional ) {
					add_filter( 'network_admin_plugin_action_links_' . $dependency->slug, array( &$this, 'dependency_optional' ) );
					add_filter( 'plugin_action_links_' . $dependency->slug, array( &$this, 'dependency_optional' ) );

				}
			}
		}

		/**
		 * Remove plugin install actions.
		 *
		 * @return array()
		 */
		public function install_plugin_complete_actions() {
			return array();
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
			$new_source = trailingslashit( $remote_source ) . dirname( self::$dependency->slug );
			$wp_filesystem->move( $source, $new_source );

			return trailingslashit( $new_source );
		}

		/**
		 * Remove specific row actions for dependencies.
		 * @param $actions
		 *
		 * @return array
		 */
		public function dependency_active( $actions ) {
			if ( isset( $actions['activate'] ) ) {
				unset( $actions['activate'] );
			}
			if ( isset( $actions['delete'] ) ) {
				unset( $actions['delete'] );
			}
			if ( isset( $actions['deactivate'] ) ) {
				unset( $actions['deactivate'] );
			}

			return array_merge( array( 'dependency' => esc_html__( 'Plugin Dependency' ) ), $actions );
		}

		/**
		 * Remove specific row actions for optional dependencies.
		 * @param $actions
		 *
		 * @return array
		 */
		public function dependency_optional( $actions ) {
			if ( isset( $actions['delete'] ) ) {
				unset( $actions['delete'] );
			}

			return array_merge( array( 'dependency' => esc_html__( 'Optional Plugin Dependency' ) ), $actions );
		}

		/**
		 * jQuery to hide checkbox in plugin row.
		 * @param $slug
		 */
		public function hide_dependency_plugin_row_info( $slug ) {
			print('<script>jQuery(".active[data-plugin=\'' . $slug . '\'] .check-column input").remove();</script>');
		}

		/**
		 * Show dependency installation message.
		 */
		public static function message() {
			foreach ( self::$notices as $notice ) {
				?>
				<div class="updated notice is-dismissible" style="margin-left:18%; float:left; width:75%;">
					<p>
						<?php echo $notice;  _e( ' has been installed and activated as a dependency.' ) ?>
					</p>
				</div>
				<?php
			}
		}

	}

}
