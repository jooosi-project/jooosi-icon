<?php

declare(strict_types=1);

namespace JooosiIcon\Core\Discovery;

use JooosiIcon\Core\Container\Container;
use JooosiIcon\Core\Logger\DiscoveryLogger;
use JooosiIcon\Core\Logger\LoggerService;
use JOOOSI_ICON;
use Psr\Log\LoggerInterface;
use Throwable;

final class DiscoveryManager
{
    /** @var array<Discovery> */
    private array $discoveries = [];

    /** @var array<DiscoveryLocation> */
    private array $discoveryLocations = [];

    private ?DiscoveryCache $discoveryCache = null;

    private LoggerInterface $logger;

    /** @var array<string> */
    private array $excludedPaths = [];

    public function __construct(
        private Container $container
    ) {
        $this->logger = new DiscoveryLogger();
    }

    public function discover(): void
    {
        $this->initializeDiscoveryLocations();
        $this->initializeDiscoveries();
        $this->runDiscovery();
        $this->applyDiscoveries();
    }

    /**
     * @return array<Discovery>
     */
    public function getDiscoveries(): array
    {
        return $this->discoveries;
    }

    public function clear_cache(): void
    {
        if ($this->discoveryCache instanceof DiscoveryCache) {
            $this->discoveryCache->clear();
        }
    }

    private function initializeDiscoveryLocations(): void
    {
        $this->loadComposerLocations();
        $this->loadExcludedPaths();
    }

