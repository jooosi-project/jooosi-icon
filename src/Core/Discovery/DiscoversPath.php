<?php

declare (strict_types=1);
namespace JooosiIcon\Core\Discovery;

interface DiscoversPath
{
    public function discoverPath(\JooosiIcon\Core\Discovery\DiscoveryLocation $discoveryLocation, string $path): void;
}
