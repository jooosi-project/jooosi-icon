<?php

declare(strict_types=1);

namespace JooosiIcon\Services;

use enshrined\svgSanitize\Sanitizer;
use JOOOSI_ICON;
use JooosiIcon\Core\Discovery\Attributes\Service;
use JooosiIcon\Core\Logger\LogComponent;
use JooosiIcon\Core\Logger\LoggerService;
use JooosiIcon\Core\Icon\Exception\IconNotFoundException;
use JooosiIcon\Core\Icon\IconRegistryInterface;
use JooosiIcon\Core\Icon\Registry\ChainIconRegistry;

/**
 * Icon service for rendering SVG icons from local uploads, file-based sources, and Iconify API.
 *
 * @example
 * // Basic usage with local and remote icons
 * $iconService->get_icon('local:my-icon');
 * $iconService->get_icon('jooosi:livecanvas');
 * $iconService->get_icon('about-us:old-logo');
 * $iconService->get_icon('mdi:home');
 *
 * // With attributes
 * $iconService->get_icon('prefix:name', ['class' => 'icon-large', 'width' => '32', 'height' => '32']);
 */
#[Service]
class IconService
{
    private ?IconRegistryInterface $registry = null;
    private Sanitizer $sanitizer;

    public function __construct(
        private LocalIconService $localIconService,
        private IconSourceService $iconSourceService,
        private IconifyService $iconifyService,
        private LoggerService $logger,
    ) {
        // Initialize SVG sanitizer for render-time sanitization
        $this->sanitizer = new Sanitizer();
    }

    /**
     * Build the registry only when it is first used so late-loaded theme filters
     * can register sources before the source service initializes.
     */
    private function get_or_create_registry(): IconRegistryInterface
    {
        if (!$this->registry instanceof IconRegistryInterface) {
            // Chain registries: local icons take precedence over file-based sources,
            // followed by on-demand Iconify icons.
            $this->registry = new ChainIconRegistry([
                $this->localIconService->get_registry(), // Check local uploaded icons first
                $this->iconSourceService->get_registry(), // Check built-in and third-party sources next
                $this->iconifyService->get_registry(), // Fallback to on-demand Iconify icons
            ]);
        }

        return $this->registry;
    }

    /**
     * Get an icon and return as sanitized HTML string.
     *
     * SVG content is sanitized at render time using enshrined/svg-sanitize library
     * for defense-in-depth security, ensuring safe output for WordPress.org requirements.
     *
     * @param string $name Icon name in format "prefix:icon-name" (e.g., "mdi:home", "bi:github")
     * @param array<string, mixed> $attributes Optional HTML attributes to add to the SVG element
     * @return null|string the sanitized SVG HTML if exists, or null if couldn't be found
     */
    public function get_icon(string $name, array $attributes = []): ?string
    {
        // Validate icon name format
        if (! str_contains($name, ':')) {
            return null;
        }

        try {
            // Fetch icon from registry (with caching)
            $icon = $this->get_or_create_registry()->get($name);

            // Add custom attributes if provided
            if (! empty($attributes)) {
                $icon = $icon->withAttributes($attributes);
            }

            $svg = $icon->toHtml();

            $skipSanitization = apply_filters(
                'jooosi-icon/service/icon:skip_render_sanitization',
                false,
                $name,
                $attributes,
            );
            $skipSanitization = apply_filters_deprecated(
                'omni-icon/service/icon:skip_render_sanitization',
                [$skipSanitization, $name, $attributes],
                JOOOSI_ICON::VERSION,
                'jooosi-icon/service/icon:skip_render_sanitization',
            );

            if ($skipSanitization) {
                return $svg;
            }

            // Apply SVG sanitization at render time for defense-in-depth security
            // This ensures all SVG output is safe, even from remote sources (Iconify API)
            $sanitized = $this->sanitizer->sanitize($svg);
            
            if ($sanitized === false || empty($sanitized)) {
                $this->logger->warning('SVG sanitization failed at render time', [
                    'component' => LogComponent::ICON_SERVICE,
                    'icon' => $name,
                ]);
                return null;
            }
            
            return $sanitized;
        } catch (IconNotFoundException $e) {
            $this->logger->warning('Icon not found', [
                'component' => LogComponent::ICON_SERVICE,
                'icon' => $name,
            ]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get icon', [
                'component' => LogComponent::ICON_SERVICE,
                'exception' => $e,
                'icon' => $name,
            ]);
            return null;
        }
    }

    /**
     * Get icon data as array (useful for API responses)
     *
     * @param string $name Icon name in format "prefix:icon-name"
     * @return array{svg: string|null, name: string, prefix: string}|null
     */
    public function get_icon_data(string $name): ?array
    {
        if (! str_contains($name, ':')) {
            return null;
        }

        [$prefix, $icon] = explode(':', $name, 2);

        $svg = $this->get_icon($name);
        
        if ($svg === null) {
            return null;
        }

        return [
            'svg' => $svg,
            'name' => $icon,
            'prefix' => $prefix,
        ];
    }

    /**
     * Get the icon registry instance
     *
     * @return IconRegistryInterface
     */
    public function get_registry(): IconRegistryInterface
    {
        return $this->get_or_create_registry();
    }

    /**
     * Get all available icon sets from local, file-based, and Iconify registries.
     *
     * @return array<string, array{name: string, total: int, samples: array<int, string>}>
     */
    public function get_icon_sets(): array
    {
        $localSets = $this->localIconService->get_icon_sets();
        $sourceSets = $this->iconSourceService->get_icon_sets();
        $iconifySets = $this->iconifyService->get_icon_sets();

        return array_merge($localSets, $sourceSets, $iconifySets);
    }

    /**
     * Search for icons - fetches ALL results from local, file-based, and Iconify (limit=999)
     * Iconify results are cached for 5 minutes
     *
     * @param string $query Search query string
     * @return array{results: array<int, array{name: string, prefix: string}>, total: int}
     */
    public function search_icons(string $query): array
    {
        // Get local icons (already cached)
        $localResults = $this->localIconService->search_icons($query);
        
        // Get file-based source icons (already cached)
        $sourceResults = $this->iconSourceService->search_icons($query);
        
        // Fetch all results from Iconify (limit=999, cached for 5 minutes)
        $iconifyResults = $this->iconifyService->search_icons($query);
        
        // Merge local icons first, then file-based sources, then Iconify results
        $allResults = array_merge($localResults, $sourceResults, $iconifyResults['results']);
        
        return [
            'results' => $allResults,
            'total' => count($allResults),
        ];
    }
}
