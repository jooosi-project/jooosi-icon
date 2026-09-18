<?php

declare(strict_types=1);

namespace JooosiIcon\Core\Discovery;

use Psr\Log\LoggerInterface;
use Throwable;

final class DirectoryScanner
{
    /**
     * @param array<Discovery> $discoveries
     * @param array<string> $excludedPaths
     */
    public function __construct(
        private array $discoveries,
        private LoggerInterface $logger,
        private array $excludedPaths = [],
    ) {
    }

    /**
     * Recursively scan a directory and apply discoveries to all files
     */
    public function scan(DiscoveryLocation $discoveryLocation, string $path): void
    {
        $input = realpath($path);

        // Make sure the path is valid
        if (false === $input) {
            return;
        }

        // Directories are scanned recursively
        if (is_dir($input)) {
            // Skip certain directories
            if ($this->shouldSkipDirectory($input)) {
                return;
            }

            // Skip excluded paths
            if ($this->isPathExcluded($input)) {
                return;
            }

            $items = scandir($input, SCANDIR_SORT_NONE);
            if (false === $items) {
                return;
            }

            foreach ($items as $item) {
                // Skip `.` and `..`
                if ('.' === $item || '..' === $item) {
                    continue;
                }

                // Scan all files and folders within this directory
                $this->scan($discoveryLocation, sprintf('%s/%s', $input, $item));
            }

            return;
        }

        // At this point, we have a single file
        $pathInfo = pathinfo($input);
        $extension = $pathInfo['extension'] ?? null;
        $fileName = $pathInfo['filename'] ?? null;

        // If this is a PHP file starting with an uppercase letter, we assume it's a class
        if ('php' === $extension && null !== $fileName && ucfirst($fileName) === $fileName) {
            // If namespace is empty, extract from file
            if ('' === $discoveryLocation->namespace) {
                $className = $this->extractClassNameFromFile($input);
            } else {
                $className = $discoveryLocation->toClassName($input);
            }

            if (null === $className) {
                return;
            }

            // Try to create a class reflector
            $classReflector = null;
            try {
                // For non-vendor locations, require the file first
                // This is necessary for files that don't have composer autoload
                if (! $discoveryLocation->isVendor() && ! class_exists($className, false)) {
                    require_once $input;
                }

                if (class_exists($className)) {
                    $classReflector = new ClassReflector($className);
                }
            } catch (Throwable $e) {
                $this->logger->error('Discovery error for class', [
                    'component' => 'DirectoryScanner',
                    'className' => $className,
                    'file' => $input,
                    'exception' => $e,
                ]);
            }

            // Pass to discoveries
            if ($classReflector instanceof \JooosiIcon\Core\Discovery\ClassReflector) {
                foreach ($this->discoveries as $discovery) {
                    $discovery->discover($discoveryLocation, $classReflector);
                }

                return;
            }
        }

        // If not a class, check if any discovery can handle paths
        foreach ($this->discoveries as $discovery) {
            if ($discovery instanceof DiscoversPath) {
                $discovery->discoverPath($discoveryLocation, $input);
            }
        }
    }

    /**
     * Check whether a given directory should be skipped
     */
    private function shouldSkipDirectory(string $path): bool
    {
        $directory = pathinfo($path, PATHINFO_BASENAME);

        // Skip hidden directories (starting with .)
        if (str_starts_with($directory, '.')) {
            return true;
        }

        return 'node_modules' === $directory || 'vendor' === $directory;
    }

    /**
     * Extract the fully qualified class name from a PHP file
     */
    private function extractClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if (false === $content) {
            return null;
        }

        $namespace = '';
        $className = '';

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches)) {
            $namespace = trim($namespaceMatches[1]);
        }

        // Extract class name
        $fileBasename = pathinfo($filePath, PATHINFO_FILENAME);
        if (preg_match('/\b(?:class|interface|trait|enum)\s+' . preg_quote($fileBasename, '/') . '\b/', $content)) {
            $className = $fileBasename;
        }

        if ('' === $className) {
            return null;
        }

        return '' === $namespace ? $className : $namespace . '\\' . $className;
    }

    private function isPathExcluded(string $path): bool
    {
        foreach ($this->excludedPaths as $excludedPath) {
            $realExcludedPath = realpath($excludedPath);
            if ($realExcludedPath === false) {
                continue;
            }

            // Check if the path matches or is a subdirectory of an excluded path
            if ($path === $realExcludedPath || str_starts_with($path . '/', $realExcludedPath . '/')) {
                return true;
            }
        }

        return false;
    }
}
