<?php

declare(strict_types=1);

namespace JooosiIcon\Services;

use Exception;
use Nabasa\VitePlus\Assets;
use JOOOSI_ICON;
use JooosiIcon\Core\Discovery\Attributes\Service;

use function Nabasa\VitePlus\assets as vite_assets;
use function Nabasa\VitePlus\development_asset_src as vite_generate_development_asset_src;
use function Nabasa\VitePlus\get_manifest as vite_get_manifest;

/**
 * Utility class for managing Vite+ assets.
 */
#[Service]
class ViteService
{
    public const BUILD_DIR = 'dist';
    public const MANIFEST_DIR = JOOOSI_ICON::DIR . self::BUILD_DIR;

    private Assets $assets;

    /**
     * Handles resolved during this request, keyed by entry and handle.
     *
     * @var array<string, array{scripts: list<string>, styles: list<string>}>
     */
    private array $registered_assets = [];

    public function __construct()
    {
        $this->assets = vite_assets(self::MANIFEST_DIR);
    }

    public function enqueue_asset(string $asset_path, array $args = []): void
    {
        $key = $this->asset_key($asset_path, $args);
        $assets = $this->assets->register($asset_path, $args);

        // vp-wp does not return handles when an asset was already registered.
        // Reuse the handles cached by register_asset() so repeated calls still
        // enqueue the script and extracted styles.
        if (is_array($assets) && empty($assets) && isset($this->registered_assets[$key])) {
            $assets = $this->registered_assets[$key];
        }

        if (!is_array($assets)) {
            return;
        }

        $this->registered_assets[$key] = $assets;

        foreach ($assets['scripts'] as $handle) {
            wp_enqueue_script($handle);
        }

        foreach ($assets['styles'] as $handle) {
            wp_enqueue_style($handle);
        }
    }

    /**
     * @return array{scripts: list<string>, styles: list<string>}|null
     */
    public function register_asset(string $asset_path, array $args = []): ?array
    {
        $key = $this->asset_key($asset_path, $args);
        $assets = $this->assets->register($asset_path, $args);

        if (is_array($assets)) {
            $this->registered_assets[$key] = $assets;
        }

        return $assets;
    }

    private function asset_key(string $asset_path, array $args): string
    {
        return md5($asset_path . '\0' . (string) ($args['handle'] ?? ''));
    }

    /**
     * Get manifest data
     *
     * @return object Object containing manifest type and data.
     */
    public function get_manifest(): object
    {
        try {
            return vite_get_manifest(self::MANIFEST_DIR);
        } catch (Exception) {
            return (object) [
                'data' => null,
                'dir' => self::MANIFEST_DIR,
                'is_dev' => false,
            ];
        }
    }

    /**
     * Generate development asset path
     *
     * @param string $asset_path Relative path to the asset.
     * @return string Full URL to the development asset.
     */
    public function generate_development_asset_path(string $asset_path): string
    {
        $manifest = $this->get_manifest();

        if (! $manifest->is_dev || ! is_object($manifest->data)) {
            return JOOOSI_ICON::DIR . ltrim($asset_path, '/');
        }

        $asset_src = vite_generate_development_asset_src($manifest, $asset_path);
        $origin_prefix = untrailingslashit((string) ($manifest->data->origin ?? '')) . '/';

        if (str_starts_with($asset_src, $origin_prefix)) {
            $asset_src = substr($asset_src, strlen($origin_prefix));
        }

        $relative_path = preg_replace('#^(?:\./)+#', '', ltrim($asset_src, '/'));

        return JOOOSI_ICON::DIR . $relative_path;
    }

    public function get_manifest_dir(): string
    {
        return self::MANIFEST_DIR;
    }

    public function is_development(): bool
    {
        return $this->get_manifest()->is_dev;
    }
}
