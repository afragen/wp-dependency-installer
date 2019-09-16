# WP Dependency Installer
* Contributors: [Andy Fragen](https://github.com/afragen), [Matt Gibbs](https://github.com/mgibbs189), [contributors](https://github.com/afragen/wp-dependency-installer/graphs/contributors)
* Tags: plugin, dependency, install
* Requires at least: 5.1
* Requires PHP: 5.6
* Stable tag: master
* Donate link: <https://thefragens.com/wp-dependency-installer-donate>
* License: MIT

A lightweight class to add to WordPress plugins or themes to automatically install required plugin dependencies. Uses a JSON config file to declare plugin dependencies.

## Description

This is a drop in class for developers to optionally or automatically install plugin dependencies for their own plugins or themes. It can install a plugin from w.org, GitHub, Bitbucket, GitLab, Gitea, or a direct URL. You must include a JSON config file in the root directory of the plugin/theme file.

This contains an example plugin and an example JSON configuration file. Only required dependencies are installed automatically, optional dependencies are not. Required dependencies are always kept active.

## Installation

WP Dependency Installer v2.0.0 or greater now requires PHP 5.6 or greater and WordPress 5.1 or greater.

Install the package via composer.

Run the composer command: ```composer require afragen/wp-dependency-installer```

Then create a new `wp-dependencies.json` file.

```cp ./vendor/afragen/wp-dependency-installer/wp-dependencies-example.json wp-dependencies.json```

You will then need to update `wp-dependencies.json` to suit your requirements.

Add the following lines to your plugin or to your theme's `functions.php` file.

```php
include_once( __DIR__ . '/vendor/autoload.php' );
WP_Dependency_Installer::instance()->run( __DIR__ );
```

## JSON config file format

This file must be named `wp-dependencies.json` and it must be in the root directory of your plugin or theme.

```json
[
  {
    "name": "Query Monitor",
    "host": "wordpress",
    "slug": "query-monitor/query-monitor.php",
    "uri": "https://wordpress.org/plugins/query-monitor/",
    "optional": false
  },
  {
    "name": "GitHub Updater",
    "host": "github",
    "slug": "github-updater/github-updater.php",
    "uri": "afragen/github-updater",
    "branch": "master",
    "optional": false,
    "token": null
  },
  {
    "name": "Test Plugin Notags",
    "host": "bitbucket",
    "slug": "test-plugin-notags/test-plugin-notags.php",
    "uri": "https://bitbucket.org/afragen/test-plugin-notags",
    "branch": "master",
    "optional": true
  },
  {
    "name": "Test Gitlab Plugin2",
    "host": "gitlab",
    "slug": "test-gitlab-plugin2/test-gitlab-plugin2.php",
    "uri": "https://gitlab.com/afragen/test-gitlab-plugin2",
    "branch": "develop",
    "optional": true,
    "token": null
  },
  {
    "name": "Test Direct Plugin Download",
    "host": "direct",
    "slug": "test-direct-plugin/test-plugin.php",
    "uri": "https://direct-download.com/path/to.zip",
    "optional": true
  }
]
```

An example file is included, `wp-dependencies-example.json`. You may use a shorthand uri such as `<owner>/<repo>` in the JSON.

If you want to programmatically add dependencies you can send an associative array directly to

```php
WP_Dependency_Installer::instance()->register( $config )
```

where `$config` is an associative array as in identical format as `json_decode( wp-dependencies.json content )`

The default timeout for dismissal of a notification is 7 days. There is a filter `wp_dependency_timeout` to adjust this on a per project basis.

```php
add_filter(
  'wp_dependency_timeout', function( $timeout, $source ) {
    $timeout = $source !== basename( __DIR__ ) ? $timeout : 14;
    return $timeout;
  }, 10, 2
);
```

The download link can be filtered using the filter hook `wp_dependency_download_link`. The `$download_link` and the `$dependency` are passed as parameters.

## Development

PRs are welcome against the `develop` branch.
