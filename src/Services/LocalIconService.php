<?php

declare (strict_types=1);
namespace JooosiIcon\Services;

use JooosiIconDeps\enshrined\svgSanitize\Sanitizer;
use JOOOSI_ICON;
use JooosiIcon\Core\Discovery\Attributes\Service;
use JooosiIconDeps\Symfony\Component\Cache\Adapter\FilesystemAdapter;
use JooosiIconDeps\Symfony\Component\Mime\MimeTypes;
use JooosiIconDeps\Symfony\Contracts\Cache\ItemInterface;
use JooosiIcon\Core\Icon\Exception\IconNotFoundException;
use JooosiIcon\Core\Icon\Registry\LocalSvgIconRegistry;
use JooosiIcon\Core\Icon\IconRegistryInterface;
/**
 * Service for managing local uploaded SVG icons.
 * 
 * Supports multiple icon sets through subdirectories:
 * - Icons in root directory use prefix "local:" (e.g., local:icon-name)
 * - Icons in subdirectories use subdirectory name as prefix (e.g., brand:logo)
 * 
 * Directory structure:
 * wp-content/uploads/jooosi-icon/local/
 * ├── icon1.svg              -> local:icon1
 * ├── icon2.svg              -> local:icon2
 * ├── brand/
 * │   ├── logo.svg           -> brand:logo
 * │   └── icon.svg           -> brand:icon
 * └── social/
 *     ├── facebook.svg       -> social:facebook
 *     └── twitter.svg        -> social:twitter
 */
