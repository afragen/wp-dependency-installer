# WP Dependency Installer
* Contributors: [Andy Fragen](https://github.com/afragen), [Matt Gibbs](https://github.com/mgibbs189), [contributors](https://github.com/afragen/wp-dependency-installer/graphs/contributors)
* Tags: plugin, dependency, install
* Requires at least: 3.8
* Requires PHP: 5.3
* Tested up to: 4.5
* Stable tag: master
* Donate link: http://thefragens.com/wp-dependency-installer-donate
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

A lightweight class to add to WordPress plugins or themes to automatically install required plugin dependencies. Uses a JSON config file to declare plugin dependencies.

## Description

This is a drop in class for developers to optionally or automatically install plugin dependencies for their own plugins or themes. It can install a plugin from w.org, GitHub, Bitbucket, or GitLab. You must include a JSON config file in the same directory as this class file.

This contains an example plugin and an example JSON configuration file. Only required dependencies are installed automatically, optional dependencies are not.

## Installation

Copy the `wp-install-dependencies` folder into your project and copy or adapt the `wp-dependencies-example.json` file as `wp-dependencies.json` to your needs. Best practices may be to add this directory into your `vendor` directory.

Add the following line to your plugin or theme's `functions.php` file. Make sure to adjust for where in your project you install the `wp-install-dependencies` folder.

```php
include_once( __DIR__ . '/vendor/wp-install-dependencies/wp-dependency-installer.php' );
```

## JSON config file format

This file must be named `wp-dependencies.json` and it must be in the same directory as `wp-dependency-installer.php`.

```json
[
  {
    "name": "WordPress REST API",
    "host": "wordpress",
    "slug": "rest-api/plugin.php",
    "uri": "https://wordpress.org/plugins/rest-api/",
    "branch": "trunk",
    "optional": true
  },
  {
    "name": "GitHub Updater",
    "host": "github",
    "slug": "github-updater/github-updater.php",
    "uri": "afragen/github-updater",
    "branch": "master",
    "optional": true,
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

## Development

PRs are welcome against the `develop` branch.
