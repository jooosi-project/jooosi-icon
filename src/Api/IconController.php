<?php

declare (strict_types=1);
namespace JooosiIcon\Api;

defined('ABSPATH') || exit;
use JooosiIcon\Core\Discovery\Attributes\Controller;
use JooosiIcon\Core\Discovery\Attributes\Route;
use JooosiIcon\Services\IconService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
#[Controller(namespace: 'jooosi-icon/v1', prefix: 'icon')]
final class IconController
{
    /**
     * @var IconService
     */
    private IconService $iconService;
    public function __construct(IconService $iconService)
    {
        $this->iconService = $iconService;
    }
    /**
     * Get a single icon by name
     * URL format: /wp-json/jooosi-icon/v1/icon/item/{prefix}/{name}
     * The / separator is converted to : internally for the icon service
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    #[Route('/item/(?P<prefix>[a-zA-Z0-9_-]+)/(?P<name>[a-zA-Z0-9_-]+)', 'GET', args: ['prefix' => ['validate_callback' => [self::class, 'validateIconPrefix'], 'sanitize_callback' => 'sanitize_text_field', 'required' => \true], 'name' => ['validate_callback' => [self::class, 'validateIconNamePart'], 'sanitize_callback' => 'sanitize_text_field', 'required' => \true]])]
    public function get_icon(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $prefix = $request->get_param('prefix');
        $name = $request->get_param('name');
        // Convert URL-friendly / separator to : for internal icon service
        $iconName = $prefix . ':' . $name;
        $iconData = $this->iconService->get_icon_data($iconName);
        if ($iconData === null) {
            return new WP_Error('icon_not_found', sprintf('Icon "%s" not found', $iconName), ['status' => 404]);
        }
        return new WP_REST_Response($iconData);
    }
    /**
     * Get SVG content for a single icon by name
     * Returns raw SVG with image/svg+xml content type
     * URL format: /wp-json/jooosi-icon/v1/icon/{prefix}/{name}.svg
     * The / separator is converted to : internally for the icon service
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    #[Route('/item/(?P<prefix>[a-zA-Z0-9_-]+)/(?P<name>[a-zA-Z0-9_-]+)\.svg', 'GET', args: ['prefix' => ['validate_callback' => [self::class, 'validateIconPrefix'], 'sanitize_callback' => 'sanitize_text_field', 'required' => \true], 'name' => ['validate_callback' => [self::class, 'validateIconNamePart'], 'sanitize_callback' => 'sanitize_text_field', 'required' => \true]])]
    public function get_icon_svg(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $prefix = $request->get_param('prefix');
        $name = $request->get_param('name');
        // Convert URL-friendly / separator to : for internal icon service
        $iconName = $prefix . ':' . $name;
        $svg = $this->iconService->get_icon($iconName);
        if ($svg === null) {
            return new WP_Error('icon_not_found', sprintf('Icon "%s" not found', $iconName), ['status' => 404]);
        }
        // Bypass WordPress REST API JSON encoding by outputting directly
        add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) use ($svg) {
            if ($result instanceof WP_REST_Response && $result->get_Data() === '__SVG_OUTPUT__') {
                header('Content-Type: image/svg+xml');
                header('Cache-Control: public, max-age=31536000, immutable');
                header('Vary: Accept-Encoding');
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is sanitized by IconService
                echo $svg;
                return \true;
                // Mark as served
            }
            return $served;
        }, 10, 4);
        // Return a marker that triggers our filter
        return new WP_REST_Response('__SVG_OUTPUT__');
    }
    /**
     * Search icons - returns ALL results (limit=999, cached for 5 minutes)
     * Frontend does client-side pagination
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    #[Route('/search', 'GET', args: ['query' => ['validate_callback' => [self::class, 'validateSearchQuery'], 'sanitize_callback' => 'sanitize_text_field', 'required' => \true]])]
    public function search_icons(WP_REST_Request $request): WP_REST_Response
    {
        $query = $request->get_param('query') ?? '';
        try {
            $searchResults = $this->iconService->search_icons($query);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage(), 'error' => get_class($e)], 400);
        }
        return new WP_REST_Response(['results' => $searchResults['results'], 'total' => $searchResults['total'], 'query' => $query]);
    }
    /**
     * Get available icon collections/prefixes
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    #[Route('/collections', 'GET')]
    public function get_collections(WP_REST_Request $request): WP_REST_Response
    {
        $iconSets = $this->iconService->get_icon_sets();
        return new WP_REST_Response(['collections' => $iconSets]);
    }
    /**
     * Validate search query
     *
     * @param mixed $value
     * @return bool
     */
    public static function validateSearchQuery(mixed $value): bool
    {
        if (!is_string($value) && $value !== null) {
            return \false;
        }
        return \true;
    }
    /**
     * Validate positive integer
     *
     * @param mixed $value
     * @return bool
     */
    public static function validatePositiveInteger(mixed $value): bool
    {
        return is_numeric($value) && (int) $value > 0;
    }
    /**
     * Validate per_page parameter (1-128)
     *
     * @param mixed $value
     * @return bool
     */
    public static function validatePerPage(mixed $value): bool
    {
        return is_numeric($value) && (int) $value >= 1 && (int) $value <= 128;
    }
    /**
     * Validate icon prefix
     *
     * @param mixed $value
     * @return bool
     */
    public static function validateIconPrefix(mixed $value): bool
    {
        if (!is_string($value)) {
            return \false;
        }
        return (bool) preg_match('/^[a-zA-Z0-9_-]+$/', $value);
    }
    /**
     * Validate icon name part without prefix
     *
     * @param mixed $value
     * @return bool
     */
    public static function validateIconNamePart(mixed $value): bool
    {
        if (!is_string($value)) {
            return \false;
        }
        return (bool) preg_match('/^[a-zA-Z0-9_-]+$/', $value);
    }
}
