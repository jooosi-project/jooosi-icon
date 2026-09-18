<?php

declare(strict_types=1);

namespace JooosiIcon\Integration\Breakdance;

use JOOOSI_ICON;
use JooosiIcon\Core\Discovery\Attributes\Hook;
use JooosiIcon\Core\Discovery\Attributes\Service;
use JooosiIcon\Services\ViteService;

use function Breakdance\ElementStudio\registerSaveLocation;
use function Breakdance\Util\getDirectoryPathRelativeToPluginFolder;

/**
 * Service for registering and managing Breakdance integration
 */
#[Service]
class BreakdanceService
{
    public function __construct(
        private ViteService $viteService,
    ) {}

    /**
     * Enqueue editor assets for Breakdance
     * 
     * Uses the unofficial action hook to ensure assets are loaded in Breakdance builder
     * @see wp-content/plugins/breakdance/plugin/loader/loader.php
     */
    #[Hook('breakdance_builder_footer', priority: 1_000_001)]
    public function editor_assets(): void
    {
        // Check if we're in Breakdance builder mode
        // Sanitize and validate $_GET parameter early
        $breakdance_mode = isset($_GET['breakdance']) ? sanitize_text_field(wp_unslash($_GET['breakdance'])) : '';
        if ($breakdance_mode !== 'builder') {
            return;
        }

        // Check if Breakdance is active
        if (!defined('__BREAKDANCE_VERSION')) {
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

        // Enqueue Gutenberg icon block styles (reuse for Breakdance)
        $this->viteService->enqueue_asset('resources/integration/gutenberg/blocks/icon-block/editor.css', [
            'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':gutenberg-icon-block-editor-styles',
        ]);

        // Enqueue Breakdance editor integration script
        $handle = JOOOSI_ICON::TEXT_DOMAIN . ':integration-breakdance-editor';
        $this->viteService->enqueue_asset('resources/integration/breakdance/editor.ts', [
            'handle' => $handle,
            'in_footer' => true,
            'dependencies' => [
                'wp-element',
                'wp-components',
                'wp-i18n',
                'wp-data',
                'react',
                'react-dom',
            ],
        ]);

        // Manually output the enqueued scripts since Breakdance doesn't use wp_head
        $wp_scripts = wp_scripts();
        $queue = $wp_scripts->queue;

        foreach ($queue as $handle) {
            if (strpos($handle, JOOOSI_ICON::TEXT_DOMAIN . ':') !== 0) {
                continue;
            }

            $wp_scripts->do_items($handle);
        }

        // Styles
        $wp_styles = wp_styles();
        $queue = $wp_styles->queue;
        foreach ($queue as $handle) {
            if (strpos($handle, JOOOSI_ICON::TEXT_DOMAIN . ':') !== 0) {
                continue;
            }

            $wp_styles->do_items($handle);
        }
    }

    #[Hook('breakdance_loaded')]
    public function on_breakdance_loaded(): void
    {
        // Register jooosi-icon as a Breakdance icon source
        registerSaveLocation(
            getDirectoryPathRelativeToPluginFolder(__DIR__) . '/Elements',
            'JooosiIcon\Integration\Breakdance\Elements',
            'element',
            'Jooosi Icon Elements',
            false
        );

        require_once __DIR__ . '/Elements/JooosiIcon/element.php';
    }
}
