# WP Install Dependencies
* Contributors: [Andy Fragen](https://github.com/afragen), [Matt Gibbs](https://github.com/mgibbs189), [contributors](https://github.com/afragen/github-updater/graphs/contributors)
* Tags: plugin, theme, dependency, install
* Requires at least: 3.8
* Requires PHP: 5.3
* Tested up to: 4.4
* Stable tag: master
* Donate link: 
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

A lightweight class to add to WordPress plugins or themes to automatically install required plugin dependencies. Uses a JSON config file to declare plugin dependencies.

## Description

This contains an example plugin. **This is still very much in development, but it works, sort of.** All required dependencies are installed, optional dependencies are not.

Working on javascript method of installing and activating like [Install GitHub Updater](https://github.com/mgibbs189/install-github-updater)

## Installation

Copy the `install-dependencies` folder into your project and adapt the `wp-dependencies.json` file to your needs.

Add the following line to your plugin or theme's `functions.php` file. Make sure to adjust for where in your project you install the `install-dependencies` folder.

```php
include_once( __DIR__ . '/install-dependencies/wp-install-dependencies.php' );
```

## JSON config file format

This file must be named `wp-dependencies.json`.

```json
{
  "type": "plugin",
  "github-updater": {
    "name": "GitHub Updater",
    "slug": "github-updater/github-updater.php",
    "git": "github",
    "uri": "https://github.com/afragen/github-updater",
    "branch": "master",
    "optional": false,
    "token": null
  },
  "test": {
    "name": "Test Plugin",
    "slug": "test-plugin/test-plugin.php",
    "git": "github",
    "uri": "https://github.com/afragen/test-plugin",
    "branch": "master",
    "optional": true,
    "token": null
  }
}
```
The `"type"` element is either **plugin** or **theme** depending upon whether the project using the class with is a plugin or a theme. An example file is included, `wp-dependencies-example.json`.
