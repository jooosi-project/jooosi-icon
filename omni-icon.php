<?php

/**
 * @wordpress-plugin
 * Plugin Name:         Omni Icon
 * Plugin URI:          https://github.com/nabasa-dev/omni-icon
 * Description:         A modern SVG icon library for WordPress with support for custom uploads and 200,000+ Iconify icons across block editor, page builders, and themes.
 * Text Domain:         omni-icon
 * Version:             1.0.19
 * Requires at least:   6.0
 * Requires PHP:        8.1
 * Author:              Omni Icon
 * Author URI:          https://github.com/nabasa-dev
 * License:             GPL-2.0-or-later
 *
 * @package             OmniIcon
 * @author              Joshua Gugun Siagian <suabahasa@gmail.com>
 */

declare(strict_types=1);

use OmniIcon\Plugin;

defined('ABSPATH') || exit;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    if (file_exists(__DIR__ . '/vendor/scoper-autoload.php')) {
        require_once __DIR__ . '/vendor/scoper-autoload.php';
    } else {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    Plugin::get_instance()->boot();
}
