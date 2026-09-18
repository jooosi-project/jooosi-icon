<?php

declare(strict_types=1);

namespace JooosiIcon\Integration\Bricks;

use Bricks\Elements;
use JOOOSI_ICON;
use JooosiIcon\Core\Discovery\Attributes\Hook;
use JooosiIcon\Core\Discovery\Attributes\Service;
use JooosiIcon\Services\ViteService;

/**
 * Service for registering and managing Bricks integration
 */
#[Service]
class BricksService
{
    public function __construct(
        private ViteService $viteService,
    ) {}

    /**
     * Register the Bricks elements
     *
     * Registers custom Jooosi Icon element for Bricks Builder when Bricks is active.
     */
    #[Hook('init', priority: 1_000_000)]
    public function register_elements(): void
    {
        // Check if Bricks is active
        if (!defined('BRICKS_VERSION')) {
            return;
        }

        // Register the Jooosi Icon element
        Elements::register_element(
            __DIR__ . '/Elements/IconElement.php',
            JOOOSI_ICON::TEXT_DOMAIN,
        );
    }

    #[Hook('wp_enqueue_scripts', priority: 1_000_000)]
    public function editor_assets()
    {
        if (!function_exists('bricks_is_builder_main') || !\bricks_is_builder_main()) {
            return;
        }

        // Enqueue jooosi-icon web component for the editor
        $this->viteService->enqueue_asset(
            'resources/webcomponents/jooosi-icon.ts',
            [
                'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':web-component:jooosi-icon',
                'in_footer' => true,
            ]
        );

        // Enqueue Gutenberg icon block styles (reuse for Bricks)
        $this->viteService->enqueue_asset('resources/integration/gutenberg/blocks/icon-block/editor.css', [
            'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':gutenberg-icon-block-editor-styles',
        ]);

        // Enqueue Bricks editor integration script
        $this->viteService->enqueue_asset('resources/integration/bricks/editor.ts', [
            'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':integration-bricks-editor',
            'in_footer' => true,
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
        ]);
    }
}
