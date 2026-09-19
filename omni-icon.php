<?php

/**
 * Compatibility loader for installations activated through the former plugin file.
 *
 * @todo Remove this compatibility loader completely in Jooosi Icon 2.0.0.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$legacyPlugin = plugin_basename(__FILE__);
$plugin = plugin_basename(__DIR__ . '/jooosi-icon.php');

$activePlugins = get_option('active_plugins', []);
if (is_array($activePlugins)) {
    $legacyPosition = array_search($legacyPlugin, $activePlugins, true);

    if ($legacyPosition !== false) {
        if (! in_array($plugin, $activePlugins, true)) {
            $activePlugins[$legacyPosition] = $plugin;
        } else {
            unset($activePlugins[$legacyPosition]);
        }

        update_option('active_plugins', array_values($activePlugins));
    }
}

if (is_multisite()) {
    $networkPlugins = get_site_option('active_sitewide_plugins', []);

    if (is_array($networkPlugins) && isset($networkPlugins[$legacyPlugin])) {
        if (! isset($networkPlugins[$plugin])) {
            $networkPlugins[$plugin] = $networkPlugins[$legacyPlugin];
        }

        unset($networkPlugins[$legacyPlugin]);
        update_site_option('active_sitewide_plugins', $networkPlugins);
    }
}

unset($activePlugins, $legacyPlugin, $legacyPosition, $networkPlugins, $plugin);

require_once __DIR__ . '/jooosi-icon.php';
