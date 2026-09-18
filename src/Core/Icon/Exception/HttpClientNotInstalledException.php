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

namespace JooosiIcon\Core\Icon\Exception;

/**
 * Exception thrown when HTTP client is not available.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class HttpClientNotInstalledException extends \LogicException
{
}