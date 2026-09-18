<?php

declare(strict_types=1);

namespace JooosiIcon\Integration\LiveCanvas;

use JOOOSI_ICON;
use JooosiIcon\Core\Discovery\Attributes\Hook;
use JooosiIcon\Core\Discovery\Attributes\Service;
use JooosiIcon\Services\ViteService;

/**
 * Service for registering and managing LiveCanvas integration
 */
#[Service]
class LiveCanvasService
{
    public function __construct(
        private ViteService $viteService,
    ) {}

    /**
     * Register the LiveCanvas custom blocks
     *
     * Adds a Jooosi Icon block to LiveCanvas Builder when LiveCanvas is active.
     */
    #[Hook('lc_editor_header', priority: 10)]
    public function register_blocks(): void
    {
        // Check if LiveCanvas is active
        if (!defined('LC_MU_PLUGIN_NAME')) {
            return;
        }

        // Define the block configuration
        $block = [
            'category' => 'Basic',
            'block' => [
                'name' => 'Jooosi Icon',
                'icon_html' => '<i class="fa fa-star" aria-hidden="true"></i>',
                'template_html' => '<jooosi-icon name="jooosi:livecanvas" lc-helper="jooosi-icon" width="50"></jooosi-icon>'
            ],
            'options' => ['insertAt' => ['after' => 'Icon']]
        ];

        $blockCat = wp_json_encode($block["category"]);
        $blockDef = wp_json_encode($block["block"]);
        $blockOpts = wp_json_encode($block["options"]);

        // Register and enqueue inline script using WordPress standards
        wp_register_script(
            'jooosi-icon-lc-add-block',
            '', // Empty source since it's inline only
            [],
            JOOOSI_ICON::VERSION,
            false
        );
        wp_enqueue_script('jooosi-icon-lc-add-block');

        $inline_script = "
try {
    if (typeof addBlock === 'function') {
        addBlock(
            {$blockCat},
            {$blockDef},
            {$blockOpts}
        );
    }

    if (typeof addEditable === 'function') {
        addEditable('jooosi-icon', {
            selector: 'jooosi-icon',
        });
        addEditable('omni-icon', {
            selector: 'omni-icon',
        });
    }
} catch (e) {
    console.error(e);
}
";

        wp_add_inline_script('jooosi-icon-lc-add-block', $inline_script);
        wp_print_scripts('jooosi-icon-lc-add-block');
    }

    #[Hook('lc_define_custom_element')]
    public function define_custom_elements(array $elements): array
    {
        // The legacy omni-icon custom element is registered by the web component.
        // $elements['omni-icon'] = [
        //     'callback' => function($attributes, $content) {
        //         return $content;
        //     }
        // ];

        return $elements;
    }

