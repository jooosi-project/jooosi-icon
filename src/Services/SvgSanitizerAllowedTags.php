<?php

declare(strict_types=1);

namespace JooosiIcon\Services;

use enshrined\svgSanitize\data\AllowedTags;

/**
 * Adds the core SMIL elements used by animated SVG icons to the sanitizer's
 * existing SVG element allowlist.
 */
final class SvgSanitizerAllowedTags extends AllowedTags
{
    public static function getTags(): array
    {
        return array_values(array_unique([
            ...parent::getTags(),
            'animate',
            'set',
        ]));
    }
}
