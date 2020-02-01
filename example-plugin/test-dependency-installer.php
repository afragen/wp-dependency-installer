<?php

/**
 * Plugin Name: Test Dependency Installer
 * Plugin URI: https://github.com/afragen/wp-dependency-installer
 * Description: This plugin is used for test dependency installation of remote sourced plugins.
 * Version: 1.0
 * Author: Andy Fragen, Matt Gibbs
 * License: MIT
 * Text Domain: test-dependency-installer
 * Requires WP: 5.1
 * Requires PHP: 5.6
 */

require_once __DIR__ . '/vendor/autoload.php';

WP_Dependency_Installer::instance()->run( __DIR__ );

/**
 * Increase dismissable timeout from 7 to 14 days.
 */
add_filter(
	'wp_dependency_timeout',
	function( $timeout, $source ) {
		$timeout = basename( __DIR__ ) !== $source ? $timeout : 14;
		return $timeout;
	},
	10,
	2
);

/**
 * Change dismissable label from [Dependency] to [Test Dependency Installer].
 */
add_filter(
	'wp_dependency_dismiss_label',
	function( $label, $source ) {
		$label = basename( __DIR__ ) !== $source ? $label : __( 'Test Dependency Installer', 'test-dependency-installer' );
		return $label;
	},
	10,
	2
);