    /**
     * Enqueue editor assets for LiveCanvas
     */
    #[Hook('lc_editor_before_body_closing', priority: 1_000_000)]
    public function editor_assets(): void
    {
        // Enqueue the Jooosi Icon web component for the editor.
        $this->viteService->enqueue_asset(
            'resources/webcomponents/jooosi-icon.ts',
            [
                'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':web-component:jooosi-icon',
                'in_footer' => true,
            ]
        );

        // Enqueue Gutenberg icon block styles (reuse for LiveCanvas)
        $this->viteService->enqueue_asset('resources/integration/gutenberg/blocks/icon-block/editor.css', [
            'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':gutenberg-icon-block-editor-styles',
        ]);

        // Enqueue LiveCanvas editor integration script
        $this->viteService->enqueue_asset('resources/integration/livecanvas/editor.ts', [
            'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':integration-livecanvas-editor',
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

        // Output enqueued assets immediately
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

    /**
     * Enqueue frontend assets for rendering Jooosi Icon on the frontend.
     */
    #[Hook('wp_enqueue_scripts', priority: 10)]
    public function frontend_assets(): void
    {
        // Only enqueue if LiveCanvas content exists
        if (!defined('LC_MU_PLUGIN_NAME')) {
            return;
        }

        // Enqueue the Jooosi Icon web component.
        $this->viteService->enqueue_asset(
            'resources/webcomponents/jooosi-icon.ts',
            [
                'handle' => JOOOSI_ICON::TEXT_DOMAIN . ':web-component:jooosi-icon',
                'in_footer' => true,
            ]
        );
    }

    /**
     * Render custom panel for Jooosi Icon in LiveCanvas editor
     */
    #[Hook('lc_render_additional_panels', priority: 10)]
    public function render_icon_panel(): void
    {
        // Register and enqueue inline script using WordPress standards
        wp_register_script(
            'jooosi-icon-lc-panel',
            '', // Empty source since it's inline only
            ['jquery'],
            JOOOSI_ICON::VERSION,
            false
        );
        wp_enqueue_script('jooosi-icon-lc-panel');

        $inline_script = "
document.addEventListener('DOMContentLoaded', () => {
    const PANEL_SELECTOR = 'section[item-type=\"jooosi-icon\"], section[item-type=\"omni-icon\"]';
    const panels = document.querySelectorAll(PANEL_SELECTOR);
    if (!panels.length) return;

    // WHEN PANEL BECOMES VISIBLE, INITIALIZE THE PANEL FIELDS
    onVisible(PANEL_SELECTOR, () => {
        console.log('[Jooosi Icon] Panel opened');

        const panel = Array.from(panels).find((candidate) => candidate.hasAttribute('selector')) || panels[0];
        const selector = panel.getAttribute('selector');
        const theSection = jQuery(panel);
        if (!selector) return;

        // Get the selected icon element.
        const jooosiIconElement = doc.querySelector(selector);
        if (!jooosiIconElement) return;

        // Populate icon name
        theSection.find('input[attribute-name=\"name\"]').val(jooosiIconElement.getAttribute('name') || '');

        // Populate size from width attribute
        const iconWidth = jooosiIconElement.getAttribute('width');
        if (iconWidth) {
            const sizeValue = parseInt(iconWidth);
            theSection.find('input[name=\"size\"]').val(sizeValue);
            theSection.find('.size-feedback').text(sizeValue + 'px');
        }
    });
});

// Use jQuery event delegation like LiveCanvas's SVG icon panel
jQuery(document).ready(function ($) {
    // Handle icon name changes
    $('#sidepanel').on('input', 'section[item-type=jooosi-icon] input[attribute-name=\"name\"], section[item-type=omni-icon] input[attribute-name=\"name\"]', function(event) {
        event.preventDefault();
        const theSection = $(this).closest('section[selector]');
        const selector = theSection.attr('selector');
        const jooosiIconElement = doc.querySelector(selector);
        
        if (jooosiIconElement) {
            jooosiIconElement.setAttribute('name', $(this).val());
            updatePreviewSectorial(selector);
        }
    });

    // Handle size slider changes
    $('#sidepanel').on('input', 'section[item-type=jooosi-icon] input[name=size], section[item-type=omni-icon] input[name=size]', function(event) {
        event.preventDefault();
        const theSection = $(this).closest('section[selector]');
        const selector = theSection.attr('selector');
        const jooosiIconElement = doc.querySelector(selector);
        
        if (jooosiIconElement) {
            const sizeValue = $(this).val();
            
            jooosiIconElement.setAttribute('width', sizeValue);
            jooosiIconElement.setAttribute('height', sizeValue);
            theSection.find('.size-feedback').text(sizeValue + 'px');
            
            // Update the common form field for width/height if it exists
            theSection.find('.common-form-fields input[attribute-name=width]').val(sizeValue);
            theSection.find('.common-form-fields input[attribute-name=height]').val(sizeValue);
            
            updatePreviewSectorial(selector);
        }
    });

    // Handle icon picker button
    $('#sidepanel').on('click', 'section[item-type=jooosi-icon] .jooosi-icon-picker-button, section[item-type=omni-icon] .jooosi-icon-picker-button', function(event) {
        event.preventDefault();
        event.stopPropagation();
        
        const theSection = $(this).closest('section[selector]');
        const selector = theSection.attr('selector');
        const jooosiIconElement = doc.querySelector(selector);
        
        if (!jooosiIconElement) return;

        const currentValue = jooosiIconElement.getAttribute('name') || '';
        
        if (window.jooosiIconPicker) {
            window.jooosiIconPicker.open(currentValue, (iconName) => {
                // Update the input field
                theSection.find('input[attribute-name=\"name\"]').val(iconName);
                
                // Update doc element
                jooosiIconElement.setAttribute('name', iconName);
                updatePreviewSectorial(selector);
            });
        }
    });
});
";

        wp_add_inline_script('jooosi-icon-lc-panel', $inline_script);

        ?>
        <!-- Jooosi Icon Panel -->
        <?php foreach (['jooosi-icon', 'omni-icon'] as $item_type): ?>
        <section item-type="<?php echo esc_attr($item_type); ?>">
            <h1><?php echo esc_html__('Jooosi Icon', 'jooosi-icon'); ?></h1>
            
            <form class="add-common-form-elements">
                
                <!-- Icon Name Field -->
                <div>
                    <label><?php echo esc_html__('Icon Name', 'jooosi-icon'); ?></label>
                    <input 
                        type="text" 
                        attribute-name="name" 
                        value="" 
                        placeholder="mdi:home"
                        class="zoomable"
                    >
                    <small><?php echo esc_html__('Format: prefix:name (e.g., mdi:home, fa:github, lucide:star)', 'jooosi-icon'); ?></small>
                </div>

                <!-- Browse Icons Button -->
                <div style="margin: 10px 0;">
                    <button 
                        type="button" 
                        class="jooosi-icon-picker-button"
                        style="width: 100%; padding: 8px 16px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer;"
                    >
                        <?php echo esc_html__('Browse Icons', 'jooosi-icon'); ?>
                    </button>
                </div>

                <!-- Size Section -->
                <div style="position:relative">
                    <label><?php echo esc_html__('Size', 'jooosi-icon'); ?></label>
                    <div class="size-feedback"></div>
                    <input value="24" type="range" name="size" min="1" max="1024" step="1">
                </div>

                <!-- Color Widget -->
                <div>
                    <div build_widget_for="color"></div>
                </div>

            </form>
        </section>
        <?php endforeach; ?>
        <?php

        wp_print_scripts('jooosi-icon-lc-panel');
    }
}
