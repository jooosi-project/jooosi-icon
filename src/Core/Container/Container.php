<?php

declare(strict_types=1);

namespace JooosiIcon\Core\Container;

use JOOOSI_ICON;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;


final class Container implements ContainerInterface
{
    private ContainerBuilder $containerBuilder;

    private bool $compiled = false;

    public function __construct()
    {
        $this->containerBuilder = new ContainerBuilder();
        $this->configure_core_services();
        $this->register_compiler_passes();
    }

    public function get(string $id): mixed
    {
        if (! $this->compiled) {
            $this->compile();
        }

        return $this->containerBuilder->get($id);
    }

    public function has(string $id): bool
    {
        if (! $this->compiled) {
            $this->compile();
        }

        return $this->containerBuilder->has($id);
    }

    public function register(string $id, string $class): Definition
    {
        if ($this->compiled) {
            throw new RuntimeException('Cannot register services after container compilation');
        }

        $definition = new Definition($class);
        $definition->setAutowired(true);
        $definition->setAutoconfigured(true);
        $definition->setPublic(true);

        $this->containerBuilder->setDefinition($id, $definition);

        return $definition;
    }

    public function alias(string $alias, string $id): void
    {
        if ($this->compiled) {
            throw new RuntimeException('Cannot create aliases after container compilation');
        }

        $this->containerBuilder->setAlias($alias, $id);
    }

    public function parameter(string $name, mixed $value): void
    {
        if ($this->compiled) {
            throw new RuntimeException('Cannot set parameters after container compilation');
        }

        /** @phpstan-ignore-next-line */
        $this->containerBuilder->setParameter($name, $value);
    }

    public function compile(): void
    {
        if ($this->compiled) {
            return;
        }
        
        // Set synthetic services before compilation, e.g. WordPress globals
        $this->set_synthetic_services();

        $this->containerBuilder->compile();
        
        $this->compiled = true;
    }

    public function is_compiled(): bool
    {
        return $this->compiled;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function findTaggedServiceIds(string $tag): array
    {
        if (! $this->compiled) {
            $this->compile();
        }

        return $this->containerBuilder->findTaggedServiceIds($tag);
    }

    private function configure_core_services(): void
    {
        $this->parameter('jooosi-icon.plugin_dir', JOOOSI_ICON::DIR);
        $this->parameter('jooosi-icon.plugin_url', JOOOSI_ICON::url());
        $this->parameter('jooosi-icon.version', JOOOSI_ICON::VERSION);

        $this->containerBuilder->setAlias(ContainerInterface::class, 'service_container');


    }

    private function register_compiler_passes(): void
    {
    }

    private function set_synthetic_services(): void
    {
        // Reserved for future synthetic services
    }
}
