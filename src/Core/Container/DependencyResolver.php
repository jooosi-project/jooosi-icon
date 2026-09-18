<?php

declare(strict_types=1);

namespace JooosiIcon\Core\Container;

use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Resolves class constructor dependencies and instantiates objects
 * 
 * @since 1.0.0
 */
final class DependencyResolver
{
    public function __construct(
        private Container $container
    ) {
    }

    /**
     * Instantiate a class with automatic dependency resolution
     * 
     * @param class-string $className
     * @throws RuntimeException If dependencies cannot be resolved
     */
    public function instantiate(string $className): object
    {
        $reflectionClass = new ReflectionClass($className);
        $constructor = $reflectionClass->getConstructor();
        
        if ($constructor === null) {
            // No constructor, simple instantiation
            return new $className();
        }
        
        $parameters = $constructor->getParameters();
        $dependencies = [];
        
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencyClassName = $type->getName();
                
                // Try to resolve from container
                if ($this->container->has($dependencyClassName)) {
                    $dependencies[] = $this->container->get($dependencyClassName);
                } else {
                    throw new RuntimeException(
                        sprintf(
                            "Cannot resolve dependency '%s' (parameter '%s') in class %s. Service not found in container.",
                            esc_html($dependencyClassName),
                            esc_html($parameter->getName()),
                            esc_html($className)
                        )
                    );
                }
            } elseif ($parameter->isDefaultValueAvailable()) {
                // Use default value if available
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                // Cannot resolve primitive types or builtin types without defaults
                throw new RuntimeException(
                    sprintf(
                        "Cannot resolve parameter '%s' of type '%s' in class %s. Primitive types must have default values.",
                        esc_html($parameter->getName()),
                        esc_html($type?->getName() ?? 'unknown'),
                        esc_html($className)
                    )
                );
            }
        }
        
        return $reflectionClass->newInstanceArgs($dependencies);
    }
}
