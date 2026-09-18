<?php

declare(strict_types=1);

namespace JooosiIcon\Core\Discovery;

use JOOOSI_ICON;
use JooosiIcon\Core\Logger\DiscoveryLogger;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

final class DiscoveryCache
{
    private FilesystemAdapter $cache;

    private LoggerInterface $logger;

    public function __construct(
        private DiscoveryCacheStrategy $discoveryCacheStrategy,
    ) {
        $uploadDir = wp_upload_dir();
        $storage = JOOOSI_ICON::resolve_upload_location($uploadDir);
        $cacheDir = $storage['basedir'] . 'cache/discovery/';
        
        $this->cache = new FilesystemAdapter(
            namespace: 'JOOOSI_ICON_discovery',
            defaultLifetime: 0, // No expiration, manual invalidation only
            directory: $cacheDir
        );
        
        $this->logger = new DiscoveryLogger();
    }

    public function isEnabled(): bool
    {
        return $this->discoveryCacheStrategy !== DiscoveryCacheStrategy::NONE;
    }

    /**
     * Store discoveries in cache based on source type
     * 
     * @param array<Discovery> $discoveries
     * @param string $source 'composer' or 'directory'
     */
    public function store(DiscoveryLocation $discoveryLocation, array $discoveries, string $source): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // In PARTIAL mode, only cache composer-scanned items
        if ($this->discoveryCacheStrategy === DiscoveryCacheStrategy::PARTIAL && $source !== 'composer') {
            return;
        }

        $data = [];
        foreach ($discoveries as $discovery) {
            $data[$discovery::class] = $discovery->getItems()->all();
        }

        // Create manifest for cache validation
        $manifest = $this->createManifest($discoveryLocation);

        $cacheKey = $this->getCacheKey($discoveryLocation, $source);
        
        try {
            $item = $this->cache->getItem($cacheKey);
            $item->set([
                'data' => $data,
                'manifest' => $manifest,
            ]);
            $this->cache->save($item);
        } catch (Throwable $e) {
            $this->logger->error('Failed to store cache', [
                'component' => 'DiscoveryCache',
                'cacheKey' => $cacheKey,
                'source' => $source,
                'location' => $discoveryLocation->path,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Restore discoveries from cache
     * 
     * @param string $source 'composer' or 'directory'
     * @return array<string, mixed>|null
     */
    public function restore(DiscoveryLocation $discoveryLocation, string $source): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        // In PARTIAL mode, only restore composer-scanned items
        if ($this->discoveryCacheStrategy === DiscoveryCacheStrategy::PARTIAL && $source !== 'composer') {
            return null;
        }

        $cacheKey = $this->getCacheKey($discoveryLocation, $source);

        try {
            $item = $this->cache->getItem($cacheKey);
            
            if (! $item->isHit()) {
                return null;
            }

            $cached = $item->get();
            
            if (! is_array($cached)) {
                return null;
            }

            // Extract data and manifest
            $data = $cached['data'] ?? null;
            $cachedManifest = $cached['manifest'] ?? null;

            if (! is_array($data) || ! is_array($cachedManifest)) {
                return null;
            }

            // Check if cache is stale using manifest
            if ($this->isManifestStale($discoveryLocation, $cachedManifest)) {
                $this->cache->deleteItem($cacheKey);
                return null;
            }

            return $data;
        } catch (Throwable $e) {
            $this->logger->error('Failed to restore cache', [
                'component' => 'DiscoveryCache',
                'cacheKey' => $cacheKey,
                'source' => $source,
                'location' => $discoveryLocation->path,
                'exception' => $e,
            ]);
            return null;
        }
    }

    public function clear(): void
    {
        try {
            $this->cache->clear();
        } catch (Throwable $e) {
            $this->logger->error('Failed to clear cache', [
                'component' => 'DiscoveryCache',
                'exception' => $e,
            ]);
        }
    }

    private function getCacheKey(DiscoveryLocation $discoveryLocation, string $source): string
    {
        $hash = md5($discoveryLocation->namespace . $discoveryLocation->path);
        return sprintf('%s_%s', $hash, $source);
    }

    /**
     * Create a manifest of all PHP files in the discovery location
     * 
     * @return array{files: array<string, int>, count: int}
     */
    private function createManifest(DiscoveryLocation $discoveryLocation): array
    {
        $files = [];

        if (! is_dir($discoveryLocation->path)) {
            return ['files' => [], 'count' => 0];
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($discoveryLocation->path, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[$file->getPathname()] = $file->getMTime();
                }
            }
        } catch (Throwable $e) {
            $this->logger->warning('Failed to create manifest', [
                'component' => 'DiscoveryCache',
                'location' => $discoveryLocation->path,
                'exception' => $e,
            ]);
        }

        return [
            'files' => $files,
            'count' => count($files),
        ];
    }

    /**
     * Check if the cached manifest is stale compared to current filesystem state
     * 
     * @param array{files: array<string, int>, count: int} $cachedManifest
     */
    private function isManifestStale(DiscoveryLocation $discoveryLocation, array $cachedManifest): bool
    {
        if (! is_dir($discoveryLocation->path)) {
            return false;
        }

        try {
            $currentManifest = $this->createManifest($discoveryLocation);

            // Quick check: if file count changed, manifest is stale
            if ($currentManifest['count'] !== $cachedManifest['count']) {
                return true;
            }

            // Check if any cached files have been modified or deleted
            foreach ($cachedManifest['files'] as $filePath => $cachedMTime) {
                // File was deleted
                if (! isset($currentManifest['files'][$filePath])) {
                    return true;
                }

                // File was modified
                if ($currentManifest['files'][$filePath] !== $cachedMTime) {
                    return true;
                }
            }

            // Check if any new files were added
            foreach ($currentManifest['files'] as $filePath => $currentMTime) {
                if (! isset($cachedManifest['files'][$filePath])) {
                    return true;
                }
            }

            return false;
        } catch (Throwable $e) {
            // If we can't check, consider cache stale to be safe
            $this->logger->warning('Failed to check manifest staleness, considering stale', [
                'component' => 'DiscoveryCache',
                'location' => $discoveryLocation->path,
                'exception' => $e,
            ]);
            return true;
        }
    }
}
