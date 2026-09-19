<?php

declare (strict_types=1);
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

use JooosiIcon\Core\Icon\Exception\HttpClientNotInstalledException;
use JooosiIcon\Core\Icon\Exception\IconNotFoundException;
use JooosiIcon\Core\Icon\Icon;
use JooosiIcon\Core\Icon\Iconify;
use JooosiIcon\Core\Icon\IconRegistryInterface;
/**
 * Icon registry for fetching icons from Iconify API on demand.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class IconifyOnDemandRegistry implements IconRegistryInterface
{
    public function __construct(private Iconify $iconify, private ?array $prefixAliases = [])
    {
    }
    public function get(string $name): Icon
    {
        if (2 !== \count($parts = explode(':', $name))) {
            throw new IconNotFoundException(\sprintf('The icon name "%s" is not valid.', $name));
        }
        [$prefix, $icon] = $parts;
        try {
            return $this->iconify->fetchIcon($this->prefixAliases[$prefix] ?? $prefix, $icon);
        } catch (HttpClientNotInstalledException $e) {
            throw new IconNotFoundException($e->getMessage());
        }
    }
}
