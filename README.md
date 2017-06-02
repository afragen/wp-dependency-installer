# WP Dependency Installer
* Contributors: [Andy Fragen](https://github.com/afragen), [Matt Gibbs](https://github.com/mgibbs189), [contributors](https://github.com/afragen/wp-dependency-installer/graphs/contributors)
* Tags: plugin, dependency, install
* Requires at least: 3.8
* Requires PHP: 5.3
* Tested up to: 4.8
* Stable tag: master
* Donate link: http://thefragens.com/wp-dependency-installer-donate
* License: MIT

A lightweight class to add to WordPress plugins or themes to automatically install required plugin dependencies. Uses a JSON config file to declare plugin dependencies.

## Description

This is a drop in class for developers to optionally or automatically install plugin dependencies for their own plugins or themes. It can install a plugin from w.org, GitHub, Bitbucket, or GitLab. You must include a JSON config file in the same directory as this class file.

This contains an example plugin and an example JSON configuration file. Only required dependencies are installed automatically, optional dependencies are not. Required dependencies are always kept active.

## Installation

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
    "branch": "trunk",
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
    "slug": "test-plugin-notags/test-plugin-notags.php",
    "uri": "https://bitbucket.org/afragen/test-plugin-notags",
    "branch": "master",
    "optional": true,
    "token": null
  },
  {
    "name": "Test Gitlab Plugin2",
    "host": "gitlab",
    "slug": "test-gitlab-plugin2/test-gitlab-plugin2.php",
    "uri": "https://gitlab.com/afragen/test-gitlab-plugin2",
    "branch": "develop",
    "optional": true,
    "token": null
  }
]
```

An example file is included, `wp-dependencies-example.json`. You may use a shorthand uri such as `<owner>/<repo>` but only if you include the `host` element in the JSON. If you have a full URI in the `uri` element then the `host` element is optional.

If you want to programmatically add dependencies you can send an associative array directly to 
```php
WP_Dependency_Installer::instance()->register( $config )
```
where `$config` is an associative array as in identical format as `json_decode( wp-dependencies.json content )`

## Development

PRs are welcome against the `develop` branch.
