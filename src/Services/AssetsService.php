<?php

declare (strict_types=1);
namespace JooosiIcon\Services;

use JOOOSI_ICON;
use JooosiIcon\Core\Discovery\Attributes\Hook;
use JooosiIcon\Core\Discovery\Attributes\Service;
/**
 * Service for managing plugin assets (scripts and styles)
 */
#[Service]
class AssetsService
{
    public function __construct(private \JooosiIcon\Services\ViteService $viteService)
    {
    }
    /**
     * Enqueue webcomponent on the frontend and admin pages
     */
    #[Hook('wp_enqueue_scripts', priority: 10)]
    #[Hook('admin_enqueue_scripts', priority: 10)]
    public function enqueue_frontend_scripts(): void
    {
        $this->viteService->enqueue_asset('resources/webcomponents/jooosi-icon.ts', ['handle' => JOOOSI_ICON::TEXT_DOMAIN . ':web-component:jooosi-icon', 'dependencies' => [], 'in_footer' => \false]);
    }
}
