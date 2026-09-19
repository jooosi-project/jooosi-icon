<?php

declare (strict_types=1);
namespace JooosiIcon\Core\Discovery;

use JooosiIcon\Core\Container\Container;
use JooosiIcon\Core\Container\DependencyResolver;
use JooosiIcon\Core\Discovery\Attributes\Command;
use JooosiIcon\Core\Logger\LogComponent;
use JooosiIcon\Core\Logger\LoggerService;
use Throwable;
use WP_CLI;
final class CommandDiscovery implements \JooosiIcon\Core\Discovery\Discovery
{
    use \JooosiIcon\Core\Discovery\IsDiscovery;
    /** @var array<array<string, mixed>> */
    private array $commands = [];
    private DependencyResolver $dependencyResolver;
    public function __construct(private Container $container, private LoggerService $logger)
    {
        $this->discoveryItems = new \JooosiIcon\Core\Discovery\DiscoveryItems();
        $this->dependencyResolver = new DependencyResolver($container);
    }
    /**
     * @param ClassReflector $classReflector
     */
    public function discover(\JooosiIcon\Core\Discovery\DiscoveryLocation $discoveryLocation, \JooosiIcon\Core\Discovery\ClassReflector $classReflector): void
    {
        // Check for class-level Command attribute (invokable command)
        $classCommandAttribute = $classReflector->getAttribute(Command::class);
        if ($classCommandAttribute !== null) {
            $this->discoveryItems->add($discoveryLocation, ['type' => 'class', 'className' => $classReflector->getName(), 'method' => '__invoke', 'name' => $classCommandAttribute->name ?? $this->generateCommandName($classReflector->getName()), 'description' => $classCommandAttribute->description ?? '', 'aliases' => $classCommandAttribute->aliases, 'synopsis' => $classCommandAttribute->synopsis, 'when' => $classCommandAttribute->when]);
        }
        // Check for method-level Command attributes
        foreach ($classReflector->getPublicMethods() as $methodReflector) {
            $methodCommandAttribute = $methodReflector->getAttribute(Command::class);
            if ($methodCommandAttribute !== null) {
                $this->discoveryItems->add($discoveryLocation, ['type' => 'method', 'className' => $classReflector->getName(), 'method' => $methodReflector->getName(), 'name' => $methodCommandAttribute->name ?? $this->generateMethodCommandName($classReflector->getName(), $methodReflector->getName()), 'description' => $methodCommandAttribute->description ?? '', 'aliases' => $methodCommandAttribute->aliases, 'synopsis' => $methodCommandAttribute->synopsis, 'when' => $methodCommandAttribute->when]);
            }
        }
    }
    public function apply(): void
    {
        foreach ($this->discoveryItems as $discoveryItem) {
            if (is_array($discoveryItem)) {
                /** @var array<string, mixed> $item */
                $this->commands[] = ['className' => $discoveryItem['className'], 'name' => $discoveryItem['name'], 'description' => $discoveryItem['description'], 'aliases' => $discoveryItem['aliases'], 'synopsis' => $discoveryItem['synopsis'], 'when' => $discoveryItem['when'], 'type' => $discoveryItem['type'], 'method' => $discoveryItem['method']];
            }
        }
    }
    public function registerCommands(): void
    {
        if (!class_exists('WP_CLI')) {
            return;
        }
        foreach ($this->commands as $command) {
            $this->registerCommandFromData($command);
        }
    }
    /**
     * @param array<string, mixed> $data
     */
    private function registerCommandFromData(array $data): void
    {
        assert(is_string($data['className']));
        assert(is_string($data['name']));
        assert(is_string($data['description']));
        assert(is_array($data['aliases']));
        assert(is_string($data['synopsis']) || $data['synopsis'] === null);
        assert(is_string($data['when']) || $data['when'] === null);
        assert(is_string($data['type']));
        assert(is_string($data['method']));
        $className = $data['className'];
        $commandName = $data['name'];
        $description = $data['description'];
        $aliases = $data['aliases'];
        $synopsis = $data['synopsis'];
        $when = $data['when'];
        $type = $data['type'];
        $methodName = $data['method'];
        // Try to get from container first, otherwise instantiate directly
        if ($this->container->has($className)) {
            $instance = $this->container->get($className);
        } else {
            // Instantiate with manual dependency resolution for commands not in DI container
            if (!class_exists($className)) {
                return;
            }
            try {
                $instance = $this->dependencyResolver->instantiate($className);
            } catch (Throwable $e) {
                $this->logger->error('Failed to instantiate command class', ['component' => LogComponent::COMMAND_DISCOVERY, 'exception' => $e, 'className' => $className]);
                return;
            }
        }
        assert(is_object($instance));
        // Create callable based on type
        if ($type === 'class') {
            // For class-level commands, use the instance directly (invokable)
            $callable = $instance;
        } else {
            // For method-level commands, create array callable [object, method]
            $callable = [$instance, $methodName];
        }
        $args = ['shortdesc' => $description];
        if ($synopsis !== null) {
            $args['synopsis'] = $synopsis;
        }
        if ($when !== null) {
            $args['when'] = $when;
        }
        WP_CLI::add_command($commandName, $callable, $args);
        // Register aliases with the same configuration
        foreach ($aliases as $alias) {
            assert(is_string($alias));
            WP_CLI::add_command($alias, $callable, $args);
        }
    }
    /**
     * @return array<array<string, mixed>>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }
    private function generateCommandName(string $className): string
    {
        // Convert class name to command name
        // e.g., "JooosiIcon\Commands\EmailCommand" -> "jooosi-icon email"
        $parts = explode('\\', $className);
        $commandClass = end($parts);
        // end() on explode result will return string since class names are not empty
        // Remove "Command" suffix if present
        if (str_ends_with($commandClass, 'Command')) {
            $commandClass = substr($commandClass, 0, -7);
        }
        // Convert PascalCase to kebab-case
        $commandName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $commandClass) ?? '');
        return 'jooosi-icon ' . $commandName;
    }
    private function generateMethodCommandName(string $className, string $methodName): string
    {
        // Convert class name to base command name
        $parts = explode('\\', $className);
        $commandClass = end($parts);
        // Remove "Command" suffix if present
        if (str_ends_with($commandClass, 'Command')) {
            $commandClass = substr($commandClass, 0, -7);
        }
        // Convert PascalCase to kebab-case for both class and method
        $classCommand = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $commandClass) ?? '');
        $methodCommand = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $methodName) ?? '');
        return sprintf('jooosi-icon %s %s', $classCommand, $methodCommand);
    }
}
