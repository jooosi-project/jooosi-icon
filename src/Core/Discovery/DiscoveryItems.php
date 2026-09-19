<?php

declare (strict_types=1);
namespace JooosiIcon\Core\Discovery;

use ArrayIterator;
use IteratorAggregate;
use Traversable;
final class DiscoveryItems implements IteratorAggregate
{
    /** @var array<string, array<mixed>> */
    private array $items = [];
    public function add(\JooosiIcon\Core\Discovery\DiscoveryLocation $discoveryLocation, mixed $item): void
    {
        $locationKey = $discoveryLocation->namespace . '|' . $discoveryLocation->path;
        if (!isset($this->items[$locationKey])) {
            $this->items[$locationKey] = [];
        }
        $this->items[$locationKey][] = $item;
    }
    /**
     * @return array<mixed>
     */
    public function all(): array
    {
        $allItems = [];
        foreach ($this->items as $item) {
            $allItems = array_merge($allItems, $item);
        }
        return $allItems;
    }
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->all());
    }
    /**
     * @return array<string, array<mixed>>
     */
    public function getByLocation(): array
    {
        return $this->items;
    }
    public function isEmpty(): bool
    {
        return [] === $this->items;
    }
    public function count(): int
    {
        return count($this->all());
    }
}
