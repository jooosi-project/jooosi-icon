<?php

declare (strict_types=1);
namespace JooosiIcon\Core\Discovery\Attributes;

use Attribute;
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Command
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        /** @var array<string> */
        public array $aliases = [],
        public ?string $synopsis = null,
        public ?string $when = 'after_wp_load'
    )
    {
    }
}
