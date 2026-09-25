<?php

declare(strict_types=1);

namespace JooosiIcon\Services;

use JooosiIcon\Core\Discovery\Attributes\Service;
use JooosiIcon\Core\Discovery\Attributes\Hook;

/**
 * Keeps one random key for the browser's persistent icon cache.
 */
#[Service]
class IconCacheKeyService
{
    private const OPTION_NAME = 'jooosi_icon_cache_key';

    public function get_key(): string
    {
        $key = get_option(self::OPTION_NAME);

        if (!is_string($key) || $key === '') {
            $key = wp_generate_uuid4();
            add_option(self::OPTION_NAME, $key, '', false);

            // Another request may have initialized the key at the same time.
            $storedKey = get_option(self::OPTION_NAME);
            if (is_string($storedKey) && $storedKey !== '') {
                $key = $storedKey;
            }
        }

        return $key;
    }

    /** Replace the key after icon data or its server cache changes. */
    public function regenerate(): void
    {
        update_option(self::OPTION_NAME, wp_generate_uuid4(), false);
    }

    /** Add the cache key to regular frontend and admin document heads. */
    #[Hook('wp_head', priority: 1)]
    #[Hook('admin_head', priority: 1)]
    public function output_meta(): void
    {
        printf(
            '<meta name="jooosi-icon-cache-key" content="%s" />' . "\n",
            esc_attr($this->get_key()),
        );
    }

    /**
     * Add the key to an isolated document whose head hooks were not rendered,
     * such as Gutenberg's editor canvas iframe.
     *
     * @param list<string> $scriptHandles
     */
    public function add_inline_meta(array $scriptHandles): void
    {
        $key = wp_json_encode($this->get_key());
        $metaName = wp_json_encode('jooosi-icon-cache-key');
        if (!is_string($key) || !is_string($metaName)) {
            return;
        }

        $script = sprintf(
            '(()=>{const meta=document.createElement("meta");meta.name=%s;meta.content=%s;document.head.append(meta);})();',
            $metaName,
            $key,
        );
        foreach (array_unique($scriptHandles) as $handle) {
            wp_add_inline_script($handle, $script, 'before');
        }
    }
}
