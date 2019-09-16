<?php

/**
 * Plugin Name: Test Dependency Installer
 * Plugin URI: https://github.com/afragen/wp-dependency-installer
 * Description: This plugin is used for test dependency installation of remote sourced plugins.
 * Version: 1.0
 * Author: Andy Fragen, Matt Gibbs
 * License: MIT
 * Requires WP: 5.1
 * Requires PHP: 5.6
 */

require_once __DIR__ . '/vendor/autoload.php';

WP_Dependency_Installer::instance()->run( __DIR__ );
add_filter(
	'wp_dependency_timeout',
	function( $timeout, $source ) {
		$timeout = $source !== basename( __DIR__ ) ? $timeout : 14;
		return $timeout;
	},
	10,
	2
);
