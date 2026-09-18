<?php
/**
 * @var array $propertiesData
 */

defined('ABSPATH') || exit;

use JooosiIcon\Plugin;
use JooosiIcon\Services\IconService;

$content = $propertiesData['content'] ?? [];

$icon = $content['icon'] ?? null;
$icon_name = $icon['name'] ?? '';

// Build attributes array
$attributes = ['name' => $icon_name];

$width = $icon['width'] ?? null;
if ($width !== null && isset($width['style'])) {
    $attributes['width'] = $width['style'];
}

$height = $icon['height'] ?? null;
if ($height !== null && isset($height['style'])) {
    $attributes['height'] = $height['style'];
}

$color = $icon['color'] ?? null;
if ($color !== null) {
    $attributes['color'] = $color;
}

// Get the IconService to fetch SVG for SSR
$container = Plugin::get_instance()->container();
$iconService = $container->get(IconService::class);
$svg = $iconService->get_icon($icon_name, $attributes);

// Build the Jooosi Icon element with SSR content.
$attr_string = '';
foreach ($attributes as $key => $value) {
    if ($value !== false && $value !== null && $value !== '') {
        $attr_string .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
    }
}

// Output the complete jooosi-icon element
if ($svg !== null) {
    /*
     * Security: SVG content is sanitized by IconService->get_icon() using enshrined/svg-sanitize library.
     * Attributes are escaped with esc_attr() above. The SVG content cannot be escaped with esc_html()
     * as it would break the SVG markup. We use render-time sanitization for defense-in-depth security.
     */
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG sanitized by enshrined/svg-sanitize, attributes escaped with esc_attr()
    echo sprintf('<jooosi-icon data-prerendered%s>%s</jooosi-icon>', $attr_string, $svg);
} else {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are escaped with esc_attr() above
    echo sprintf('<jooosi-icon%s></jooosi-icon>', $attr_string);
}
