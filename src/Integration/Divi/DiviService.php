<?php

declare(strict_types=1);

namespace JooosiIcon\Integration\Divi;

use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\VisualBuilder\Assets\PackageBuildManager;
use JOOOSI_ICON;
use JooosiIcon\Core\Discovery\Attributes\Hook;
use JooosiIcon\Core\Discovery\Attributes\Service;
use JooosiIcon\Services\IconService;
use JooosiIcon\Services\ViteService;
use WP_Block;

#[Service]
final class DiviService
{
    private const MODULE_CLASS_NAME = 'jooosi_icon_divi_module';

    public function __construct(
        private IconService $iconService,
        private ViteService $viteService,
    ) {}

    #[Hook('init', priority: 20)]
    public function register_module(): void
    {
        if (! $this->has_divi_module_api()) {
            return;
        }

        $module_dir = $this->viteService->is_development()
            ? JOOOSI_ICON::DIR . 'resources/integration/divi'
            : $this->viteService->get_manifest_dir() . '/integration/divi';

        ModuleRegistration::register_module(
            $module_dir,
            [
                'render_callback' => [$this, 'render_callback'],
            ]
        );
    }

    // #[Hook('divi_visual_builder_assets_before_enqueue_styles', priority: 10)]
    #[Hook('divi_visual_builder_assets_before_enqueue_scripts', priority: 10)]
    public function register_visual_builder_assets(): void
    {
        if (! $this->has_divi_visual_builder_api()) {
            return;
        }

        // Enqueue Gutenberg icon block styles (reuse for Divi)
        $this->viteService->enqueue_asset('resources/integration/gutenberg/blocks/icon-block/editor.css', [
            'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':gutenberg-icon-block-editor-styles',
        ]);

        // Enqueue Breakdance editor integration script
        $handle = JOOOSI_ICON::TEXT_DOMAIN . ':integration-divi-editor';
        $this->viteService->enqueue_asset('resources/integration/divi/editor.ts', [
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
    }

    public function render_callback(array $attrs, string $content, WP_Block $block, $elements): string
    {
        $icon_name = $this->get_field_value($attrs, 'iconName');
        if ($icon_name === '') {
            $icon_name = 'mdi:home';
        }

        $width = $this->get_field_value($attrs, 'iconWidth');
        $height = $this->get_field_value($attrs, 'iconHeight');
        $color = $this->get_field_value($attrs, 'iconColor');

        if ($width !== '' && $height === '') {
            $height = $width;
        }

        if ($height !== '' && $width === '') {
            $width = $height;
        }

        $icon_attributes = [
            'class'  => 'jooosi-icon-divi__icon',
            'name'   => $icon_name,
            'width'  => $width,
            'height' => $height,
            'color'  => $color,
        ];

        $icon_attributes = array_filter(
            $icon_attributes,
            static fn ($value): bool => $value !== '' && $value !== null && $value !== false
        );

        $svg = $this->iconService->get_icon($icon_name, $icon_attributes);

        $module_inner = HTMLUtility::render(
            [
                'tag'               => 'div',
                'attributes'        => [
                    'class' => 'et_pb_module_inner',
                ],
                'childrenSanitizer' => 'et_core_esc_previously',
                'children'          => $this->render_jooosi_icon($icon_attributes, $svg),
            ]
        );

        $module_elements = $elements->style_components(
            [
                'attrName' => 'module',
            ]
        );

        return Module::render(
            [
                'orderIndex'          => $block->parsed_block['orderIndex'],
                'storeInstance'       => $block->parsed_block['storeInstance'],
                'attrs'               => $attrs,
                'elements'            => $elements,
                'id'                  => $block->parsed_block['id'],
                'moduleClassName'     => self::MODULE_CLASS_NAME,
                'name'                => $block->block_type->name,
                'classnamesFunction'  => [$this, 'module_classnames'],
                'moduleCategory'      => $block->block_type->category,
                'stylesComponent'     => [$this, 'module_styles'],
                'scriptDataComponent' => [$this, 'module_script_data'],
                'children'            => $module_elements . $module_inner,
            ]
        );
    }

    public function module_styles(array $args): void
    {
        $elements = $args['elements'];

        Style::add(
            [
                'id'            => $args['id'],
                'name'          => $args['name'],
                'orderIndex'    => $args['orderIndex'],
                'storeInstance' => $args['storeInstance'],
                'styles'        => [
                    $elements->style(
                        [
                            'attrName'   => 'module',
                            'styleProps' => [
                                'disabledOn' => [
                                    'disabledModuleVisibility' => $args['settings']['disabledModuleVisibility'] ?? null,
                                ],
                            ],
                        ]
                    ),
                ],
            ]
        );
    }

    public function module_script_data(array $args): void
    {
        $args['elements']->script_data(
            [
                'attrName' => 'module',
            ]
        );
    }

    public function module_classnames(array $args): void
    {
        $args['classnamesInstance']->add(
            ElementClassnames::classnames(
                [
                    'attrs' => $args['attrs']['module']['decoration'] ?? [],
                ]
            )
        );
    }

    private function has_divi_module_api(): bool
    {
        return function_exists('et_builder_d5_enabled')
            && et_builder_d5_enabled()
            && class_exists(ModuleRegistration::class)
            && class_exists(Module::class)
            && class_exists(HTMLUtility::class)
            && class_exists(Style::class)
            && class_exists(ElementClassnames::class);
    }

    private function has_divi_visual_builder_api(): bool
    {
        return $this->has_divi_module_api() && class_exists(PackageBuildManager::class);
    }

    private function get_field_value(array $attrs, string $attribute_name): string
    {
        return trim((string) $this->resolve_field_value($attrs[$attribute_name]['innerContent'] ?? null));
    }

    private function resolve_field_value(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        if (! is_array($value)) {
            return '';
        }

        if (isset($value['desktop']) && is_array($value['desktop']) && array_key_exists('value', $value['desktop'])) {
            return $this->resolve_field_value($value['desktop']['value']);
        }

        if (array_key_exists('value', $value)) {
            return $this->resolve_field_value($value['value']);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function render_jooosi_icon(array $attributes, ?string $svg): string
    {
        $attribute_string = '';

        foreach ($attributes as $key => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            $attribute_string .= sprintf(' %s="%s"', esc_attr((string) $key), esc_attr((string) $value));
        }

        if ($svg !== null) {
            return sprintf('<jooosi-icon data-prerendered%s>%s</jooosi-icon>', $attribute_string, $svg);
        }

        return sprintf('<jooosi-icon%s></jooosi-icon>', $attribute_string);
    }

}
