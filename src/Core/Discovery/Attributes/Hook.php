<?php

declare (strict_types=1);
namespace JooosiIcon\Core\Discovery\Attributes;

use Attribute;
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Hook
{
    public function __construct(public string $name, public string $type = 'filter', public int $priority = 10, public int $accepted_args = 1)
    {
    }
}
