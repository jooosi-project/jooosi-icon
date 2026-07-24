<?php

declare (strict_types=1);
namespace OmniIcon\Integration\Bricks\Elements;

use Bricks\Element;
use OMNI_ICON;
use OmniIcon\Plugin;
use OmniIcon\Services\IconService;
use function bricks_render_dynamic_data;
/**
 * Omni Icon element for Bricks Builder
 *
 * Has feature parity with the Gutenberg Icon Block, supporting:
 * - Icon selection via prefix:name format (e.g., mdi:home, fa:github, lucide:star) and Icon Picker Modal / UI
 * - Custom width and height dimensions
 * - Custom color styling
 *
 * The element extends Bricks' base Element class and renders icons using the
 * omni-icon web component.
 *
 * @see https://academy.bricksbuilder.io/article/create-your-own-elements/
 */
class IconElement extends Element
{
    /**
     * Element properties
     */
    public $category = 'general';
    public $name = 'omni-icon';
    public $icon = 'ti-star';
    public $scripts = ['omniIcon'];
    /**
     * Return localized element label
     */
    public function get_label()
    {
        return esc_html__('Omni Icon', 'omni-icon');
    }
    /**
     * Return element keywords for search
     */
    public function get_keywords()
    {
        return ['icon', 'iconify', 'svg', 'omni', 'symbol'];
    }
    /**
     * Set builder controls
     */
    public function set_controls()
    {
        // Icon name control
        $this->controls['iconName'] = [
            'tab' => 'content',
            'label' => esc_html__('Icon Name', 'omni-icon'),
            'type' => 'text',
            // 'inline' => true,
            'placeholder' => 'mdi:home',
            'description' => esc_html__('Format: prefix:name (e.g., mdi:home, fa:github, lucide:star)', 'omni-icon'),
        ];
        // Browse icons button
        $this->controls['_iconPickerButton'] = ['tab' => 'content', 'type' => 'info', 'content' => sprintf('<button type="button" class="oibb-icon-picker-button" onclick="if (window.omniIconPicker) { window.omniIconPicker.open(); }">%s</button>', esc_html__('Browse Icons', 'omni-icon'))];
        // Icon color control
        $this->controls['iconColor'] = ['tab' => 'content', 'label' => esc_html__('Color', 'omni-icon'), 'type' => 'color', 'inline' => \true, 'default' => 'currentColor'];
        // Width control
        $this->controls['iconWidth'] = ['tab' => 'content', 'label' => esc_html__('Width', 'omni-icon'), 'type' => 'number', 'units' => \true, 'min' => 16, 'max' => 256, 'placeholder' => 'auto'];
        // Height control
        $this->controls['iconHeight'] = ['tab' => 'content', 'label' => esc_html__('Height', 'omni-icon'), 'type' => 'number', 'units' => \true, 'min' => 16, 'max' => 256, 'placeholder' => 'auto'];
    }
    /**
     * Render element HTML on the frontend
     */
    public function render()
    {
        $settings = $this->settings;
        $icon_name = bricks_render_dynamic_data((string) ($settings['iconName'] ?? ''), $this->post_id);
        // Show placeholder if no icon name is set
        if (empty($icon_name)) {
            return $this->render_element_placeholder(['title' => esc_html__('No icon selected.', 'omni-icon'), 'description' => esc_html__('Enter an icon name in the format: prefix:name', 'omni-icon')]);
        }
        // Get icon attributes
        $attributes = [];
        $attributes['name'] = $icon_name;
        // Handle dimensions - normalize like Gutenberg block
        $width = $settings['iconWidth'] ?? '';
        $height = $settings['iconHeight'] ?? '';
        // If one dimension is set and the other isn't, use the set one for both
        if (!empty($width) && empty($height)) {
            $height = $width;
        } elseif (!empty($height) && empty($width)) {
            $width = $height;
        }
        if (!empty($width)) {
            $attributes['width'] = $width;
        }
        if (!empty($height)) {
            $attributes['height'] = $height;
        }
        // Add color if not default
        $color = $settings['iconColor'] ?? 'currentColor';
        if ($color !== 'currentColor') {
            /**
             * @source Bricks\Assets::generate_css_color()
             */
            if (is_string($color)) {
                $attributes['color'] = $color;
            } else if (is_array($color)) {
                // Plain color value (@since 1.5 for CSS vars, dynamic data color)
                if (!empty($color['raw'])) {
                    $attributes['color'] = bricks_render_dynamic_data($color['raw'], $this->post_id);
                }
                if (!empty($color['rgb'])) {
                    $attributes['color'] = $color['rgb'];
                }
                if (!empty($color['hex'])) {
                    $attributes['color'] = $color['hex'];
                }
            }
        }
        // Get the IconService to fetch SVG for SSR
        $container = Plugin::get_instance()->container();
        $iconService = $container->get(IconService::class);
        $svg = $iconService->get_icon($icon_name, $attributes);
        foreach ($attributes as $key => $value) {
            if ($value !== \false && $value !== null) {
                $this->set_attribute('_root', $key, $value);
            }
        }
        // Render omni-icon with SSR support
        if ($svg !== null) {
            /*
             * Security: SVG content is sanitized by IconService->get_icon() using enshrined/svg-sanitize library.
             * Attributes from render_attributes() are escaped by Bricks. The SVG content cannot be escaped with
             * esc_html() as it would break the SVG markup. We use render-time sanitization for defense-in-depth security.
             */
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG sanitized by enshrined/svg-sanitize, attributes escaped by Bricks
            echo sprintf('<omni-icon data-prerendered %s>%s</omni-icon>', $this->render_attributes('_root'), $svg);
        } else {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are escaped by Bricks render_attributes()
            echo sprintf('<omni-icon %s></omni-icon>', $this->render_attributes('_root'));
        }
    }
}
