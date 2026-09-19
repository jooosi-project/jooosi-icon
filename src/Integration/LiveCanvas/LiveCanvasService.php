<?php

declare (strict_types=1);
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
    public function __construct(private ViteService $viteService)
    {
    }
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
        $block = ['category' => 'Basic', 'block' => ['name' => 'Jooosi Icon', 'icon_html' => '<i class="fa fa-star" aria-hidden="true"></i>', 'template_html' => '<jooosi-icon name="jooosi:livecanvas" lc-helper="jooosi-icon" width="50"></jooosi-icon>'], 'options' => ['insertAt' => ['after' => 'Icon']]];
        $blockCat = wp_json_encode($block["category"]);
        $blockDef = wp_json_encode($block["block"]);
        $blockOpts = wp_json_encode($block["options"]);
        // Register and enqueue inline script using WordPress standards
        wp_register_script(
            'jooosi-icon-lc-add-block',
            '',
            // Empty source since it's inline only
            [],
            JOOOSI_ICON::VERSION,
            \false
        );
        wp_enqueue_script('jooosi-icon-lc-add-block');
        $inline_script = "\ntry {\n    if (typeof addBlock === 'function') {\n        addBlock(\n            {$blockCat},\n            {$blockDef},\n            {$blockOpts}\n        );\n    }\n\n    if (typeof addEditable === 'function') {\n        addEditable('jooosi-icon', {\n            selector: 'jooosi-icon',\n        });\n        addEditable('omni-icon', {\n            selector: 'omni-icon',\n        });\n    }\n} catch (e) {\n    console.error(e);\n}\n";
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
    #[Hook('lc_editor_before_body_closing', priority: 1000000)]
    public function editor_assets(): void
    {
        // Enqueue the Jooosi Icon web component for the editor.
        $this->viteService->enqueue_asset('resources/webcomponents/jooosi-icon.ts', ['handle' => JOOOSI_ICON::TEXT_DOMAIN . ':web-component:jooosi-icon', 'in_footer' => \true]);
        // Enqueue Gutenberg icon block styles (reuse for LiveCanvas)
        $this->viteService->enqueue_asset('resources/integration/gutenberg/blocks/icon-block/editor.css', ['handle' => JOOOSI_ICON::TEXT_DOMAIN . ':gutenberg-icon-block-editor-styles']);
        // Enqueue LiveCanvas editor integration script
        $this->viteService->enqueue_asset('resources/integration/livecanvas/editor.ts', ['handle' => JOOOSI_ICON::TEXT_DOMAIN . ':integration-livecanvas-editor', 'in_footer' => \true, 'dependencies' => ['wp-element', 'wp-components', 'wp-i18n', 'wp-data', 'react', 'react-dom']]);
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
        $this->viteService->enqueue_asset('resources/webcomponents/jooosi-icon.ts', ['handle' => JOOOSI_ICON::TEXT_DOMAIN . ':web-component:jooosi-icon', 'in_footer' => \true]);
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
            '',
            // Empty source since it's inline only
            ['jquery'],
            JOOOSI_ICON::VERSION,
            \false
        );
        wp_enqueue_script('jooosi-icon-lc-panel');
        $inline_script = "\ndocument.addEventListener('DOMContentLoaded', () => {\n    const PANEL_SELECTOR = 'section[item-type=\"jooosi-icon\"], section[item-type=\"omni-icon\"]';\n    const panels = document.querySelectorAll(PANEL_SELECTOR);\n    if (!panels.length) return;\n\n    // WHEN PANEL BECOMES VISIBLE, INITIALIZE THE PANEL FIELDS\n    onVisible(PANEL_SELECTOR, () => {\n        console.log('[Jooosi Icon] Panel opened');\n\n        const panel = Array.from(panels).find((candidate) => candidate.hasAttribute('selector')) || panels[0];\n        const selector = panel.getAttribute('selector');\n        const theSection = jQuery(panel);\n        if (!selector) return;\n\n        // Get the selected icon element.\n        const jooosiIconElement = doc.querySelector(selector);\n        if (!jooosiIconElement) return;\n\n        // Populate icon name\n        theSection.find('input[attribute-name=\"name\"]').val(jooosiIconElement.getAttribute('name') || '');\n\n        // Populate size from width attribute\n        const iconWidth = jooosiIconElement.getAttribute('width');\n        if (iconWidth) {\n            const sizeValue = parseInt(iconWidth);\n            theSection.find('input[name=\"size\"]').val(sizeValue);\n            theSection.find('.size-feedback').text(sizeValue + 'px');\n        }\n    });\n});\n\n// Use jQuery event delegation like LiveCanvas's SVG icon panel\njQuery(document).ready(function (\$) {\n    // Handle icon name changes\n    \$('#sidepanel').on('input', 'section[item-type=jooosi-icon] input[attribute-name=\"name\"], section[item-type=omni-icon] input[attribute-name=\"name\"]', function(event) {\n        event.preventDefault();\n        const theSection = \$(this).closest('section[selector]');\n        const selector = theSection.attr('selector');\n        const jooosiIconElement = doc.querySelector(selector);\n        \n        if (jooosiIconElement) {\n            jooosiIconElement.setAttribute('name', \$(this).val());\n            updatePreviewSectorial(selector);\n        }\n    });\n\n    // Handle size slider changes\n    \$('#sidepanel').on('input', 'section[item-type=jooosi-icon] input[name=size], section[item-type=omni-icon] input[name=size]', function(event) {\n        event.preventDefault();\n        const theSection = \$(this).closest('section[selector]');\n        const selector = theSection.attr('selector');\n        const jooosiIconElement = doc.querySelector(selector);\n        \n        if (jooosiIconElement) {\n            const sizeValue = \$(this).val();\n            \n            jooosiIconElement.setAttribute('width', sizeValue);\n            jooosiIconElement.setAttribute('height', sizeValue);\n            theSection.find('.size-feedback').text(sizeValue + 'px');\n            \n            // Update the common form field for width/height if it exists\n            theSection.find('.common-form-fields input[attribute-name=width]').val(sizeValue);\n            theSection.find('.common-form-fields input[attribute-name=height]').val(sizeValue);\n            \n            updatePreviewSectorial(selector);\n        }\n    });\n\n    // Handle icon picker button\n    \$('#sidepanel').on('click', 'section[item-type=jooosi-icon] .jooosi-icon-picker-button, section[item-type=omni-icon] .jooosi-icon-picker-button', function(event) {\n        event.preventDefault();\n        event.stopPropagation();\n        \n        const theSection = \$(this).closest('section[selector]');\n        const selector = theSection.attr('selector');\n        const jooosiIconElement = doc.querySelector(selector);\n        \n        if (!jooosiIconElement) return;\n\n        const currentValue = jooosiIconElement.getAttribute('name') || '';\n        \n        if (window.jooosiIconPicker) {\n            window.jooosiIconPicker.open(currentValue, (iconName) => {\n                // Update the input field\n                theSection.find('input[attribute-name=\"name\"]').val(iconName);\n                \n                // Update doc element\n                jooosiIconElement.setAttribute('name', iconName);\n                updatePreviewSectorial(selector);\n            });\n        }\n    });\n});\n";
        wp_add_inline_script('jooosi-icon-lc-panel', $inline_script);
        ?>
        <!-- Jooosi Icon Panel -->
        <?php 
        foreach (['jooosi-icon', 'omni-icon'] as $item_type) {
            ?>
        <section item-type="<?php 
            echo esc_attr($item_type);
            ?>">
            <h1><?php 
            echo esc_html__('Jooosi Icon', 'jooosi-icon');
            ?></h1>
            
            <form class="add-common-form-elements">
                
                <!-- Icon Name Field -->
                <div>
                    <label><?php 
            echo esc_html__('Icon Name', 'jooosi-icon');
            ?></label>
                    <input 
                        type="text" 
                        attribute-name="name" 
                        value="" 
                        placeholder="mdi:home"
                        class="zoomable"
                    >
                    <small><?php 
            echo esc_html__('Format: prefix:name (e.g., mdi:home, fa:github, lucide:star)', 'jooosi-icon');
            ?></small>
                </div>

                <!-- Browse Icons Button -->
                <div style="margin: 10px 0;">
                    <button 
                        type="button" 
                        class="jooosi-icon-picker-button"
                        style="width: 100%; padding: 8px 16px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer;"
                    >
                        <?php 
            echo esc_html__('Browse Icons', 'jooosi-icon');
            ?>
                    </button>
                </div>

                <!-- Size Section -->
                <div style="position:relative">
                    <label><?php 
            echo esc_html__('Size', 'jooosi-icon');
            ?></label>
                    <div class="size-feedback"></div>
                    <input value="24" type="range" name="size" min="1" max="1024" step="1">
                </div>

                <!-- Color Widget -->
                <div>
                    <div build_widget_for="color"></div>
                </div>

            </form>
        </section>
        <?php 
        }
        ?>
        <?php 
        wp_print_scripts('jooosi-icon-lc-panel');
    }
}
