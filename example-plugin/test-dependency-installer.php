<?php

/**
 * Plugin Name: Test Dependency Installer
 * Plugin URI: https://github.com/afragen/wp-dependency-installer
 * Description: This plugin is used for test dependency installation of remote sourced plugins.
 * Version: 1.0
 * Author: Andy Fragen, Matt Gibbs
 * License: MIT
 * Requires WP: 4.0
 * Requires PHP: 5.3
 */

include_once __DIR__ . '/vendor/autoload.php';

WP_Dependency_Installer::instance()->run( __DIR__ );
