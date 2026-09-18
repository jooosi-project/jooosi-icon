<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

// You can do your own things here, e.g. collecting symbols to expose dynamically
// or files to exclude.
// However beware that this file is executed by PHP-Scoper, hence if you are using
// the PHAR it will be loaded by the PHAR. So it is highly recommended to avoid
// to auto-load any code here: it can result in a conflict or even corrupt
// the PHP-Scoper analysis.

$wp_classes   = json_decode(file_get_contents('deploy/php-scoper-wordpress-excludes-master/generated/exclude-wordpress-classes.json'));
$wp_functions = json_decode(file_get_contents('deploy/php-scoper-wordpress-excludes-master/generated/exclude-wordpress-functions.json'));
$wp_constants = json_decode(file_get_contents('deploy/php-scoper-wordpress-excludes-master/generated/exclude-wordpress-constants.json'));

/**
 * @see https://github.com/humbug/php-scoper/blob/main/docs/further-reading.md#polyfills
 */
$polyfillsBootstraps = array_map(
    static fn (SplFileInfo $fileInfo) => $fileInfo->getPathname(),
    iterator_to_array(
        Finder::create()
            ->files()
            ->in(dirname(__DIR__) . '/vendor/symfony/polyfill-*')
            ->name('bootstrap*.php'),
        false,
    ),
);

$polyfillsStubs = array_map(
    static fn (SplFileInfo $fileInfo) => $fileInfo->getPathname(),
    iterator_to_array(
        Finder::create()
            ->files()
            ->in(dirname(__DIR__) . '/vendor/symfony/polyfill-*/Resources/stubs')
            ->name('*.php'),
        false,
    ),
);