#[Service]
class LocalIconService
{
    private Sanitizer $sanitizer;
    private string $upload_dir;
    private string $upload_url;
    private IconRegistryInterface $registry;
    private FilesystemAdapter $cache;
    public function __construct()
    {
        $this->sanitizer = new Sanitizer();
        // SVG is embedded as HTML, so an XML declaration would be parsed as a
        // bogus comment when the markup is inserted with innerHTML.
        $this->sanitizer->removeXMLTag(\true);
        // Set up local upload directory
        $wp_upload_dir = wp_upload_dir();
        $storage = JOOOSI_ICON::resolve_upload_location($wp_upload_dir);
        $this->upload_dir = $storage['basedir'] . 'local';
        $this->upload_url = $storage['baseurl'] . 'local';
        // Ensure the directory exists
        $this->ensure_directory_exists($this->upload_dir);
        // Initialize cache
        $cache_dir = $storage['basedir'] . 'cache';
        wp_mkdir_p($cache_dir);
        $this->cache = new FilesystemAdapter('local_icons', 300, $cache_dir);
        // Create local icon registry for custom uploaded icons
        $this->registry = new LocalSvgIconRegistry($this->get_upload_dir(), ['local' => $this->upload_dir]);
    }
    public function get_registry(): IconRegistryInterface
    {
        return $this->registry;
    }
    /**
     * Ensure directory exists with proper permissions and .htaccess
     */
    private function ensure_directory_exists(string $dir): void
    {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
    }
    /**
     * Get the upload directory path
     */
    public function get_upload_dir(): string
    {
        return $this->upload_dir;
    }
    /**
     * Get the upload directory URL
     */
    public function get_upload_url(): string
    {
        return $this->upload_url;
    }
    /**
     * Clear all caches
     */
    public function clear_cache(): void
    {
        $this->cache->clear();
    }
    /**
     * Get cache key based on directory modification time for auto-invalidation
     * This ensures cache is invalidated when files are added/removed directly to the directory
     * 
     * Note: The parent directory mtime changes when:
     * - Files are added/removed in the directory
     * - Subdirectories are added/removed
     * 
     * Subdirectory mtime changes when:
     * - Files are added/removed in the subdirectory
     */
    private function get_cache_key(string $prefix, string $dir): string
    {
        if (!is_dir($dir)) {
            return $prefix . '_0';
        }
        // Parent directory mtime changes when subdirectories/files are added or removed
        $max_mtime = filemtime($dir);
        // Check all subdirectories for modifications (icons added/removed within subdirs)
        $subdirs = glob($dir . '/*', \GLOB_ONLYDIR | \GLOB_NOSORT);
        if ($subdirs !== \false) {
            foreach ($subdirs as $subdir) {
                $subdir_mtime = filemtime($subdir);
                if ($subdir_mtime > $max_mtime) {
                    $max_mtime = $subdir_mtime;
                }
            }
        }
        return "{$prefix}_{$max_mtime}";
    }
    /**
     * Upload and sanitize an SVG file
     *
     * @param array<string, mixed> $file The uploaded file from $_FILES
     * @param string|null $icon_set Optional icon set (subdirectory) name
     * @return array{success: bool, message: string, filename?: string, url?: string, path?: string, icon_name?: string}
     */
    public function upload_svg(array $file, ?string $icon_set = null): array
    {
        // Validate file type
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => \false, 'message' => __('Invalid file upload', 'jooosi-icon')];
        }
        // Check file extension
        $filename = $file['name'];
        $file_ext = strtolower(pathinfo($filename, \PATHINFO_EXTENSION));
        if ($file_ext !== 'svg') {
            return ['success' => \false, 'message' => __('Only SVG files are allowed', 'jooosi-icon')];
        }
        // Check file size (limit to 1MB to prevent memory issues)
        $max_size = 1024 * 1024;
        // 1MB
        if (isset($file['size']) && $file['size'] > $max_size) {
            return ['success' => \false, 'message' => __('File size exceeds maximum allowed size (1MB)', 'jooosi-icon')];
        }
        // Read file content
        $svg_content = file_get_contents($file['tmp_name']);
        if ($svg_content === \false) {
            return ['success' => \false, 'message' => __('Failed to read file content', 'jooosi-icon')];
        }
        // Sanitize SVG
        $clean_svg = $this->sanitizer->sanitize($svg_content);
        if ($clean_svg === \false || empty($clean_svg)) {
            return ['success' => \false, 'message' => __('Invalid or malicious SVG content detected', 'jooosi-icon')];
        }
        // Determine target directory
        $target_dir = $this->upload_dir;
        if ($icon_set !== null && $icon_set !== '') {
            $icon_set = $this->sanitize_icon_set_name($icon_set);
            $target_dir .= '/' . $icon_set;
            $this->ensure_directory_exists($target_dir);
        }
        // Generate unique filename
        $safe_filename = $this->generate_unique_filename($filename, $target_dir);
        $file_path = $target_dir . '/' . $safe_filename;
        // Save sanitized SVG
        if (file_put_contents($file_path, $clean_svg) === \false) {
            return ['success' => \false, 'message' => __('Failed to save file', 'jooosi-icon')];
        }
        // Invalidate cache after successful upload
        $this->clear_cache();
        // Build icon name for reference
        $icon_name_base = pathinfo($safe_filename, \PATHINFO_FILENAME);
        $icon_name = $icon_set ? "{$icon_set}:{$icon_name_base}" : "local:{$icon_name_base}";
        return ['success' => \true, 'message' => __('Icon uploaded successfully', 'jooosi-icon'), 'filename' => $safe_filename, 'url' => $this->get_icon_url($safe_filename, $icon_set), 'path' => $file_path, 'icon_name' => $icon_name];
    }
    /**
     * Sanitize icon set name (for subdirectory)
     */
    private function sanitize_icon_set_name(string $name): string
    {
        // Convert to lowercase and allow only alphanumeric and hyphens
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9-]/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);
        return trim($name, '-');
    }
    /**
     * Generate a unique filename
     */
    private function generate_unique_filename(string $original_filename, string $target_dir): string
    {
        $basename = pathinfo($original_filename, \PATHINFO_FILENAME);
        $extension = pathinfo($original_filename, \PATHINFO_EXTENSION);
        // Sanitize filename - Symfony UX Icons expects slug format: [a-z0-9-]+
        $basename = sanitize_file_name($basename);
        $basename = strtolower($basename);
        $basename = preg_replace('/[^a-z0-9-]/', '-', $basename);
        $basename = preg_replace('/-+/', '-', $basename);
        $basename = trim($basename, '-');
        // Make unique
        $filename = $basename . '.' . $extension;
        $counter = 1;
        while (file_exists($target_dir . '/' . $filename)) {
            $filename = $basename . '-' . $counter . '.' . $extension;
            $counter++;
        }
        return $filename;
    }
    /**
     * Get all available icon sets (subdirectories).
     * 
     * @return array<string, array{name: string, total: int, samples: array<int, string>}>
     */
    public function get_icon_sets(): array
    {
        $cache_key = $this->get_cache_key('icon_sets', $this->upload_dir);
        return $this->cache->get($cache_key, function (ItemInterface $item) {
            $item->expiresAfter(300);
            // 5 minutes
            $sets = [];
            // Add "local" set for root directory icons
            $root_icons = $this->scan_directory($this->upload_dir, \false);
            if (!empty($root_icons)) {
                // Return ALL icons as samples instead of just 6
                $sets['local'] = ['name' => 'Local Icons', 'total' => count($root_icons), 'samples' => array_map(static fn($file) => pathinfo(basename($file), \PATHINFO_FILENAME), $root_icons)];
            }
            // Scan for subdirectories
            if (!is_dir($this->upload_dir)) {
                return $sets;
            }
            $subdirs = glob($this->upload_dir . '/*', \GLOB_ONLYDIR);
            if ($subdirs === \false) {
                return $sets;
            }
            foreach ($subdirs as $subdir) {
                $prefix = basename($subdir);
                $icons = $this->scan_directory($subdir, \false);
                // Return ALL icons as samples instead of just 6
                $sets[$prefix] = ['name' => ucfirst($prefix), 'total' => count($icons), 'samples' => array_map(static fn($file) => pathinfo(basename($file), \PATHINFO_FILENAME), $icons)];
            }
            return $sets;
        });
    }
    /**
     * Get all icons from a specific icon set
     *
     * @param string|null $icon_set Icon set prefix (null for "local")
     * @return array<int, array{name: string, filename: string, icon_name: string, url: string, path: string}>
     */
    public function get_icons_by_set(?string $icon_set = null): array
    {
        $prefix = $icon_set ?? 'local';
        $scan_dir = $icon_set ? $this->upload_dir . '/' . $icon_set : $this->upload_dir;
        if (!is_dir($scan_dir)) {
            return [];
        }
        $cache_key = $this->get_cache_key("icons_set_{$prefix}", $scan_dir);
        return $this->cache->get($cache_key, function (ItemInterface $item) use ($scan_dir, $prefix, $icon_set) {
            $item->expiresAfter(300);
            // 5 minutes
            $files = $this->scan_directory($scan_dir, \false);
            $icons = [];
            foreach ($files as $file) {
                $filename = basename($file);
                $name = pathinfo($filename, \PATHINFO_FILENAME);
                $icons[] = ['name' => $name, 'filename' => $filename, 'icon_name' => "{$prefix}:{$name}", 'url' => $this->get_icon_url($filename, $icon_set), 'path' => $file];
            }
            return $icons;
        });
    }
    /**
     * Get all local icons (from all sets)
     *
     * @return array<int, array{name: string, filename: string, icon_name: string, icon_set: string, url: string, path: string}>
     */
    public function get_all_icons(): array
    {
        $cache_key = $this->get_cache_key('all_icons', $this->upload_dir);
        return $this->cache->get($cache_key, function (ItemInterface $item) {
            $item->expiresAfter(300);
            // 5 minutes
            $all_icons = [];
            // Get icons from root directory (local set) - using cached result
            $local_icons = $this->get_icons_by_set(null);
            foreach ($local_icons as $icon) {
                $icon['icon_set'] = 'local';
                $all_icons[] = $icon;
            }
            // Get icons from subdirectories - using cached result
            $sets = $this->get_icon_sets();
            foreach ($sets as $prefix => $set) {
                if ($prefix === 'local') {
                    continue;
                    // Already processed
                }
                // This will use cached result from get_icons_by_set
                $set_icons = $this->get_icons_by_set($prefix);
                foreach ($set_icons as $icon) {
                    $icon['icon_set'] = $prefix;
                    $all_icons[] = $icon;
                }
            }
            return $all_icons;
        });
    }
    /**
     * Get all local icons as a generator (memory-efficient streaming)
     * Use this for large icon sets to avoid memory spikes
     *
     * @return \Generator<array{name: string, filename: string, icon_name: string, icon_set: string, url: string, path: string}>
     */
    public function get_all_icons_lazy(): \Generator
    {
        // Get icons from root directory (local set)
        $local_icons = $this->get_icons_by_set(null);
        foreach ($local_icons as $icon) {
            $icon['icon_set'] = 'local';
            yield $icon;
        }
        // Get icons from subdirectories
        $sets = $this->get_icon_sets();
        foreach ($sets as $prefix => $set) {
            if ($prefix === 'local') {
                continue;
                // Already processed
            }
            $set_icons = $this->get_icons_by_set($prefix);
            foreach ($set_icons as $icon) {
                $icon['icon_set'] = $prefix;
                yield $icon;
            }
        }
    }
    /**
     * Scan directory for SVG files
     *
     * @param string $dir Directory to scan
     * @param bool $recursive Scan subdirectories
     * @return array<int, string> Array of file paths
     */
    private function scan_directory(string $dir, bool $recursive = \false): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        // Use GLOB_NOSORT for better performance when order doesn't matter
        $pattern = $recursive ? $dir . '/**/*.svg' : $dir . '/*.svg';
        $files = glob($pattern, \GLOB_NOSORT);
        return $files !== \false ? $files : [];
    }
    /**
     * Scan directory for SVG files as a generator (memory-efficient)
     *
     * @param string $dir Directory to scan
     * @param bool $recursive Scan subdirectories
     * @return \Generator<string> Generator yielding file paths
     */
    private function scan_directory_lazy(string $dir, bool $recursive = \false): \Generator
    {
        if (!is_dir($dir)) {
            return;
        }
        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'svg') {
                    yield $file->getPathname();
                }
            }
        } else {
            $iterator = new \DirectoryIterator($dir);
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'svg') {
                    yield $file->getPathname();
                }
            }
        }
    }
    /**
     * Get icon URL
     */
    private function get_icon_url(string $filename, ?string $icon_set = null): string
    {
        $url = $this->upload_url;
        if ($icon_set !== null && $icon_set !== '') {
            $url .= '/' . $icon_set;
        }
        return $url . '/' . $filename;
    }
    /**
     * Delete a local icon
     *
     * @param string $icon_name Icon name in format "prefix:name" or just "name"
     * @return array{success: bool, message: string}
     */
    public function delete_icon(string $icon_name): array
    {
        // Parse icon name
        if (str_contains($icon_name, ':')) {
            [$prefix, $name] = explode(':', $icon_name, 2);
        } else {
            $prefix = 'local';
            $name = $icon_name;
        }
        // Determine file path
        $filename = $name . '.svg';
        if ($prefix === 'local') {
            $file_path = $this->upload_dir . '/' . $filename;
        } else {
            $file_path = $this->upload_dir . '/' . $prefix . '/' . $filename;
        }
        if (!file_exists($file_path)) {
            return ['success' => \false, 'message' => __('Icon not found', 'jooosi-icon')];
        }
        if (!wp_delete_file($file_path)) {
            return ['success' => \false, 'message' => __('Failed to delete icon', 'jooosi-icon')];
        }
        // Invalidate cache after successful deletion
        $this->clear_cache();
        return ['success' => \true, 'message' => __('Icon deleted successfully', 'jooosi-icon')];
    }
    /**
     * Get icon content by icon name
     *
     * @param string $icon_name Icon name in format "prefix:name"
     * @return string|null The SVG content or null if not found
     */
    public function get_icon_content(string $icon_name): ?string
    {
        if (!str_contains($icon_name, ':')) {
            return null;
        }
        [$prefix, $name] = explode(':', $icon_name, 2);
        $filename = $name . '.svg';
        if ($prefix === 'local') {
            $file_path = $this->upload_dir . '/' . $filename;
        } else {
            $file_path = $this->upload_dir . '/' . $prefix . '/' . $filename;
        }
        if (!file_exists($file_path)) {
            return null;
        }
        $content = file_get_contents($file_path);
        return $content === \false ? null : $content;
    }
    /**
     * Search icons by name query
     *
     * @param string $query Search query (can include prefix)
     * @return array<int, array{name: string, prefix: string}>
     */
    public function search_icons(string $query): array
    {
        // If prefix is specified, search within that set
        if (str_contains($query, ':')) {
            [$prefix, $search_term] = explode(':', $query, 2) + ['', ''];
            if ('' === $prefix || '' === $search_term) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output
                throw new IconNotFoundException(\sprintf('The icon name "%s" is not valid.', esc_html($query)));
            }
            // Search in specific icon set - uses cached icons
            $icons = $prefix === 'local' ? $this->get_icons_by_set(null) : $this->get_icons_by_set($prefix);
            // Use array_filter for better performance
            return array_values(array_map(static fn($icon) => ['name' => $icon['name'], 'prefix' => $prefix], array_filter($icons, static fn($icon) => str_contains($icon['name'], $search_term))));
        }
        // Search across all icon sets - uses cached icons
        $all_icons = $this->get_all_icons();
        return array_values(array_map(static fn($icon) => ['name' => $icon['name'], 'prefix' => $icon['icon_set']], array_filter($all_icons, static fn($icon) => str_contains($icon['name'], $query))));
    }
    /**
     * Move icon to a different set
     *
     * @param string $icon_name Icon name in format "prefix:name"
     * @param string|null $target_set Target set name (null for "local" set)
     * @return array{success: bool, message: string}
     */
    public function move_icon(string $icon_name, ?string $target_set): array
    {
        // Parse icon name
        if (str_contains($icon_name, ':')) {
            [$prefix, $name] = explode(':', $icon_name, 2);
        } else {
            $prefix = 'local';
            $name = $icon_name;
        }
        // Determine source file path
        $filename = $name . '.svg';
        if ($prefix === 'local') {
            $source_path = $this->upload_dir . '/' . $filename;
        } else {
            $source_path = $this->upload_dir . '/' . $prefix . '/' . $filename;
        }
        if (!file_exists($source_path)) {
            return ['success' => \false, 'message' => __('Icon not found', 'jooosi-icon')];
        }
        // Determine target directory
        if ($target_set === null || $target_set === '' || $target_set === 'local') {
            $target_dir = $this->upload_dir;
            $new_prefix = 'local';
        } else {
            $target_set = $this->sanitize_icon_set_name($target_set);
            $target_dir = $this->upload_dir . '/' . $target_set;
            $new_prefix = $target_set;
            $this->ensure_directory_exists($target_dir);
        }
        // Check if already in target set
        if ($prefix === $new_prefix) {
            return ['success' => \false, 'message' => __('Icon is already in this set', 'jooosi-icon')];
        }
        $target_path = $target_dir . '/' . $filename;
        // Check if target already exists
        if (file_exists($target_path)) {
            return ['success' => \false, 'message' => __('An icon with this name already exists in the target set', 'jooosi-icon')];
        }
        // Move file using WordPress filesystem API
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once \ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        if (!$wp_filesystem->move($source_path, $target_path)) {
            return ['success' => \false, 'message' => __('Failed to move icon', 'jooosi-icon')];
        }
        // Clear cache after successful move
        $this->clear_cache();
        return ['success' => \true, 'message' => __('Icon moved successfully', 'jooosi-icon')];
    }
    /**
     * Create a new icon set (directory)
     *
     * @param string $set_name Set name
     * @return array{success: bool, message: string}
     */
    public function create_set(string $set_name): array
    {
        // Sanitize name
        $set_name = $this->sanitize_icon_set_name($set_name);
        if (empty($set_name)) {
            return ['success' => \false, 'message' => __('Invalid set name', 'jooosi-icon')];
        }
        if ($set_name === 'local') {
            return ['success' => \false, 'message' => __('Cannot use "local" as a set name', 'jooosi-icon')];
        }
        $set_dir = $this->upload_dir . '/' . $set_name;
        if (file_exists($set_dir)) {
            return ['success' => \false, 'message' => __('A set with this name already exists', 'jooosi-icon')];
        }
        // Create directory
        if (!wp_mkdir_p($set_dir)) {
            return ['success' => \false, 'message' => __('Failed to create icon set directory', 'jooosi-icon')];
        }
        // Clear cache after successful creation
        $this->clear_cache();
        return ['success' => \true, 'message' => __('Icon set created successfully', 'jooosi-icon')];
    }
    /**
     * Rename an icon set (directory)
     *
     * @param string $old_name Current set name
     * @param string $new_name New set name
     * @return array{success: bool, message: string}
     */
    public function rename_set(string $old_name, string $new_name): array
    {
        // Cannot rename the "local" set
        if ($old_name === 'local') {
            return ['success' => \false, 'message' => __('Cannot rename the default "local" set', 'jooosi-icon')];
        }
        // Sanitize names
        $old_name = $this->sanitize_icon_set_name($old_name);
        $new_name = $this->sanitize_icon_set_name($new_name);
        if ($old_name === $new_name) {
            return ['success' => \false, 'message' => __('New name must be different from current name', 'jooosi-icon')];
        }
        if ($new_name === 'local') {
            return ['success' => \false, 'message' => __('Cannot use "local" as a set name', 'jooosi-icon')];
        }
        $old_dir = $this->upload_dir . '/' . $old_name;
        $new_dir = $this->upload_dir . '/' . $new_name;
        if (!is_dir($old_dir)) {
            return ['success' => \false, 'message' => __('Icon set not found', 'jooosi-icon')];
        }
        if (file_exists($new_dir)) {
            return ['success' => \false, 'message' => __('A set with this name already exists', 'jooosi-icon')];
        }
        // Rename directory using WordPress filesystem API
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once \ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        if (!$wp_filesystem->move($old_dir, $new_dir)) {
            return ['success' => \false, 'message' => __('Failed to rename icon set', 'jooosi-icon')];
        }
        // Clear cache after successful rename
        $this->clear_cache();
        return ['success' => \true, 'message' => __('Icon set renamed successfully', 'jooosi-icon')];
    }
    /**
     * Detect MIME type of a file
     *
     * @param string $path File path
     * @return string|null MIME type or null if not detectable
     */
    public function detect_mime(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $mimeTypes = new MimeTypes();
        return $mimeTypes->guessMimeType($path);
    }
}
