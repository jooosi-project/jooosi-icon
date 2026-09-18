<?php

/**
 * Plugin constants.
 *
 * @since 1.0.0
 */

declare(strict_types=1);

class JOOOSI_ICON
{
    /**
     * @var string
     */
    public const FILE = __DIR__ . '/jooosi-icon.php';

    /**
     * @var string
     */
    public const DIR = __DIR__ . '/';

    public static function url(): string
    {
        return plugin_dir_url(self::FILE);
    }

    /**
     * @var string
     */
    public const VERSION = '1.0.19';

    /**
     * @var string
     */
    public const WP_OPTION_PREFIX = 'jooosiicon_';

    /**
     * @var string
     */
    public const DB_TABLE_PREFIX = 'jooosiicon_';

    public const TEXT_DOMAIN = 'jooosi-icon';

    /**
     * @var string
     */
    public const REST_NAMESPACE = 'jooosi-icon/v1';

    /**
     * @var string
     */
    public const UPLOAD_DIR = '/jooosi-icon/';

    public const CACHE_DIR = '/jooosi-icon/cache/';

    public const LEGACY_UPLOAD_DIR = '/omni-icon/';

    /**
     * Resolve the writable uploads location and migrate the legacy directory.
     *
     * If the canonical directory is absent and the legacy directory exists, the
     * legacy directory is renamed in place. When that rename is not possible,
     * the legacy directory remains the active fallback so existing icons are not
     * lost or hidden.
     *
     * @param array<string, mixed>|null $uploads Result from wp_upload_dir().
     * @return array{basedir: string, baseurl: string, relative: string, migrated: bool, legacy: bool}
     */
    public static function resolve_upload_location(?array $uploads = null): array
    {
        $uploads ??= wp_upload_dir();

        $base_dir = rtrim((string) ($uploads['basedir'] ?? ''), '/\\');
        $base_url = rtrim((string) ($uploads['baseurl'] ?? ''), '/');
        $canonical_dir = $base_dir . self::UPLOAD_DIR;
        $legacy_dir = $base_dir . self::LEGACY_UPLOAD_DIR;
        $migrated = false;

        if (!is_dir($canonical_dir) && is_dir($legacy_dir)) {
            $migrated = @rename(rtrim($legacy_dir, '/'), rtrim($canonical_dir, '/'));
        }

        if (is_dir($canonical_dir) || $migrated || !is_dir($legacy_dir)) {
            return [
                'basedir' => $canonical_dir,
                'baseurl' => $base_url . self::UPLOAD_DIR,
                'relative' => self::UPLOAD_DIR,
                'migrated' => $migrated,
                'legacy' => false,
            ];
        }

        return [
            'basedir' => $legacy_dir,
            'baseurl' => $base_url . self::LEGACY_UPLOAD_DIR,
            'relative' => self::LEGACY_UPLOAD_DIR,
            'migrated' => false,
            'legacy' => true,
        ];
    }
}
