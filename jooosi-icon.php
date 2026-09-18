<?php

/**
 * @wordpress-plugin
 * Plugin Name:         Jooosi Icon
 * Plugin URI:          https://icon.jooo.si
 * Description:         A modern SVG icon library for WordPress with support for custom uploads and 200,000+ Iconify icons across block editor, page builders, and themes.
 * Text Domain:         jooosi-icon
 * Version:             1.0.19
 * Requires at least:   6.0
 * Requires PHP:        8.1
 * Author:              Jooosi
 * Author URI:          https://jooo.si
 * License:             GPL-2.0-or-later
 *
 * @package             JooosiIcon
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    if (file_exists(__DIR__ . '/vendor/scoper-autoload.php')) {
        require_once __DIR__ . '/vendor/scoper-autoload.php';
    } else {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    JooosiIcon\Plugin::get_instance()->boot();
}
