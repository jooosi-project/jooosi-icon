<?php

declare(strict_types=1);

namespace JooosiIcon\Services;

use JOOOSI_ICON;
use JooosiIcon\Core\Discovery\Attributes\Service;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use JooosiIcon\Core\Icon\Registry\LocalSvgIconRegistry;
use JooosiIcon\Core\Icon\IconRegistryInterface;

/**
 * Service for managing built-in and third-party file-based SVG icon sources.
 * 
 * All icons from the plugin's svg/ folder are available with the "jooosi:" prefix.
 * The former "omni:" prefix remains available as an alias.
 *
 * Additional icon sources using the same directory contract can be registered with the
 * "jooosi-icon/service/icon:sources" filter.
 * 
 * Directory structure:
 * svg/
 * ├── livecanvas.svg        -> jooosi:livecanvas
 * ├── windpress.svg         -> jooosi:windpress
 * └── yabe-webfont.svg      -> jooosi:yabe-webfont
 */
#[Service]
class IconSourceService
{
    private string $svg_dir;
    private string $svg_url;
    private ?IconRegistryInterface $registry = null;
    private FilesystemAdapter $cache;

    /**
     * @var array<string, array{name: string, path: string, url: string}>|null
     */
    private ?array $icon_sources = null;

    public function __construct()
    {
        // Set up plugin SVG directory
        $this->svg_dir = JOOOSI_ICON::DIR . 'svg';
        $this->svg_url = JOOOSI_ICON::url() . 'svg';

        // Initialize cache
        $wp_upload_dir = wp_upload_dir();
        $storage = JOOOSI_ICON::resolve_upload_location($wp_upload_dir);
        $cache_dir = $storage['basedir'] . 'cache';
        wp_mkdir_p($cache_dir);
        $this->cache = new FilesystemAdapter('icon_sources', 300, $cache_dir);
    }

    public function get_registry(): IconRegistryInterface
    {
        $this->initialize_sources();

        assert($this->registry instanceof IconRegistryInterface);

        return $this->registry;
    }

    /**
     * Initialize sources only when they are first used. This allows filters from
     * a theme's functions.php to run after the plugin has booted.
     */
    private function initialize_sources(): void
    {
        if ($this->icon_sources !== null && $this->registry instanceof IconRegistryInterface) {
            return;
        }

        /**
         * Allow third-party plugins and themes to register icon source directories.
         *
         * @var mixed $iconSources
         */
        $iconSources = apply_filters(
            'jooosi-icon/service/icon:sources',
            [
                'jooosi' => [
                    'name' => 'Bundled Jooosi Icons',
                    'path' => $this->svg_dir,
                    'url' => $this->svg_url,
                ],
            ],
        );

        $this->icon_sources = $this->normalize_icon_sources($iconSources);

        // Create a local icon registry for built-in and third-party sources.
        $iconSetPaths = array_map(
            static fn(array $source): string => $source['path'],
            $this->icon_sources,
        );
        $iconSetPaths['omni'] = $this->icon_sources['jooosi']['path'];

        $this->registry = new LocalSvgIconRegistry(
            $this->svg_dir,
            $iconSetPaths,
            [
                'omni:omni-icon' => 'jooosi:jooosi-icon',
            ],
        );
    }

    /**
     * Get the SVG directory path
     */
    public function get_svg_dir(): string
    {
        return $this->svg_dir;
    }

    /**
     * Get the SVG directory URL
     */
    public function get_svg_url(): string
    {
        return $this->svg_url;
    }

    /**
     * Clear all caches
     */
    public function clear_cache(): void
    {
        $this->cache->clear();
    }

    /**
     * Get cache key based on directory modification time for auto-invalidation
     */
    private function get_cache_key(string $prefix): string
    {
        $this->initialize_sources();

        $max_mtime = 0;
        $source_signature = [];

        foreach ($this->icon_sources as $source_prefix => $source) {
            $source_signature[$source_prefix] = [$source['name'], $source['path'], $source['url']];

            if (is_dir($source['path'])) {
                $mtime = filemtime($source['path']);
                if (false !== $mtime && $mtime > $max_mtime) {
                    $max_mtime = $mtime;
                }
            }
        }

        return sprintf('%s_%s_%d', $prefix, md5(serialize($source_signature)), $max_mtime);
    }

    /**
     * Get the built-in icon set.
     * 
     * @return array{name: string, total: int, samples: array<int, string>}
     */
    public function get_icon_set(): array
    {
        return $this->get_icon_sets()['jooosi'] ?? [
            'name' => 'Bundled Jooosi Icons',
            'total' => 0,
            'samples' => [],
        ];
    }

