<?php

declare (strict_types=1);
namespace JooosiIcon\Core\Discovery;

final class DiscoveryLocation
{
    public function __construct(public string $namespace, public string $path)
    {
    }
    /**
     * Convert a file path to a fully qualified class name
     */
    public function toClassName(string $filePath): string
    {
        $realPath = realpath($this->path);
        if (\false === $realPath) {
            $realPath = $this->path;
        }
        // Try to create a PSR-4 compliant class name from the path
        return str_replace([$realPath, '/', '\\\\', '.php'], [rtrim($this->namespace, '\\'), '\\', '\\', ''], $filePath);
    }
    /**
     * Check if this location is in the vendor directory
     */
    public function isVendor(): bool
    {
        return str_contains($this->path, '/vendor/') || str_contains($this->path, '\vendor\\');
    }
}