    public function loadComposerLocations(): void
    {
        $composerData = $this->getComposerData();
        if ($composerData === null) {
            return;
        }

        if (isset($composerData['autoload']) && is_array($composerData['autoload']) && 
            isset($composerData['autoload']['psr-4']) && is_array($composerData['autoload']['psr-4'])) {
            foreach ($composerData['autoload']['psr-4'] as $namespace => $path) {
                if (is_string($namespace) && is_string($path) && str_starts_with($namespace, 'JooosiIcon\\')) {
                    $fullPath = JOOOSI_ICON::DIR . $path;
                    $this->discoveryLocations[] = new DiscoveryLocation(
                        $namespace,
                        $fullPath
                    );
                }
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG && (isset($composerData['autoload-dev']) && is_array($composerData['autoload-dev']) && isset($composerData['autoload-dev']['psr-4']) && is_array($composerData['autoload-dev']['psr-4']))) {
            foreach ($composerData['autoload-dev']['psr-4'] as $namespace => $path) {
                if (is_string($namespace) && is_string($path) && str_starts_with($namespace, 'JooosiIcon\\Tests\\')) {
                    $fullPath = JOOOSI_ICON::DIR . $path;
                    $this->discoveryLocations[] = new DiscoveryLocation(
                        $namespace,
                        $fullPath
                    );
                }
            }
        }
    }

    private function loadExcludedPaths(): void
    {
        $composerData = $this->getComposerData();
        if ($composerData === null) {
            return;
        }

        if (isset($composerData['extra']['discovery']['exclude']) && is_array($composerData['extra']['discovery']['exclude'])) {
            foreach ($composerData['extra']['discovery']['exclude'] as $path) {
                if (is_string($path)) {
                    $this->excludedPaths[] = JOOOSI_ICON::DIR . $path;
                }
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getComposerData(): ?array
    {
        $composerFile = JOOOSI_ICON::DIR . 'composer.json';
        $composerContent = file_get_contents($composerFile);

        if ($composerContent === false) {
            $this->logger->error('Failed to read composer.json', [
                'component' => 'DiscoveryManager',
                'file' => $composerFile,
            ]);
            return null;
        }

        $composerData = json_decode($composerContent, true);

        if (!is_array($composerData)) {
            $this->logger->error('Invalid composer.json format', [
                'component' => 'DiscoveryManager',
                'file' => $composerFile,
            ]);
            return null;
        }

        return $composerData;
    }

    private function initializeDiscoveries(): void
    {
        $this->discoveryCache = new DiscoveryCache($this->determineCacheStrategy());

        // Create LoggerService for discoveries that require it (no dependencies required)
        $loggerService = new LoggerService();

        $this->discoveries = [
            new ServiceDiscovery($this->container),
            new HookDiscovery($this->container),
            new CommandDiscovery($this->container, $loggerService),
            new ControllerDiscovery($this->container),
        ];
    }

    private function determineCacheStrategy(): DiscoveryCacheStrategy
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return DiscoveryCacheStrategy::PARTIAL;
        }

        return DiscoveryCacheStrategy::FULL;
    }

    private function runDiscovery(): void
    {
        foreach ($this->discoveryLocations as $discoveryLocation) {
            // Try to restore composer cache
            $composerCached = $this->discoveryCache?->restore($discoveryLocation, 'composer');
            
            if ($composerCached !== null) {
                $this->restoreFromCache($discoveryLocation, $composerCached);
            } else {
                // Scan via composer classmap and cache results
                $this->scanViaComposerClassmap($discoveryLocation);
                $this->cacheLocation($discoveryLocation, 'composer');
            }

            // For directory scanning: in PARTIAL mode, always scan; in FULL mode, use cache
            $directoryCached = $this->discoveryCache?->restore($discoveryLocation, 'directory');
            
            if ($directoryCached !== null) {
                $this->restoreFromCache($discoveryLocation, $directoryCached);
            } else {
                // Always do directory scanning to catch classes not in composer classmap
                $this->scanViaDirectoryScanner($discoveryLocation);
                $this->cacheLocation($discoveryLocation, 'directory');
            }
        }
    }

    private function restoreFromCache(DiscoveryLocation $discoveryLocation, array $cached): void
    {
        foreach ($this->discoveries as $discovery) {
            $items = $cached[$discovery::class] ?? [];
            if (!empty($items)) {
                $discoveryItems = $discovery->getItems();
                foreach ($items as $item) {
                    $discoveryItems->add($discoveryLocation, $item);
                }

                $discovery->setItems($discoveryItems);
            }
        }
    }

    private function scanViaDirectoryScanner(DiscoveryLocation $discoveryLocation): void
    {
        $path = $discoveryLocation->path;

        if (!is_dir($path)) {
            return;
        }

        $directoryScanner = new DirectoryScanner($this->discoveries, $this->logger, $this->excludedPaths);
        $directoryScanner->scan($discoveryLocation, $path);
    }

    private function scanViaComposerClassmap(DiscoveryLocation $discoveryLocation): bool
    {
        $classmap = $this->getComposerClassmap();
        if ($classmap === []) {
            return false;
        }

        $classes = [];
        foreach ($classmap as $className => $filePath) {
            if (str_starts_with($className, rtrim($discoveryLocation->namespace, '\\'))) {
                // skip if path is in excluded paths
                if ($this->isPathExcluded($filePath)) {
                    continue;
                }

                $classes[$className] = $filePath;
            }
        }

        if ($classes === []) {
            return false;
        }

        foreach (array_keys($classes) as $className) {
            // Use class_exists with autoload=false since we already have the file path from classmap
            // This prevents fatal errors when a class file has missing dependencies
            if (!class_exists($className, false)) {
                // Try to load the class file, but catch any errors
                try {
                    require_once $classes[$className];
                    if (!class_exists($className, false)) {
                        continue;
                    }
                } catch (Throwable $e) {
                    $this->logger->debug('Skipped class due to load error', ['component' => 'DiscoveryManager', 'className' => $className, 'error' => $e->getMessage()]);
                    continue;
                }
            }

            try {
                $classReflector = new ClassReflector($className);

                foreach ($this->discoveries as $discovery) {
                    $discovery->discover($discoveryLocation, $classReflector);
                }
            } catch (Throwable $e) {
                $this->logger->error('Discovery error for class', [
                    'component' => 'DiscoveryManager',
                    'className' => $className,
                    'exception' => $e,
                ]);
            }
        }

        return true;
    }

    /**
     * @return array<class-string, string>
     */
    private function getComposerClassmap(): array
    {
        $classmapFile = JOOOSI_ICON::DIR . 'vendor/composer/autoload_classmap.php';

        if (file_exists($classmapFile)) {
            $classmap = include $classmapFile;
            if (is_array($classmap)) {
                /** @var array<class-string, string> $classmap */
                return $classmap;
            }
        }

        return [];
    }

    private function cacheLocation(DiscoveryLocation $discoveryLocation, string $source): void
    {
        if (!$this->discoveryCache || !$this->discoveryCache->isEnabled()) {
            return;
        }

        $this->discoveryCache->store($discoveryLocation, $this->discoveries, $source);
    }

    private function applyDiscoveries(): void
    {
        foreach ($this->discoveries as $discovery) {
            $discovery->apply();
        }
    }

    private function isPathExcluded(string $path): bool
    {
        $realPath = realpath(dirname($path));
        if ($realPath === false) {
            return false;
        }

        foreach ($this->excludedPaths as $excludedPath) {
            $realExcludedPath = realpath($excludedPath);
            if ($realExcludedPath === false) {
                continue;
            }

            // Check if the file's directory matches or is a subdirectory of an excluded path
            if ($realPath === $realExcludedPath || str_starts_with($realPath . '/', $realExcludedPath . '/')) {
                return true;
            }
        }

        return false;
    }
}