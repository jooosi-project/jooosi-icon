<?php

declare(strict_types=1);

namespace JooosiIcon\Core\Discovery;

use JooosiIcon\Core\Container\Container;
use JooosiIcon\Core\Discovery\Attributes\Service;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\DependencyInjection\Reference;

final class ServiceDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private Container $container
    ) {
        $this->discoveryItems = new DiscoveryItems();
    }

    /**
     * @param ClassReflector $classReflector
     */
    public function discover(DiscoveryLocation $discoveryLocation, ClassReflector $classReflector): void
    {
        $serviceAttribute = $classReflector->getAttribute(Service::class);

        if (null === $serviceAttribute) {
            return;
        }

        $this->discoveryItems->add($discoveryLocation, [
            'className' => $classReflector->getName(),
            'serviceId' => $serviceAttribute->id ?? $classReflector->getName(),
            'singleton' => $serviceAttribute->singleton,
            'public' => $serviceAttribute->public,
            'tags' => $serviceAttribute->tags,
            'alias' => $serviceAttribute->alias,
        ]);
    }

    public function apply(): void
    {
        foreach ($this->discoveryItems as $discoveryItem) {
            if (is_array($discoveryItem)) {
                /** @var array<string, mixed> $item */
                $this->registerServiceFromData($discoveryItem);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function registerServiceFromData(array $data): void
    {
        assert(is_string($data['className']));
        assert(is_string($data['serviceId']));

        $className = $data['className'];
        $serviceId = $data['serviceId'];

        $definition = $this->container->register($serviceId, $className);

        if (! $data['singleton']) {
            $definition->setShared(false);
        }

        if ($data['public']) {
            $definition->setPublic(true);
        }

        assert(is_array($data['tags']));
        foreach ($data['tags'] as $tag) {
            assert(is_string($tag));
            $definition->addTag($tag);
        }

        if ($data['alias']) {
            assert(is_string($data['alias']));
            $this->container->alias($data['alias'], $serviceId);
        }

        if ($serviceId !== $className) {
            $this->container->alias($className, $serviceId);
        }

        if (class_exists($className) && is_subclass_of($className, LoggerAwareInterface::class)) {
            $definition->addMethodCall('setLogger', [
                new Reference('logger'),
            ]);
        }
    }
}