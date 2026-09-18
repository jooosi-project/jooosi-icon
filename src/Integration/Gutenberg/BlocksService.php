<?php

declare(strict_types=1);

namespace JooosiIcon\Integration\Gutenberg;

defined('ABSPATH') || exit;

use JOOOSI_ICON;
use JooosiIcon\Core\Discovery\Attributes\Hook;
use JooosiIcon\Core\Discovery\Attributes\Service;
use JooosiIcon\Services\IconService;
use JooosiIcon\Services\ViteService;

/**
 * Service for registering and managing Gutenberg blocks
 */
#[Service]
class BlocksService
{
    public function __construct(
        private IconService $iconService,
        private ViteService $viteService,
    ) {}

    /**
     * Register the Gutenberg blocks
     */
    #[Hook('init', priority: 10)]
    public function register_blocks(): void
    {
        $path = $this->viteService->is_development()
            ? $this->viteService->generate_development_asset_path('resources/integration/gutenberg/blocks/icon-block/block.json')
            : $this->viteService->get_manifest_dir() . '/integration/gutenberg/blocks/icon-block/block.json';

        register_block_type(
            $path,
            [
                'render_callback' => $this->render_icon_block(...),
            ],
        );
    }

    /**
     * Enqueue block editor assets
     */
    #[Hook('enqueue_block_editor_assets', priority: 10)]
    public function enqueue_block_editor_assets(): void
    {
        $screen = get_current_screen();
        if (is_admin() && $screen->is_block_editor()) {
            add_action('admin_head', fn() => $this->admin_head(), 10);
            
            // Enqueue webcomponent for block editor
            $this->enqueue_webcomponent_for_editor();

            // Enqueue iframe asset for block editor
            $this->enqueue_iframe_asset_for_editor();
        }
    }
    
    /**
     * Enqueue webcomponent scripts for the block editor
     */
    private function enqueue_webcomponent_for_editor(): void
    {
        // Enqueue the Jooosi Icon web component (including the legacy alias).
        $this->viteService->enqueue_asset(
            'resources/webcomponents/jooosi-icon.ts',
            [
                'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':web-component:jooosi-icon',
                'dependencies' => [
                    // JOOOSI_ICON::TEXT_DOMAIN . ':web-component-module:error-handler-editor',
                ],
                'in_footer' => true,
            ]
        );
    }

    /**
     * Enqueue iframe asset for the block editor
     */
    private function enqueue_iframe_asset_for_editor(): void
    {
        $this->viteService->enqueue_asset('resources/integration/gutenberg/blocks/icon-block/iframe.ts', [
            'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':gutenberg-icon-block:iframe'
        ]);
    }

    /**
     * Enqueue assets in admin head for block editor
     */
    public function admin_head(): void
    {
        $this->viteService->enqueue_asset('resources/integration/gutenberg/blocks/icon-block/index.jsx', [
            'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':gutenberg-icon-block',
            'dependencies' => [
                'wp-blocks',
                'wp-element',
                'wp-editor',
                'wp-components',
                'wp-block-editor',
                'wp-hooks',
                'wp-i18n',
                'wp-plugins',
                'wp-data',
                'react',
                'react-dom',
            ],
            'in_footer' => true,
        ]);
    }

    /**
     * Render the icon block on the frontend
     *
     * @param array<string, mixed> $attributes Block attributes
     * @param string $content Block content
     * @return string Rendered HTML
     */
    public function render_icon_block(array $attributes, string $content): string
    {
        // normalize attributes such as className to class, etc.
        $attributes = $this->normalizeAttribute($attributes);

        $svg = $this->iconService->get_icon($attributes['name'] ?? '', $attributes);

        // Build attribute string for the jooosi-icon element.
        $attrString = '';
        foreach ($attributes as $key => $value) {
            if ($value !== false && $value !== null) {
                $attrString .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
            }
        }

        if ($svg !== null) {
            /*
             * Security: SVG content is sanitized by IconService->get_icon() using enshrined/svg-sanitize library.
             * Attributes are escaped with esc_attr() above. The SVG content cannot be escaped with esc_html()
             * as it would break the SVG markup. We use render-time sanitization for defense-in-depth security.
             */
            // SSR: Render with data-prerendered attribute and SVG content inside
            return sprintf(
                '<jooosi-icon data-prerendered%s>%s</jooosi-icon>',
                $attrString,
                $svg
            );
        }

        // Fallback: let frontend handle the error (client-side rendering)
        return sprintf(
            '<jooosi-icon%s></jooosi-icon>',
            $attrString
        );
    }

    private function normalizeAttribute(array $attributes): array
    {
        $normalized = $attributes;

        // Normalize className to class
        if (isset($attributes['className']) && !isset($attributes['class'])) {
            $normalized['class'] = $attributes['className'];
            unset($normalized['className']);
        }

        // Normalize dimensions: if one is undefined/false, match it with the defined one
        $hasWidth = isset($normalized['width']) && $normalized['width'] !== false;
        $hasHeight = isset($normalized['height']) && $normalized['height'] !== false;

        if ($hasWidth && !$hasHeight) {
            $normalized['height'] = $normalized['width'];
        } elseif ($hasHeight && !$hasWidth) {
            $normalized['width'] = $normalized['height'];
        }

        return $normalized;
    }
}
