<?php

/**
 * Plugin constants.
 *
 * @since 1.0.0
 */

declare(strict_types=1);

class OMNI_ICON
{
    /**
     * @var string
     */
    public const FILE = __DIR__ . '/omni-icon.php';

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
    public const WP_OPTION_PREFIX = 'omniicon_';

    /**
     * @var string
     */
    public const DB_TABLE_PREFIX = 'omniicon_';

    /**
     * The text domain should use the literal string 'omni-icon' as the text domain.
     * This constant is used for reference only and should not be used as the actual text domain.
     *
     * @var string
     */
    public const TEXT_DOMAIN = 'omni-icon';

    /**
     * @var string
     */
    public const REST_NAMESPACE = 'omni-icon/v1';

    /**
     * @var string
     */
    public const UPLOAD_DIR = '/omni-icon/';

    public const CACHE_DIR = '/omni-icon/cache/';
}
