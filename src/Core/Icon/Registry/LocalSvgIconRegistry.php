<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Modified for JooosiIcon WordPress plugin.
 */

namespace JooosiIcon\Core\Icon\Registry;

use JooosiIcon\Core\Icon\Exception\IconNotFoundException;
use JooosiIcon\Core\Icon\Icon;
use JooosiIcon\Core\Icon\IconRegistryInterface;

/**
 * Icon registry for local SVG files with support for icon sets via directory structure.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class LocalSvgIconRegistry implements IconRegistryInterface
{
    /**
     * @param array<string, string> $iconSetPaths
     * @param array<string, string> $iconAliases Full icon-name aliases.
     */
    public function __construct(
        private readonly string $iconDir,
        private readonly array $iconSetPaths = [],
        private readonly array $iconAliases = [],
    ) {
    }

    public function get(string $name): Icon
    {
        $name = $this->iconAliases[$name] ?? $name;

        if (str_contains($name, ':')) {
            [$prefix, $icon] = explode(':', $name, 2) + ['', ''];
            if ('' === $prefix || '' === $icon) {
                throw new IconNotFoundException(\sprintf('The icon name "%s" is not valid.', $name));
            }

            if ($prefixPath = $this->iconSetPaths[$prefix] ?? null) {
                if (!file_exists($filename = $prefixPath.'/'.str_replace(':', '/', $icon).'.svg')) {
                    throw new IconNotFoundException(\sprintf('The icon "%s" (%s) does not exist.', $name, $filename));
                }

                return Icon::fromFile($filename);
            }
        }

        $filepath = str_replace(':', '/', $name).'.svg';
        if (file_exists($filename = $this->iconDir.'/'.$filepath)) {
            return Icon::fromFile($filename);
        }

        throw new IconNotFoundException(\sprintf('The icon "%s" (%s) does not exist.', $name, $filename));
    }

    public function has(string $name): bool
    {
        try {
            $this->get($name);

            return true;
        } catch (IconNotFoundException) {
            return false;
        }
    }

    public function add(string $name, string $svg): void
    {
        $filename = \sprintf('%s/%s.svg', $this->iconDir, $name);

        // Create directory if it doesn't exist
        $directory = dirname($filename);
        if (!is_dir($directory)) {
            wp_mkdir_p($directory);
        }

        // Write the SVG file
        file_put_contents($filename, $svg);
    }
}
