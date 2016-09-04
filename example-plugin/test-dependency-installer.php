<?php

/**
 * Plugin Name: Test Dependency Installer
 * Plugin URI: https://github.com/afragen/wp-dependency-installer
 * Description: This plugin is used for test dependency installation of remote sourced plugins.
 * Version: 0.5
 * Author: Andy Fragen, Matt Gibbs
 * License: GNU General Public License v2
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Requires WP: 4.0
 * Requires PHP: 5.3
 */

include_once( __DIR__ . '/vendor/wp-dependency-installer/wp-dependency-installer.php' );

WP_Dependency_Installer::instance()->run();
