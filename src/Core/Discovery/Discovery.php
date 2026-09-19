<?php

declare (strict_types=1);
namespace JooosiIcon\Core\Discovery;

interface Discovery
{
    public function discover(\JooosiIcon\Core\Discovery\DiscoveryLocation $discoveryLocation, \JooosiIcon\Core\Discovery\ClassReflector $classReflector): void;
    public function apply(): void;
    public function getItems(): \JooosiIcon\Core\Discovery\DiscoveryItems;
    public function setItems(\JooosiIcon\Core\Discovery\DiscoveryItems $discoveryItems): void;
}
