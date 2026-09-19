<?php

declare (strict_types=1);
namespace JooosiIcon\Core\Discovery\Attributes;

use Attribute;
#[Attribute(Attribute::TARGET_CLASS)]
final class Controller
{
    public function __construct(public string $namespace = 'jooosi-icon/v1', public string $prefix = '', public array $middleware = [])
    {
    }
}
