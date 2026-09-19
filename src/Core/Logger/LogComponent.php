<?php

declare (strict_types=1);
namespace JooosiIcon\Core\Logger;

defined('ABSPATH') || exit;
/**
 * Enum for logger component names
 * 
 * Provides type-safe component identifiers for logging
 * 
 * @since 1.0.0
 */
enum LogComponent : string
{
    case ICON_SERVICE = 'IconService';
    case ICONIFY_SERVICE = 'IconifyService';
    case LOCAL_ICON_SERVICE = 'LocalIconService';
    case ICON_SOURCE_SERVICE = 'IconSourceService';
    /** @deprecated Use ICON_SOURCE_SERVICE instead. */
    case BUNDLE_ICON_SERVICE = 'BundleIconService';
    case DISCOVERY = 'Discovery';
    case COMMAND_DISCOVERY = 'CommandDiscovery';
    case CONTAINER = 'Container';
    case ASSETS = 'Assets';
    case VITE = 'Vite';
}
