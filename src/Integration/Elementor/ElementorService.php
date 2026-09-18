<?php

declare(strict_types=1);

namespace JooosiIcon\Integration\Elementor;

use JOOOSI_ICON;
use JooosiIcon\Core\Discovery\Attributes\Hook;
use JooosiIcon\Core\Discovery\Attributes\Service;
use JooosiIcon\Services\ViteService;

/**
 * Service for registering and managing Elementor integration
 */
#[Service]
class ElementorService
{
    public function __construct(
        private ViteService $viteService,
    ) {}

    /**
     * Register the Elementor widgets
     *
     * Registers custom Jooosi Icon widget for Elementor when Elementor is active.
     */
    #[Hook('elementor/widgets/register', priority: 10)]
    public function register_widgets($widgets_manager): void
    {
        // Check if Elementor is active
        if (!did_action('elementor/loaded')) {
            return;
        }

        // Require the widget file
        require_once __DIR__ . '/Widgets/IconWidget.php';

        // Register the Jooosi Icon widget.
        $widgets_manager->register(new \JooosiIcon\Integration\Elementor\Widgets\IconWidget());
    }

    /**
     * Enqueue editor assets for Elementor
     */
    #[Hook('elementor/editor/after_enqueue_scripts', priority: 10)]
    public function editor_assets(): void
    {
        // Enqueue jooosi-icon web component for the editor
        $this->viteService->enqueue_asset(
            'resources/webcomponents/jooosi-icon.ts',
            [
                'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':web-component:jooosi-icon',
                'in_footer' => true,
            ]
        );

        // Enqueue Gutenberg icon block styles (reuse for Elementor)
        $this->viteService->enqueue_asset('resources/integration/gutenberg/blocks/icon-block/editor.css', [
            'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':gutenberg-icon-block-editor-styles',
        ]);

        // Enqueue Elementor editor integration script
        $this->viteService->enqueue_asset('resources/integration/elementor/editor.ts', [
            'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':integration-elementor-editor',
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
    }

    /**
     * Enqueue frontend assets for rendering jooosi-icon on the frontend
     */
    #[Hook('elementor/frontend/after_enqueue_scripts', priority: 10)]
    public function frontend_assets(): void
    {
        // Enqueue jooosi-icon web component
        $this->viteService->enqueue_asset(
            'resources/webcomponents/jooosi-icon.ts',
            [
                'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':web-component:jooosi-icon',
                'in_footer' => true,
            ]
        );
    }

    /**
     * Register custom categories for Elementor
     */
    #[Hook('elementor/elements/categories_registered', priority: 10)]
    public function register_categories($elements_manager): void
    {
        $elements_manager->add_category(
            'jooosi-icon',
            [
                'title' => esc_html__('Jooosi Icon', 'jooosi-icon'),
                'icon' => 'fa fa-star',
            ]
        );
    }
}
