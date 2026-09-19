<?php

declare (strict_types=1);
namespace JooosiIcon\Core\Discovery;

trait IsDiscovery
{
    protected \JooosiIcon\Core\Discovery\DiscoveryItems $discoveryItems;
    public function getItems(): \JooosiIcon\Core\Discovery\DiscoveryItems
    {
        return $this->discoveryItems;
    }
    public function setItems(\JooosiIcon\Core\Discovery\DiscoveryItems $discoveryItems): void
    {
        $this->discoveryItems = $discoveryItems;
    }
}
