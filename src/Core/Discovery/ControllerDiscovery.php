<?php

declare(strict_types=1);

namespace JooosiIcon\Core\Discovery;

defined('ABSPATH') || exit;

use JOOOSI_ICON;
use JooosiIcon\Core\Container\Container;
use JooosiIcon\Core\Container\DependencyResolver;
use JooosiIcon\Core\Discovery\Attributes\Controller;
use JooosiIcon\Core\Discovery\Attributes\Route;
use WP_REST_Request;

final class ControllerDiscovery implements Discovery
{
    use IsDiscovery;

    /** @var array<string, array<string, mixed>> */
    private array $controllers = [];

    /** @var array<array<string, mixed>> */
    private array $routes = [];

    private DependencyResolver $dependencyResolver;

    public function __construct(
        private Container $container
    ) {
        $this->discoveryItems = new DiscoveryItems();
        $this->dependencyResolver = new DependencyResolver($container);
    }

    public function discover(DiscoveryLocation $discoveryLocation, ClassReflector $classReflector): void
    {
        $controllerAttribute = $classReflector->getAttribute(Controller::class);

        if ($controllerAttribute === null) {
            return;
        }

        $routes = [];
        foreach ($classReflector->getPublicMethods() as $methodReflector) {
            $routeAttributes = $methodReflector->getAttributes(Route::class);

            foreach ($routeAttributes as $routeAttribute) {
                $routes[] = [
                    'methodName' => $methodReflector->getName(),
                    'path' => $routeAttribute->path,
                    'methods' => $routeAttribute->methods,
                    'name' => $routeAttribute->name,
                    'middleware' => $routeAttribute->middleware ?? [],
                    'permission_callback' => $routeAttribute->permission_callback,
                    'args' => $routeAttribute->args ?? [],
                ];
            }
        }

        $this->discoveryItems->add($discoveryLocation, [
            'className' => $classReflector->getName(),
            'prefix' => $controllerAttribute->prefix ?? '',
            'namespace' => $controllerAttribute->namespace ?? JOOOSI_ICON::REST_NAMESPACE,
            'middleware' => $controllerAttribute->middleware ?? [],
            'routes' => $routes,
        ]);
    }

    public function apply(): void
    {
        foreach ($this->discoveryItems as $discoveryItem) {
            $this->registerControllerFromData($discoveryItem);
        }

        $this->registerRestRoutes();
    }

    private function registerControllerFromData(array $data): void
    {
        $className = $data['className'];
        $prefix = $data['prefix'];
        $namespace = $data['namespace'];
        $controllerMiddleware = $data['middleware'];

        if (!$this->container->has($className) && !$this->container->is_compiled()) {
            $this->container->register($className, $className);
        }

        $this->controllers[$className] = [
            'class' => $className,
            'prefix' => $prefix,
            'namespace' => $namespace,
            'middleware' => $controllerMiddleware,
        ];

        foreach ($data['routes'] as $routeData) {
            $fullPath = $prefix . $routeData['path'];

            $this->routes[] = [
                'namespace' => $namespace,
                'path' => $fullPath,
                'methods' => $routeData['methods'],
                'controller' => $className,
                'action' => $routeData['methodName'],
                'name' => $routeData['name'],
                'middleware' => array_merge($controllerMiddleware, $routeData['middleware']),
                'permission_callback' => $routeData['permission_callback'],
                'args' => $routeData['args'],
            ];
        }
    }

    private function registerRestRoutes(): void
    {
        add_action('rest_api_init', function () {
            foreach ($this->routes as $route) {
                register_rest_route(
                    $route['namespace'],
                    $route['path'],
                    [
                        'methods' => $route['methods'],
                        'callback' => $this->createRouteCallback($route),
                        'permission_callback' => $this->createPermissionCallback($route),
                        'args' => $route['args'],
                    ]
                );
            }
        });
    }

    private function createRouteCallback(array $route): callable
    {
        return function (WP_REST_Request $wprestRequest) use ($route) {
            $className = $route['controller'];

            // Try to get from container first, otherwise instantiate directly
            if ($this->container->has($className)) {
                $controller = $this->container->get($className);
            } else {
                $controller = $this->dependencyResolver->instantiate($className);
            }

            $action = $route['action'];

            $response = $this->applyMiddleware($route['middleware'], $wprestRequest, function ($request) use ($controller, $action) {
                return $controller->$action($request);
            });

            return $response;
        };
    }

    private function createPermissionCallback(array $route): callable
    {
        return function () use ($route) {
            if ($route['permission_callback']) {                
                if (function_exists($route['permission_callback'])) {
                    return call_user_func($route['permission_callback']);
                }

                return current_user_can($route['permission_callback']);
            }

            if (in_array('auth', $route['middleware'], true)) {
                return is_user_logged_in();
            }

            if (in_array('admin', $route['middleware'], true)) {
                return current_user_can('manage_options');
            }

            return true;
        };
    }

    private function applyMiddleware(array $middleware, WP_REST_Request $wprestRequest, callable $next): mixed
    {
        return $next($wprestRequest);
    }

    public function getControllers(): array
    {
        return $this->controllers;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
