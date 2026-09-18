<?php

declare(strict_types=1);

namespace JooosiIcon\Core\Discovery;

defined('ABSPATH') || exit;

use JooosiIcon\Core\Container\Container;
use JooosiIcon\Core\Discovery\Attributes\Hook;

final class HookDiscovery implements Discovery
{
    use IsDiscovery;

    /** @var array<array<string, mixed>> */
    private array $hooks = [];

    /** @var array<string> Track registered hooks to avoid duplicates */
    private array $registeredHooks = [];

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
        foreach ($classReflector->getPublicMethods() as $methodReflector) {
            $hookAttributes = $methodReflector->getAttributes(Hook::class);

            if (empty($hookAttributes)) {
                continue;
            }

            foreach ($hookAttributes as $hookAttribute) {
                $this->discoveryItems->add($discoveryLocation, [
                    'className' => $classReflector->getName(),
                    'methodName' => $methodReflector->getName(),
                    'hook' => $hookAttribute->name,
                    'type' => $hookAttribute->type ?? 'filter',
                    'priority' => $hookAttribute->priority ?? 10,
                    'acceptedArgs' => $hookAttribute->accepted_args ?? 1,
                    'isStatic' => $methodReflector->isStatic(),
                ]);
            }
        }
    }

    public function apply(): void
    {
        foreach ($this->discoveryItems as $discoveryItem) {
            if (
                is_array($discoveryItem)
                && isset($discoveryItem['className'], $discoveryItem['methodName'], $discoveryItem['hook'], $discoveryItem['priority'], $discoveryItem['acceptedArgs'])
            ) {
                $this->hooks[] = [
                    'className' => $discoveryItem['className'],
                    'methodName' => $discoveryItem['methodName'],
                    'hook' => $discoveryItem['hook'],
                    'type' => $discoveryItem['type'] ?? 'filter',
                    'priority' => $discoveryItem['priority'],
                    'acceptedArgs' => $discoveryItem['acceptedArgs'],
                    'isStatic' => $discoveryItem['isStatic'] ?? false,
                ];
            }
        }
    }

    /**
     * Register only static method hooks (can be called before container compilation)
     */
    public function registerStaticHooks(): void
    {
        $groupedHooks = [];

        foreach ($this->hooks as $hook) {
            // Only include static hooks
            if (empty($hook['isStatic'])) {
                continue;
            }

            $priority = $hook['priority'];
            $groupedHooks[$priority][] = $hook;
        }

        ksort($groupedHooks);

        foreach ($groupedHooks as $groupedHook) {
            foreach ($groupedHook as $hook) {
                $this->registerHookFromData($hook);
            }
        }
    }

    /**
     * Register all hooks (both static and instance methods)
     */
    public function registerHooks(): void
    {
        $groupedHooks = [];

        foreach ($this->hooks as $hook) {
            $priority = $hook['priority'];
            $groupedHooks[$priority][] = $hook;
        }

        ksort($groupedHooks);

        foreach ($groupedHooks as $groupedHook) {
            foreach ($groupedHook as $hook) {
                $this->registerHookFromData($hook);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function registerHookFromData(array $data): void
    {
        assert(is_string($data['className']));
        assert(is_string($data['methodName']));
        assert(is_string($data['hook']));
        assert(is_int($data['priority']));
        assert(is_int($data['acceptedArgs']));

        $className = $data['className'];
        $methodName = $data['methodName'];
        $hookName = $data['hook'];
        $type = $data['type'] ?? 'filter';
        $priority = $data['priority'];
        $acceptedArgs = $data['acceptedArgs'];
        $isStatic = $data['isStatic'] ?? false;

        // Create unique key to track registered hooks
        $hookKey = $className . '::' . $methodName . '@' . $hookName . ':' . $priority;

        // Skip if already registered
        if (isset($this->registeredHooks[$hookKey])) {
            return;
        }

        // Handle static methods
        if ($isStatic) {
            $callback = [$className, $methodName];
            assert(is_callable($callback));

            if ('action' === $type) {
                add_action($hookName, $callback, $priority, $acceptedArgs);
            } else {
                add_filter($hookName, $callback, $priority, $acceptedArgs);
            }

            // Mark as registered
            $this->registeredHooks[$hookKey] = true;

            return;
        }

        // Handle instance methods - require container registration
        if (! $this->container->has($className)) {
            return;
        }

        $instance = $this->container->get($className);
        assert(is_object($instance));

        $callback = [$instance, $methodName];
        assert(is_callable($callback));

        if ('action' === $type) {
            add_action($hookName, $callback, $priority, $acceptedArgs);
        } else {
            add_filter($hookName, $callback, $priority, $acceptedArgs);
        }

        // Mark as registered
        $this->registeredHooks[$hookKey] = true;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getHooks(): array
    {
        return $this->hooks;
    }
}