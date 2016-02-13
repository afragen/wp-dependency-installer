# WP Dependency Installer
* Contributors: [Andy Fragen](https://github.com/afragen), [Matt Gibbs](https://github.com/mgibbs189), [contributors](https://github.com/afragen/wp-dependency-installer/graphs/contributors)
* Tags: plugin, dependency, install
* Requires at least: 3.8
* Requires PHP: 5.3
* Tested up to: 4.4
* Stable tag: master
* Donate link: 
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

A lightweight class to add to WordPress plugins or themes to automatically install required plugin dependencies. Uses a JSON config file to declare plugin dependencies.

## Description

This is a drop in class for developers to optionally or automatically install plugin dependencies for their own plugins or themes. It can install a plugin from w.org, GitHub, Bitbucket, or GitLab.

This contains an example plugin. Only required dependencies are installed automatically, optional dependencies are not.

## Installation

Copy the `dependency-installer` folder into your project and adapt the `wp-dependencies.json` file to your needs.

Add the following line to your plugin or theme's `functions.php` file. Make sure to adjust for where in your project you install the `dependency-installer` folder.

```php
include_once( __DIR__ . '/dependency-installer/wp-dependency-installer.php' );
```

## JSON config file format

This file must be named `wp-dependencies.json`.

```json
[
  {
    "name": "Hello Dolly",
    "host": "wordpress,
    "slug": "hello-dolly/hello.php",
    "uri": "https://wordpress.org/plugins/hello-dolly",
    "branch": "trunk",
    "optional": true,
    "token": null
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
    "host": "bitbucket",
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
An example file is included, `wp-dependencies-example.json`. You may use a shorthand uri such as `<owner>/<repo>`.

## Development

PRs are welcome against the `develop` branch.
