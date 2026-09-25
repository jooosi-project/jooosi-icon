<?php

declare(strict_types=1);

namespace JooosiIcon\Integration\Bricks\Elements;

/**
 * Legacy Bricks element type kept for layouts saved before the rebrand.
 */
class OmniIconElement extends IconElement
{
    public const ELEMENT_NAME = 'omni-icon';

    public $name = self::ELEMENT_NAME;

    public function get_label()
    {
        return esc_html__('Omni Icon', 'jooosi-icon');
    }
}
