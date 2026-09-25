<?php

declare (strict_types=1);
namespace JooosiIcon\Api\Admin;

use JooosiIcon\Core\Discovery\Attributes\Controller;
use JooosiIcon\Core\Discovery\Attributes\Route;
use JooosiIcon\Services\IconCacheKeyService;
use JooosiIcon\Services\LocalIconService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
#[Controller(namespace: 'jooosi-icon/v1', prefix: 'admin/local-icon')]
final class LocalIconController
{
    /**
     * @var LocalIconService
     */
    private LocalIconService $localIconService;
    private IconCacheKeyService $iconCacheKeyService;
    public function __construct(LocalIconService $localIconService, IconCacheKeyService $iconCacheKeyService)
    {
        $this->localIconService = $localIconService;
        $this->iconCacheKeyService = $iconCacheKeyService;
    }
    /**
     * Upload custom SVG icon(s)
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    #[Route('/upload', 'POST', permission_callback: 'manage_options')]
    public function upload_custom_icon(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Sanitize $_FILES superglobal early (WordPress standard: sanitize early)
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File arrays are sanitized below
        $files = isset($_FILES['icon']) ? $_FILES['icon'] : null;
        // Check if file(s) were uploaded
        if (empty($files)) {
            return new WP_Error('no_file', __('No file uploaded', 'jooosi-icon'), ['status' => 400]);
        }
        // Get optional icon set (subdirectory) - WP_REST_Request handles sanitization
        $icon_set = $request->get_param('icon_set');
        // Check if multiple files were uploaded
        $is_multiple = is_array($files['name']);
        if ($is_multiple) {
            // Handle multiple file uploads
            $results = [];
            $errors = [];
            $uploaded_count = 0;
            $total_files = count($files['name']);
            for ($i = 0; $i < $total_files; $i++) {
                // Check if file was actually uploaded
                if (empty($files['tmp_name'][$i])) {
                    continue;
                }
                // Create single file array from multi-file upload and sanitize each field
                $file = ['name' => sanitize_file_name($files['name'][$i]), 'type' => sanitize_text_field($files['type'][$i]), 'tmp_name' => sanitize_text_field($files['tmp_name'][$i]), 'error' => (int) $files['error'][$i], 'size' => (int) $files['size'][$i]];
                // Check mime type
                $mime_type = $this->localIconService->detect_mime($file['tmp_name']);
                if ($mime_type !== 'image/svg+xml') {
                    $errors[] = ['filename' => $file['name'], 'message' => __('Invalid file type. Only SVG files are allowed.', 'jooosi-icon')];
                    continue;
                }
                // Upload and sanitize the icon
                $result = $this->localIconService->upload_svg($file, $icon_set);
                if ($result['success']) {
                    $results[] = $result;
                    $uploaded_count++;
                } else {
                    $errors[] = ['filename' => $file['name'], 'message' => $result['message']];
                }
            }
            // Return combined results
            return new WP_REST_Response(['success' => $uploaded_count > 0, 'message' => sprintf(__('%d of %d icons uploaded successfully', 'jooosi-icon'), $uploaded_count, $total_files), 'uploaded_count' => $uploaded_count, 'total_count' => $total_files, 'results' => $results, 'errors' => $errors]);
        } else {
            // Handle single file upload (backward compatibility)
            // Sanitize file array fields
            $file = ['name' => sanitize_file_name($files['name']), 'type' => sanitize_text_field($files['type']), 'tmp_name' => sanitize_text_field($files['tmp_name']), 'error' => (int) $files['error'], 'size' => (int) $files['size']];
            // check mime type with LocalIconService
            $mime_type = $this->localIconService->detect_mime($file['tmp_name']);
            if ($mime_type !== 'image/svg+xml') {
                return new WP_Error('invalid_file_type', __('Invalid file type. Only SVG files are allowed.', 'jooosi-icon'), ['status' => 400]);
            }
            // Upload and sanitize the icon
            $result = $this->localIconService->upload_svg($file, $icon_set);
            if (!$result['success']) {
                return new WP_Error('upload_failed', $result['message'], ['status' => 400]);
            }
            return new WP_REST_Response($result);
        }
    }
    /**
     * Get all icon sets with metadata
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    #[Route('/sets', 'GET', permission_callback: 'manage_options')]
    public function get_icon_sets(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $sets = $this->localIconService->get_icon_sets();
        return new WP_REST_Response(['success' => \true, 'data' => $sets]);
    }
    /**
     * Get all icons from a specific set
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    #[Route('/sets/(?P<icon_set>[a-zA-Z0-9_-]+)/icons', 'GET', permission_callback: 'manage_options')]
    public function get_icons_by_set(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $icon_set = $request->get_param('icon_set');
        // "local" set is for root directory (pass null)
        $icon_set_param = $icon_set === 'local' ? null : $icon_set;
        $icons = $this->localIconService->get_icons_by_set($icon_set_param);
        return new WP_REST_Response(['success' => \true, 'data' => $icons]);
    }
    /**
     * Get all local icons
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    #[Route('/icons', 'GET', permission_callback: 'manage_options')]
    public function get_all_icons(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $icons = $this->localIconService->get_all_icons();
        return new WP_REST_Response(['success' => \true, 'data' => $icons]);
    }
    /**
     * Move icon to a different set
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    #[Route('/move', 'POST', permission_callback: 'manage_options')]
    public function move_icon(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $icon_name = $request->get_param('icon_name');
        $target_set = $request->get_param('target_set');
        if (empty($icon_name)) {
            return new WP_Error('missing_icon_name', __('Icon name is required', 'jooosi-icon'), ['status' => 400]);
        }
        $result = $this->localIconService->move_icon($icon_name, $target_set);
        if (!$result['success']) {
            return new WP_Error('move_failed', $result['message'], ['status' => 400]);
        }
        return new WP_REST_Response($result);
    }
    /**
     * Create a new icon set
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    #[Route('/sets/create', 'POST', permission_callback: 'manage_options')]
    public function create_set(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $set_name = $request->get_param('set_name');
        if (empty($set_name)) {
            return new WP_Error('missing_set_name', __('Set name is required', 'jooosi-icon'), ['status' => 400]);
        }
        $result = $this->localIconService->create_set($set_name);
        if (!$result['success']) {
            return new WP_Error('create_failed', $result['message'], ['status' => 400]);
        }
        return new WP_REST_Response($result);
    }
    /**
     * Rename an icon set
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    #[Route('/sets/(?P<set_name>[a-zA-Z0-9_-]+)/rename', 'POST', permission_callback: 'manage_options')]
    public function rename_set(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $old_name = $request->get_param('set_name');
        $new_name = $request->get_param('new_name');
        if (empty($new_name)) {
            return new WP_Error('missing_new_name', __('New name is required', 'jooosi-icon'), ['status' => 400]);
        }
        $result = $this->localIconService->rename_set($old_name, $new_name);
        if (!$result['success']) {
            return new WP_Error('rename_failed', $result['message'], ['status' => 400]);
        }
        return new WP_REST_Response($result);
    }
    /**
     * Clear icon cache
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    #[Route('/cache/clear', 'POST', permission_callback: 'manage_options')]
    public function clear_cache(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $this->localIconService->clear_cache();
        return new WP_REST_Response(['success' => \true, 'message' => __('Cache cleared successfully', 'jooosi-icon'), 'cache_key' => $this->iconCacheKeyService->get_key()]);
    }
    /**
     * Delete a custom icon
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    #[Route('/(?P<icon_name>[a-zA-Z0-9:_-]+)', 'DELETE', permission_callback: 'manage_options', args: ['icon_name' => ['validate_callback' => [self::class, 'validateIconName'], 'sanitize_callback' => [self::class, 'sanitizeIconName'], 'required' => \true]])]
    public function delete_custom_icon(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $icon_name = $request->get_param('icon_name');
        $result = $this->localIconService->delete_icon($icon_name);
        if (!$result['success']) {
            return new WP_Error('delete_failed', $result['message'], ['status' => 400]);
        }
        return new WP_REST_Response($result);
    }
    /**
     * Validate icon name format
     */
    public static function validateIconName($param, $request, $key): bool
    {
        // Icon name should be in format "prefix:name" or just "name"
        return is_string($param) && preg_match('/^[a-zA-Z0-9:_-]+$/', $param);
    }
    /**
     * Sanitize icon name
     */
    public static function sanitizeIconName($param, $request, $key): string
    {
        return sanitize_text_field($param);
    }
}
