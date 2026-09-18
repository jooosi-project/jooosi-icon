<?php

declare(strict_types=1);

namespace JooosiIcon\Core\Discovery;

interface Discovery
{
    public function discover(DiscoveryLocation $discoveryLocation, ClassReflector $classReflector): void;

    public function apply(): void;

    public function getItems(): DiscoveryItems;

    public function setItems(DiscoveryItems $discoveryItems): void;
}