return [
    // The prefix configuration. If a non null value is be used, a random prefix
    // will be generated instead.
    //
    // For more see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#prefix
    'prefix' => 'JooosiIconDeps',

    // By default when running php-scoper add-prefix, it will prefix all relevant code found in the current working
    // directory. You can however define which files should be scoped by defining a collection of Finders in the
    // following configuration key.
    //
    // This configuration entry is completely ignored when using Box.
    //
    // For more see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#finders-and-paths
    // 'finders' => [],

    // List of excluded files, i.e. files for which the content will be left untouched.
    // Paths are relative to the configuration file unless if they are already absolute
    //
    // For more see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#patchers
    'exclude-files' => [
        ...$polyfillsBootstraps,
        ...$polyfillsStubs,
    ],

    // When scoping PHP files, there will be scenarios where some of the code being scoped indirectly references the
    // original namespace. These will include, for example, strings or string manipulations. PHP-Scoper has limited
    // support for prefixing such strings. To circumvent that, you can define patchers to manipulate the file to your
    // heart contents.
    //
    // For more see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#patchers
    'patchers' => [
        // Fix Symfony Cache ValueWrapper class (uses © character as class name)
        static function (string $filePath, string $prefix, string $contents): string {
            // Preserve the class name in ValueWrapper.php
            if (str_ends_with($filePath, 'symfony/cache/Traits/ValueWrapper.php')) {
                $contents = preg_replace('/^class\s+[^\s\{]+/m', 'class ©', $contents);
            }
            
            // Update the constant reference in CacheItem.php
            if (str_ends_with($filePath, 'symfony/cache/CacheItem.php')) {
                $contents = str_replace(
                    'private const VALUE_WRAPPER = "\xa9";',
                    'private const VALUE_WRAPPER = \'' . $prefix . '\©\';',
                    $contents
                );
            }
            
            // Fix Redis proxy for PhpRedis 6.3.0+ compatibility
            // Force use of Redis6Proxy instead of Redis5Proxy for all versions >= 6.0
            // Redis5Proxy has incompatible method signatures with PhpRedis 6.3.0+
            if (str_ends_with($filePath, 'symfony/cache/Traits/RedisProxy.php')) {
                $contents = str_replace(
                    'class_alias(6.0 <= (float) phpversion(\'redis\') ? Redis6Proxy::class : Redis5Proxy::class, RedisProxy::class);',
                    'class_alias(Redis6Proxy::class, RedisProxy::class);',
                    $contents
                );
            }
            if (str_ends_with($filePath, 'symfony/cache/Traits/RedisClusterProxy.php')) {
                $contents = str_replace(
                    'class_alias(6.0 <= (float) phpversion(\'redis\') ? RedisCluster6Proxy::class : RedisCluster5Proxy::class, RedisClusterProxy::class);',
                    'class_alias(RedisCluster6Proxy::class, RedisClusterProxy::class);',
                    $contents
                );
            }

            // Fix Symfony DependencyInjection ParameterBag classes left unprefixed by php-scoper
            if (str_contains($filePath, 'symfony/dependency-injection/')) {
                $contents = preg_replace(
                    '/^use Symfony\\\\Component\\\\DependencyInjection\\\\ParameterBag\\\\/m',
                    'use ' . $prefix . '\\Symfony\\Component\\DependencyInjection\\ParameterBag' . '\\',
                    $contents
                ) ?? $contents;

                $contents = preg_replace(
                    '/(?<!' . preg_quote($prefix, '/') . ')\\\\Symfony\\\\Component\\\\DependencyInjection\\\\ParameterBag\\\\/',
                    '\\\\' . $prefix . '\\Symfony\\Component\\DependencyInjection\\ParameterBag' . '\\',
                    $contents
                ) ?? $contents;
            }

            if (str_contains($filePath, 'symfony/dependency-injection/ParameterBag/')) {
                $contents = preg_replace(
                    '/^namespace Symfony\\\\Component\\\\DependencyInjection\\\\ParameterBag;$/m',
                    'namespace ' . $prefix . '\\Symfony\\Component\\DependencyInjection\\ParameterBag;',
                    $contents
                ) ?? $contents;
            }
            
            return $contents;
        },
    ],

    // List of symbols to consider internal i.e. to leave untouched.
    //
    // For more information see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#excluded-symbols
    'exclude-namespaces' => [
        'JooosiIcon',
        'JOOOSI_ICON',
        'WP_CLI',
        'Symfony\Polyfill',

        // Page builders
        'Bricks',
        
        'Breakdance',
        'EssentialElements',

        'Elementor',
        
        'LiveCanvas',

        'Etch',

        'ET',

        // Cache plugins
    ],
    'exclude-classes' => array_merge(
        $wp_classes,
        [
            'JOOOSI_ICON',
            'WP_CLI',
            'WP_CLI_Command',
            'DOMXPath',

            'acf_field',
        ]
    ),
    'exclude-functions' => array_merge(
        $wp_functions,
        [
            // Cache clearing functions

            // Page builder functions
            // Divi declares this function in the global namespace. Keep the
            // function_exists() string literal unprefixed in the scoped build.
            'et_builder_d5_enabled',

            'bricks_is_builder_main',
            'bricks_is_builder_iframe',
            'bricks_render_dynamic_data',

            'acf_get_url',
            'acf_register_field_type',
        ]
    ),
    'exclude-constants' => array_merge(
        $wp_constants,
        [
            // Symfony global constants
            '/^SYMFONY\_[\p{L}_]+$/',

            // WordPress constants
            'WP_CONTENT_DIR',
            'WP_CONTENT_URL',
            'ABSPATH',
            'WPINC',
            'WP_DEBUG_DISPLAY',
            'WPMU_PLUGIN_DIR',
            'WP_PLUGIN_DIR',
            'WP_PLUGIN_URL',
            'WPMU_PLUGIN_URL',
            'MINUTE_IN_SECONDS',
            'HOUR_IN_SECONDS',
            'DAY_IN_SECONDS',
            'MONTH_IN_SECONDS',
            'debugger',

            // Bricks
            'BRICKS_VERSION',

            // Breakdance
            '__BREAKDANCE_VERSION',
            'BREAKDANCE_MODE',

            // Elementor
            'ELEMENTOR_VERSION',

            // LiveCanvas
            'LC_MU_PLUGIN_NAME',

            // Etch
            'ETCH_PLUGIN_FILE',
        ]
    ),

    // List of symbols to expose.
    //
    // For more information see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#exposed-symbols
    'expose-global-constants' => false,
    'expose-global-classes' => false,
    'expose-global-functions' => false,
    'expose-namespaces' => [],
    'expose-classes' => [
        'JOOOSI_ICON',
    ],
    'expose-functions' => [],
    'expose-constants' => [],
];
