<?php

declare(strict_types=1);

namespace JooosiIcon\Services;

use enshrined\svgSanitize\data\AllowedAttributes;

/**
 * Adds the missing standard SMIL attributes to the sanitizer's allowlist.
 */
final class SvgSanitizerAllowedAttributes extends AllowedAttributes
{
    public static function getAttributes(): array
    {
        $attributes = array_filter(
            parent::getAttributes(),
            static fn (string $attribute): bool => strtolower($attribute) !== 'additivive',
        );

        return array_values(array_unique([
            ...$attributes,
            'additive',
            'calcMode',
            'from',
            'to',
        ]));
    }
}
