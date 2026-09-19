<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JooosiIconDeps\Symfony\Component\HttpClient\Internal;

use JooosiIconDeps\Http\Client\HttpClient;
use JooosiIconDeps\Http\Message\RequestFactory;
use JooosiIconDeps\Http\Message\StreamFactory;
use JooosiIconDeps\Http\Message\UriFactory;
if (interface_exists(RequestFactory::class)) {
    /**
     * @internal
     *
     * @deprecated since Symfony 6.3
     */
    interface LegacyHttplugInterface extends HttpClient, RequestFactory, StreamFactory, UriFactory
    {
    }
} else {
    /**
     * @internal
     *
     * @deprecated since Symfony 6.3
     */
    interface LegacyHttplugInterface extends HttpClient
    {
    }
}
