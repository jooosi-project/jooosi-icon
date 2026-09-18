<?php

declare(strict_types=1);

namespace JooosiIcon\Core\Discovery\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Service
{
    /**
     * @var string|null
     */
    public ?string $id;
    
    /**
     * @var bool
     */
    public bool $singleton;
    
    /**
     * @var array<string>
     */
    public array $tags;
    
    /**
     * @var string|null
     */
    public ?string $alias;
    
    /**
     * @var bool
     */
    public bool $public;

    public function __construct(
        ?string $id = null,
        bool $singleton = true,
        array $tags = [],
        ?string $alias = null,
        bool $public = false
    ) {
        $this->id = $id;
        $this->singleton = $singleton;
        $this->tags = $tags;
        $this->alias = $alias;
        $this->public = $public;
    }
}