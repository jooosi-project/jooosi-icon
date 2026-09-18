<?php

declare(strict_types=1);

namespace JooosiIcon\Integration\ACF;

use Bricks\Integrations\Dynamic_Data\Providers as BricksProvider;
use Bricks\Integrations\Dynamic_Data\Providers\Provider_Acf as BricksProviderAcf;
use JOOOSI_ICON;
use JooosiIcon\Core\Discovery\Attributes\Hook;
use JooosiIcon\Core\Discovery\Attributes\Service;
use JooosiIcon\Integration\ACF\Fields\IconField;
use JooosiIcon\Services\ViteService;

use function acf_register_field_type;

/**
 * Service for registering and managing ACF integration
 */
#[Service]
class ACFService
{
    public function __construct(
        private ViteService $viteService,
    ) {}

    /**
     * Register the ACF custom field type
     *
     * Registers Jooosi Icon field type for ACF when ACF is active.
     */
    #[Hook('acf/include_field_types', priority: 10)]
    public function register_field_type(): void
    {
        // Check if ACF is active
        if (!function_exists('acf_register_field_type')) {
            return;
        }

        acf_register_field_type(IconField::class);
    }

    /**
     * @see Bricks\Integrations\Dynamic_Data\Providers\Provider_Acf::register_tags()
     */
    #[Hook('wp_loaded')]
    public function bricks_integration(): void
    {
        // Check if ACF is active
        if (!function_exists('acf_register_field_type')) {
            return;
        }

        // Bricks integration
        if (class_exists(BricksProvider::class)) {
            /** @var BricksProviderAcf */
            $bricks_acf = BricksProvider::get_registered_provider('acf');

            if (!$bricks_acf) {
                return;
            }

            $bricks_acf->tags = array_merge(
                $bricks_acf->tags,
                [
                    'jooosi_icon' => [
                        'name' => '{jooosi_icon}',
                        'label' => esc_html__('Jooosi Icon', 'bricks'),
                        'group' => 'ACF',
                        'provider' => 'acf',
                        'queryFiltersExcludeTag' => true, // Exclude from Query Filters integration dropdown (@since 2.0.2)
                    ],
                ]
            );
        }
    }

    /**
     * Enqueue admin assets for ACF field editor
     */
    #[Hook('acf/input/admin_enqueue_scripts', priority: 10)]
    public function admin_assets(): void
    {
        // Check if ACF is active
        if (!function_exists('acf_register_field_type')) {
            return;
        }

        // Enqueue jooosi-icon web component
        $this->viteService->enqueue_asset('resources/webcomponents/jooosi-icon.ts', [
            'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':web-component:jooosi-icon',
            'in_footer' => true,
        ]);

        // Enqueue Gutenberg icon block styles (reuse for ACF)
        $this->viteService->enqueue_asset('resources/integration/gutenberg/blocks/icon-block/editor.css', [
            'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':gutenberg-icon-block-editor-styles',
        ]);

        // Enqueue ACF field editor integration script
        $this->viteService->enqueue_asset('resources/integration/acf/editor.ts', [
            'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':integration-acf-editor',
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
}
