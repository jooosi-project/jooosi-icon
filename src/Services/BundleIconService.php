<?php

declare(strict_types=1);

namespace JooosiIcon\Services;

use JooosiIcon\Core\Discovery\Attributes\Service;

/**
 * Backward-compatible alias for the renamed file-based icon source service.
 *
 * @deprecated Use IconSourceService instead.
 */
#[Service]
class BundleIconService extends IconSourceService
{
}