    /**
     * Get all available file-based icon sets.
     *
     * @return array<string, array{name: string, total: int, samples: array<int, string>}>
     */
    public function get_icon_sets(): array
    {
        $cache_key = $this->get_cache_key('icon_source_sets');

        return $this->cache->get($cache_key, function (ItemInterface $item) {
            $item->expiresAfter(300); // 5 minutes

            $sets = [];

            foreach ($this->icon_sources as $prefix => $source) {
                $files = $this->scan_directory($source['path']);

                // Keep the built-in set visible even when it is empty, preserving the
                // existing response shape.
                if ('jooosi' !== $prefix && empty($files)) {
                    continue;
                }

                $sets[$prefix] = [
                    'name' => $source['name'],
                    'total' => count($files),
                    'samples' => array_map(static fn(string $file): string => pathinfo(basename($file), PATHINFO_FILENAME), $files),
                ];
            }

            return $sets;
        });
    }

    /**
     * Get all icons from registered file-based sources
     *
     * @return array<int, array{name: string, filename: string, icon_name: string, prefix: string, url: string, path: string}>
     */
    public function get_all_icons(): array
    {
        $cache_key = $this->get_cache_key('icon_source_icons');
        
        return $this->cache->get($cache_key, function (ItemInterface $item) {
            $item->expiresAfter(300); // 5 minutes
            
            $icons = [];

            foreach ($this->icon_sources as $prefix => $source) {
                $files = $this->scan_directory($source['path']);

                foreach ($files as $file) {
                    $filename = basename($file);
                    $name = pathinfo($filename, PATHINFO_FILENAME);

                    $icons[] = [
                        'name' => $name,
                        'filename' => $filename,
                        'icon_name' => "{$prefix}:{$name}",
                        'prefix' => $prefix,
                        'url' => $this->get_icon_url($source, $filename),
                        'path' => $file,
                    ];
                }
            }
            
            return $icons;
        });
    }

    /**
     * Scan directory for SVG files
     *
     * @return array<int, string> Array of file paths
     */
    private function scan_directory(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }
        
        $pattern = $directory . '/*.svg';
        $files = glob($pattern, GLOB_NOSORT);
        
        return $files !== false ? $files : [];
    }

    /**
     * Get icon content by icon name
     *
     * @param string $icon_name Icon name in format "prefix:name"
     * @return string|null The SVG content or null if not found
     */
    public function get_icon_content(string $icon_name): ?string
    {
        if (!str_contains($icon_name, ':')) {
            return null;
        }

        $this->initialize_sources();
        
        [$prefix, $name] = explode(':', $icon_name, 2);

        if ($prefix === 'omni') {
            $prefix = 'jooosi';
            if ($name === 'omni-icon') {
                $name = 'jooosi-icon';
            }
        }

        $source = $this->icon_sources[$prefix] ?? null;
        if ($source === null) {
            return null;
        }
        
        $filename = $name . '.svg';
        $file_path = $source['path'] . '/' . $filename;
        
        if (!file_exists($file_path)) {
            return null;
        }
        
        $content = file_get_contents($file_path);
        return $content === false ? null : $content;
    }

    /**
     * Search icons by name query
     *
     * @param string $query Search query
     * @return array<int, array{name: string, prefix: string}>
     */
    public function search_icons(string $query): array
    {
        $this->initialize_sources();

        // Accept both the canonical prefix and its legacy alias.
        $prefix = '';
        if (str_contains($query, ':')) {
            [$prefix, $search_term] = explode(':', $query, 2);
            if ($prefix === 'omni') {
                $prefix = 'jooosi';
            }

            if (!isset($this->icon_sources[$prefix])) {
                return [];
            }
        } else {
            $search_term = $query;
        }

        $all_icons = $this->get_all_icons();
        
        return array_values(array_map(
            static fn(array $icon): array => ['name' => $icon['name'], 'prefix' => $icon['prefix']],
            array_filter(
                $all_icons,
                static fn(array $icon): bool => ($prefix === '' || $icon['prefix'] === $prefix)
                    && str_contains($icon['name'], $search_term),
            ),
        ));
    }

    /**
     * Normalize and validate third-party icon sources.
     *
     * @param mixed $sources
     * @return array<string, array{name: string, path: string, url: string}>
     */
    private function normalize_icon_sources(mixed $sources): array
    {
        $defaultSource = [
            'name' => 'Bundled Jooosi Icons',
            'path' => $this->svg_dir,
            'url' => $this->svg_url,
        ];

        if (!is_array($sources)) {
            return ['jooosi' => $defaultSource];
        }

        $normalized = ['jooosi' => $defaultSource];

        foreach ($sources as $prefix => $source) {
            if (
                !is_string($prefix)
                || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $prefix)
                || in_array($prefix, ['local', 'omni'], true)
                || !is_array($source)
                || !isset($source['path'])
                || !is_string($source['path'])
            ) {
                continue;
            }

            $name = $source['name'] ?? ucfirst(str_replace('-', ' ', $prefix));
            $url = $source['url'] ?? '';

            if (!is_string($name) || !is_string($url)) {
                continue;
            }

            $normalized[$prefix] = [
                'name' => $name,
                'path' => rtrim($source['path'], '/'),
                'url' => rtrim($url, '/'),
            ];
        }

        return $normalized;
    }

    /**
     * @param array{name: string, path: string, url: string} $source
     */
    private function get_icon_url(array $source, string $filename): string
    {
        if ($source['url'] === '') {
            return '';
        }

        return $source['url'] . '/' . $filename;
    }
}
