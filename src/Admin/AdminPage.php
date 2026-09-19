<?php

declare (strict_types=1);
namespace JooosiIcon\Admin;

defined('ABSPATH') || exit;
use JOOOSI_ICON;
use JooosiIcon\Core\Discovery\Attributes\Hook;
use JooosiIcon\Core\Discovery\Attributes\Service;
use JooosiIcon\Services\ViteService;
/**
 * Service for registering and managing admin pages
 */
#[Service]
class AdminPage
{
    public function __construct(private ViteService $viteService)
    {
    }
    /**
     * Register the admin menu page
     */
    #[Hook('admin_menu', priority: 10)]
    public function add_admin_menu(): void
    {
        $hook = add_menu_page(__('Jooosi Icon', 'jooosi-icon'), __('Jooosi Icon', 'jooosi-icon'), 'manage_options', JOOOSI_ICON::TEXT_DOMAIN, fn() => $this->render(), 'data:image/svg+xml;base64,' . base64_encode(file_get_contents(dirname(JOOOSI_ICON::FILE) . '/jooosi-icon.svg')), 100);
        add_action('load-' . $hook, fn() => $this->init_hooks());
    }
    /**
     * Get the URL to the admin page
     */
    public static function get_page_url(): string
    {
        return add_query_arg(['page' => JOOOSI_ICON::TEXT_DOMAIN], admin_url('admin.php'));
    }
    /**
     * Render the admin page
     */
    private function render(): void
    {
        do_action('jooosi-icon/admin:render.before');
        do_action_deprecated('omni-icon/admin:render.before', [], JOOOSI_ICON::VERSION, 'jooosi-icon/admin:render.before');
        echo '<div id="jooosi-icon-app"></div>';
        do_action('jooosi-icon/admin:render.after');
        do_action_deprecated('omni-icon/admin:render.after', [], JOOOSI_ICON::VERSION, 'jooosi-icon/admin:render.after');
    }
    /**
     * Initialize hooks for the admin page
     */
    private function init_hooks(): void
    {
        add_action('admin_enqueue_scripts', fn() => $this->enqueue_scripts(), 10);
    }
    /**
     * Enqueue scripts for the admin page
     */
    private function enqueue_scripts(): void
    {
        do_action('jooosi-icon/admin:enqueue_scripts.before');
        do_action_deprecated('omni-icon/admin:enqueue_scripts.before', [], JOOOSI_ICON::VERSION, 'jooosi-icon/admin:enqueue_scripts.before');
        // Enqueue admin app
        $this->viteService->enqueue_asset('resources/admin/admin-app/index.jsx', ['handle' => 'jooosi-icon-admin', 'in_footer' => \true, 'dependencies' => ['react', 'react-dom', 'wp-element', 'wp-components', 'wp-i18n', 'wp-data']]);
        // Pass data to JavaScript
        $admin_data = ['apiUrl' => rest_url(JOOOSI_ICON::REST_NAMESPACE . '/admin/local-icon'), 'nonce' => wp_create_nonce('wp_rest'), 'version' => JOOOSI_ICON::VERSION];
        wp_localize_script('jooosi-icon-admin', 'jooosiIconAdmin', $admin_data);
        do_action('jooosi-icon/admin:enqueue_scripts.after');
        do_action_deprecated('omni-icon/admin:enqueue_scripts.after', [], JOOOSI_ICON::VERSION, 'jooosi-icon/admin:enqueue_scripts.after');
    }
}
