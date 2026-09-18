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
    /**
     * Handles registered for the Gutenberg block and its iframe assets.
     *
     * @var array{view_scripts: list<string>, iframe_scripts: list<string>, styles: list<string>, editor_styles: list<string>}
     */
    private array $registered_block_assets = [
        'view_scripts' => [],
        'iframe_scripts' => [],
        'styles' => [],
        'editor_styles' => [],
    ];

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
        $block_assets = $this->register_block_assets();
        $this->registered_block_assets = $block_assets;

        $path = $this->viteService->is_development()
            ? $this->viteService->generate_development_asset_path('resources/integration/gutenberg/blocks/icon-block/block.json')
            : $this->viteService->get_manifest_dir() . '/integration/gutenberg/blocks/icon-block/block.json';

        $args = [
            'render_callback' => $this->render_icon_block(...),
        ];

        if (!empty($block_assets['styles'])) {
            $args['style'] = $block_assets['styles'];
        }

        if (!empty($block_assets['view_scripts'])) {
            $args['viewScript'] = $block_assets['view_scripts'];
        }

        $editor_styles = array_merge(
            $block_assets['styles'],
            $block_assets['editor_styles'],
        );

        if (!empty($editor_styles)) {
            $args['editorStyle'] = array_values(array_unique($editor_styles));
        }

        // Register the editor bundle as a block asset so WordPress can load it
        // through the block editor asset pipeline, including its iframe-aware
        // style handling in WordPress 7.1+.
        $editor_assets = $this->viteService->register_asset(
            'resources/integration/gutenberg/blocks/icon-block/index.jsx',
            [
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
            ]
        );

        if (is_array($editor_assets)) {
            if (!empty($editor_assets['scripts'][0])) {
                // register_block_type_from_metadata() expects the camelCase
                // metadata key when overriding block.json asset metadata.
                $args['editorScript'] = $editor_assets['scripts'][0];
            }

            if (!empty($editor_assets['styles'])) {
                $args['editorStyle'] = array_values(array_unique(array_merge(
                    $args['editorStyle'] ?? [],
                    $editor_assets['styles'],
                )));

                $this->registered_block_assets['editor_styles'] = array_values(array_unique(array_merge(
                    $this->registered_block_assets['editor_styles'],
                    $editor_assets['styles'],
                )));
            }
        }

        register_block_type(
            $path,
            $args,
        );
    }

    /**
     * Ensure the block's scripts and styles are available in the Gutenberg iframe.
     *
     * WordPress 7.1 builds the iframe asset document by running this hook with
     * temporary script and style registries. Enqueueing the handles here keeps
     * the canvas working when a theme or editor integration changes the default
     * block asset loading mode.
     */
    #[Hook('enqueue_block_assets', priority: 10)]
    public function enqueue_editor_iframe_assets(): void
    {
        if (!is_admin()) {
            return;
        }

        foreach ($this->registered_block_assets['iframe_scripts'] as $handle) {
            wp_enqueue_script($handle);
        }

        foreach (array_merge(
            $this->registered_block_assets['styles'],
            $this->registered_block_assets['editor_styles'],
        ) as $handle) {
            wp_enqueue_style($handle);
        }
    }

    /**
     * Register the block's frontend and Gutenberg iframe assets separately.
     *
     * @return array{view_scripts: list<string>, iframe_scripts: list<string>, styles: list<string>, editor_styles: list<string>}
     */
    private function register_block_assets(): array
    {
        $webcomponent_assets = $this->viteService->register_asset(
            'resources/webcomponents/jooosi-icon.ts',
            [
                'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':web-component:jooosi-icon',
                'dependencies' => [
                    // JOOOSI_ICON::TEXT_DOMAIN . ':web-component-module:error-handler-editor',
                ],
                'in_footer' => false,
            ]
        );

        $iframe_assets = $this->viteService->register_asset(
            'resources/integration/gutenberg/blocks/icon-block/iframe.ts',
            [
                'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':gutenberg-icon-block:iframe',
            ]
        );

        return [
            'view_scripts' => is_array($webcomponent_assets) && is_array($webcomponent_assets['scripts'] ?? null)
                ? $webcomponent_assets['scripts']
                : [],
            'iframe_scripts' => is_array($iframe_assets) && is_array($iframe_assets['scripts'] ?? null)
                ? $iframe_assets['scripts']
                : [],
            'styles' => is_array($webcomponent_assets) && is_array($webcomponent_assets['styles'] ?? null)
                ? $webcomponent_assets['styles']
                : [],
            'editor_styles' => is_array($iframe_assets) && is_array($iframe_assets['styles'] ?? null)
                ? $iframe_assets['styles']
                : [],
        ];
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
