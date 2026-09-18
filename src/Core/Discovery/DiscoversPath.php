<?php

declare(strict_types=1);

namespace JooosiIcon\Core\Discovery;

interface DiscoversPath
{
    public function discoverPath(DiscoveryLocation $discoveryLocation, string $path): void;
}
