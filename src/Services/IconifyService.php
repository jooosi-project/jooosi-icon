<?php

declare(strict_types=1);

namespace JooosiIcon\Services;

use JOOOSI_ICON;
use JooosiIcon\Core\Discovery\Attributes\Service;
use JooosiIcon\Core\Logger\LogComponent;
use JooosiIcon\Core\Logger\LoggerService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use JooosiIcon\Core\Icon\Iconify as UXIconify;
use JooosiIcon\Core\Icon\IconRegistryInterface;
use JooosiIcon\Core\Icon\Registry\CacheIconRegistry;
use JooosiIcon\Core\Icon\Registry\IconifyOnDemandRegistry;

/**
 * Iconify service for registry and metadata access.
 */
#[Service]
class IconifyService
{
    private IconRegistryInterface $registry;
    private UXIconify $iconify;
    private FilesystemAdapter $searchCache;

    public function __construct(
        private LoggerService $logger,
    ) {
        // Initialize Symfony cache adapter for icon metadata
        $storage = JOOOSI_ICON::resolve_upload_location(wp_upload_dir());
        $cache = new FilesystemAdapter('iconify', 0, $storage['basedir'] . 'cache/iconify');

        // Initialize Symfony UX Iconify (for fetching icon sets metadata)
        $this->iconify = new UXIconify($cache);

        // Create the IconifyOnDemandRegistry for remote icons
        $onDemandRegistry = new IconifyOnDemandRegistry($this->iconify);

        // Wrap on-demand registry with cache
        $this->registry = new CacheIconRegistry($onDemandRegistry, $cache);
        
        // Initialize cache for search API requests (5 minutes TTL)
        $searchCacheDir = $storage['basedir'] . 'cache/iconify-search';
        $this->searchCache = new FilesystemAdapter('iconify_search', 300, $searchCacheDir);
    }

    public function get_registry(): IconRegistryInterface
    {
        return $this->registry;
    }

    public function get_icon_sets()
    {
        return $this->iconify->getIconSets();
    }

    /**
     * Search icons by name query - fetches ALL results (limit=999)
     *
     * @param string $query Search query (can include prefix)
     * @return array{results: array<int, array{name: string, prefix: string}>, total: int}
     */
    public function search_icons(string $query): array
    {
        $results = [];

        // Always fetch maximum results (999) for client-side pagination
        $limit = 999;
        $start = 0;

        // Parse query for prefix
        $prefix = '';
        $iconQuery = $query;
        if (str_contains($query, ':')) {
            [$prefix, $iconQuery] = explode(':', $query, 2) + ['', ''];
        }

        // Use our custom method that supports limit and start
        $searchResult = $this->iconify_search_icons($prefix, $iconQuery, $limit, $start);

        $icons = $searchResult['icons'] ?? [];
        $total = $searchResult['total'] ?? count($icons);

        foreach ($icons as $iconName) {
            [$iconPrefix, $name] = explode(':', $iconName, 2) + ['', ''];
            $results[] = [
                'name' => $name,
                'prefix' => $iconPrefix,
            ];
        }

        return [
            'results' => $results,
            'total' => $total,
        ];
    }

    /**
     * Search icons using Iconify API with custom limit and start parameters
     * Uses reflection to access the private http() method
     * Results are cached for 5 minutes
     * 
     * @source vendor/symfony/ux-icons/src/Iconify.php
     * @param string $prefix Icon set prefix
     * @param string $query Search query
     * @param int $limit Maximum number of results (min: 32, max: 999)
     * @param int $start Starting index for pagination
     * @return array API response with icons, total, limit, start, collections
     */
    public function iconify_search_icons(string $prefix, string $query, int $limit = 64, int $start = 0): array
    {
        // Create cache key based on query parameters
        $cacheKey = md5(sprintf('%s:%s:%d:%d', $prefix, $query, $limit, $start));
        
        // Try to get from cache
        $cachedItem = $this->searchCache->getItem($cacheKey);
        
        if ($cachedItem->isHit()) {
            return $cachedItem->get();
        }
        
        // Cache miss - fetch from API
        try {
            // Use reflection to access the private http() method
            $reflection = new \ReflectionClass($this->iconify);
            $httpMethod = $reflection->getMethod('http');
            $httpMethod->setAccessible(true);
            $httpClient = $httpMethod->invoke($this->iconify);

            // Make direct API request with limit and start parameters
            $response = $httpClient->request('GET', '/search', [
                'query' => [
                    'query' => $query,
                    'prefix' => $prefix,
                    'limit' => $limit,
                    'start' => $start,
                ],
            ]);

            $result = $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error('Iconify search error', [
                'component' => LogComponent::ICONIFY_SERVICE,
                'exception' => $e,
                'prefix' => $prefix,
                'query' => $query,
            ]);
            
            // Fallback to default method if reflection fails
            $fallbackResult = $this->iconify->searchIcons($prefix, $query);
            $result = $fallbackResult->getArrayCopy();
        }
        
        // Cache the result (TTL is set in constructor: 300 seconds = 5 minutes)
        $cachedItem->set($result);
        $this->searchCache->save($cachedItem);
        
        return $result;
    }
}
