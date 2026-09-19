<?php

declare (strict_types=1);
namespace JooosiIcon\Integration\ACF\Fields;

use acf_field;
use JooosiIcon\Plugin;
use JooosiIcon\Services\IconService;
use function acf_get_url;
/**
 * Jooosi Icon field type for ACF
 *
 * Allows users to select icons from the Jooosi Icon library in ACF field groups.
 * Supports 200,000+ icons from Iconify, custom uploads, and bundled icons.
 *
 * @see https://www.advancedcustomfields.com/resources/creating-a-new-field-type/
 */
class IconField extends acf_field
{
    public function initialize()
    {
        $this->name = 'jooosi_icon';
        $this->label = __('Jooosi Icon', 'jooosi-icon');
        $this->public = \true;
        $this->show_in_rest = \true;
        $this->category = 'advanced';
        $this->description = __('Select from 200,000+ icons including custom uploads and Iconify collections', 'jooosi-icon');
        $this->preview_image = acf_get_url() . '/assets/images/field-type-previews/field-preview-icon-picker.png';
        /**
         * Defaults for custom user-facing settings for this field type.
         */
        $this->defaults = array('default_icon' => '', 'return_format' => 'string');
        /**
         * Strings used in JavaScript code.
         */
        $this->l10n = array('browse' => __('Browse Icons', 'jooosi-icon'), 'select' => __('Select Icon', 'jooosi-icon'), 'remove' => __('Remove Icon', 'jooosi-icon'));
    }
    /**
     * Settings to display when users configure a field of this type.
     *
     * @param array $field
     * @return void
     */
    public function render_field_settings($field)
    {
        // Default icon
        acf_render_field_setting($field, array('label' => __('Default Icon', 'jooosi-icon'), 'instructions' => __('Appears when creating a new post (optional)', 'jooosi-icon'), 'type' => 'text', 'name' => 'default_icon', 'placeholder' => 'mdi:home'));
        // Return format
        acf_render_field_setting($field, array('label' => __('Return Format', 'jooosi-icon'), 'instructions' => __('Specify the value returned', 'jooosi-icon'), 'type' => 'radio', 'name' => 'return_format', 'layout' => 'horizontal', 'choices' => array('string' => __('Icon Name (e.g., mdi:home)', 'jooosi-icon'), 'array' => __('Icon Array (name, prefix, svg)', 'jooosi-icon'), 'html' => __('HTML (jooosi-icon element)', 'jooosi-icon'))));
    }
    /**
     * HTML content to show when a publisher edits the field on the edit screen.
     *
     * @param array $field The field settings and values.
     * @return void
     */
    public function render_field($field)
    {
        $value = $field['value'] ?? '';
        $field_name = esc_attr($field['name']);
        $field_id = esc_attr($field['id']);
        ?>
        <div class="acf-jooosi-icon-field" data-field-key="<?php 
        echo esc_attr($field['key']);
        ?>">
            <div class="acf-jooosi-icon-controls">
                <input 
                    type="hidden" 
                    class="acf-jooosi-icon-input"
                    name="<?php 
        echo $field_name;
        ?>" 
                    id="<?php 
        echo $field_id;
        ?>"
                    value="<?php 
        echo esc_attr($value);
        ?>" 
                />
                
                <button 
                    type="button" 
                    class="button button-primary acf-jooosi-icon-browse"
                    data-action="browse"
                >
                    <?php 
        echo esc_html($this->l10n['browse']);
        ?>
                </button>
                
                <?php 
        if (!empty($value)) {
            ?>
                <button 
                    type="button" 
                    class="button acf-jooosi-icon-remove"
                    data-action="remove"
                >
                    <?php 
            echo esc_html($this->l10n['remove']);
            ?>
                </button>
                <?php 
        }
        ?>
            </div>
            
            <div class="acf-jooosi-icon-preview">
                <?php 
        if (!empty($value)) {
            ?>
                <div class="acf-jooosi-icon-display">
                    <?php 
            // Get the IconService to fetch SVG for preview
            $container = Plugin::get_instance()->container();
            $iconService = $container->get(IconService::class);
            $svg = $iconService->get_icon($value, ['width' => '32', 'height' => '32']);
            if ($svg !== null) {
                /*
                 * Security: SVG content is sanitized by IconService->get_icon() using enshrined/svg-sanitize library.
                 * We use render-time sanitization for defense-in-depth security.
                 */
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG sanitized by enshrined/svg-sanitize
                echo sprintf('<jooosi-icon data-prerendered name="%s" width="32" height="32">%s</jooosi-icon>', esc_attr($value), $svg);
            } else {
                echo sprintf('<jooosi-icon name="%s" width="32" height="32"></jooosi-icon>', esc_attr($value));
            }
            ?>
                    <div class="acf-jooosi-icon-name">
                        <code><?php 
            echo esc_html($value);
            ?></code>
                    </div>
                </div>
                <?php 
        } else {
            ?>
                <div class="acf-jooosi-icon-placeholder">
                    <p><?php 
            echo esc_html__('No icon selected', 'jooosi-icon');
            ?></p>
                </div>
                <?php 
        }
        ?>
            </div>
        </div>
        <?php 
    }
    /**
     * This filter is applied to the $value after it is loaded from the database.
     *
     * @param mixed $value The value found in the database.
     * @param mixed $post_id The post ID from which the value was loaded.
     * @param array $field The field array holding all the field options.
     * @return mixed
     */
    public function load_value($value, $post_id, $field)
    {
        // Use default icon if no value exists
        if (empty($value) && !empty($field['default_icon'])) {
            $value = $field['default_icon'];
        }
        return $value;
    }
    /**
     * This filter is applied to the $value before it is saved in the database.
     *
     * @param mixed $value The value found in the $_POST array.
     * @param mixed $post_id The post ID from which the value was loaded.
     * @param array $field The field array holding all the field options.
     * @return mixed
     */
    public function update_value($value, $post_id, $field)
    {
        // Sanitize the icon name (should be in format prefix:name)
        if (!empty($value)) {
            $value = sanitize_text_field($value);
        }
        return $value;
    }
    /**
     * This filter is applied to the $value before being returned to template API.
     *
     * @param mixed $value The value which was loaded from the database.
     * @param mixed $post_id The post ID from which the value was loaded.
     * @param array $field The field array holding all the field options.
     * @return mixed
     */
    public function format_value($value, $post_id, $field)
    {
        // Bail early if no value
        if (empty($value)) {
            return $value;
        }
        $return_format = $field['return_format'] ?? 'string';
        switch ($return_format) {
            case 'array':
                // Return icon data as array
                $parts = explode(':', $value, 2);
                return array('name' => $value, 'prefix' => $parts[0] ?? '', 'icon' => $parts[1] ?? '');
            case 'html':
                // Return as HTML jooosi-icon element
                $container = Plugin::get_instance()->container();
                $iconService = $container->get(IconService::class);
                $svg = $iconService->get_icon($value);
                if ($svg !== null) {
                    /*
                     * Security: SVG content is sanitized by IconService->get_icon() using enshrined/svg-sanitize library.
                     */
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG sanitized by enshrined/svg-sanitize
                    return sprintf('<jooosi-icon data-prerendered name="%s">%s</jooosi-icon>', esc_attr($value), $svg);
                }
                return sprintf('<jooosi-icon name="%s"></jooosi-icon>', esc_attr($value));
            case 'string':
            default:
                // Return icon name string
                return $value;
        }
    }
    /**
     * This filter is used to perform validation on the value prior to saving.
     *
     * @param bool $valid Whether the value is valid (true|false).
     * @param mixed $value The value found in the $_POST array.
     * @param array $field The field array holding all the field options.
     * @param string $input The corresponding input name for $_POST value.
     * @return bool|string
     */
    public function validate_value($valid, $value, $field, $input)
    {
        // Allow empty values if field is not required
        if (empty($value)) {
            return $valid;
        }
        // Validate icon name format (should be prefix:name)
        if (!preg_match('/^[a-z0-9-]+:[a-z0-9-]+$/i', $value)) {
            return __('Please select a valid icon in the format prefix:name', 'jooosi-icon');
        }
        return $valid;
    }
}
