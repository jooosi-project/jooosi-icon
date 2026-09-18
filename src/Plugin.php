<?php

declare(strict_types=1);

namespace JooosiIcon;

defined('ABSPATH') || exit;

use Exception;
use JOOOSI_ICON;
use JooosiIcon\Core\Container\Container;
use JooosiIcon\Core\Discovery\CommandDiscovery;
use JooosiIcon\Core\Discovery\DiscoveryManager;
use JooosiIcon\Core\Discovery\HookDiscovery;
use RuntimeException;

final class Plugin
{
    private static ?self $instance = null;

    private ?Container $container = null;
    
    private ?DiscoveryManager $discoveryManager = null;

    private bool $booted = false;

    private function __construct()
    {
    }

    public static function get_instance(): self
    {
        if (! self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->container = new Container();
        $this->discover_components();
        
        if (!$this->container instanceof Container) {
            throw new RuntimeException('Container initialization failed');
        }
        
        $this->container->compile();
        $this->register_discovered_hooks();
        $this->register_discovered_commands();
        $this->register_hooks();
        $this->booted = true;

        do_action('jooosi-icon/core:plugin.booted', $this);
        do_action_deprecated(
            'omni-icon/core:plugin.booted',
            [$this],
            JOOOSI_ICON::VERSION,
            'jooosi-icon/core:plugin.booted',
        );
    }

    public function container(): Container
    {
        if (! $this->container instanceof Container) {
            throw new RuntimeException('Plugin not booted yet. Call boot() first.');
        }

        return $this->container;
    }

    public function init(): void
    {
        // load_plugin_textdomain(JOOOSI_ICON::TEXT_DOMAIN, false, dirname(plugin_basename(JOOOSI_ICON::FILE)) . '/languages');

        do_action('jooosi-icon/core:init', $this);
        do_action_deprecated(
            'omni-icon/core:init',
            [$this],
            JOOOSI_ICON::VERSION,
            'jooosi-icon/core:init',
        );
    }

    public function plugins_loaded(): void
    {
        if (! $this->check_dependencies()) {
            return;
        }

        do_action('jooosi-icon/core:plugins-loaded', $this);
        do_action_deprecated(
            'omni-icon/core:plugins-loaded',
            [$this],
            JOOOSI_ICON::VERSION,
            'jooosi-icon/core:plugins-loaded',
        );
    }

    public function activate(): void
    {
        if (! $this->check_dependencies()) {
            deactivate_plugins(plugin_basename(JOOOSI_ICON::FILE));
            wp_die(
                esc_html__('Jooosi Icon requires WordPress 6.0+ and PHP 8.1+', 'jooosi-icon'),
                esc_html__('Plugin Activation Error', 'jooosi-icon'),
                [
                    'back_link' => true,
                ]
            );
        }

        // Set plugin version option
        update_option('jooosi_icon_version', JOOOSI_ICON::VERSION);

        // Ensure the plugin is booted
        if (! $this->booted) {
            $this->boot();
        }

        // Clear discovery cache on activation
        try {
            $this->discoveryManager?->clear_cache();
        } catch (Exception) {
            // Discovery manager might not be available yet
        }

        do_action('jooosi-icon/core:activate', $this);
        do_action_deprecated(
            'omni-icon/core:activate',
            [$this],
            JOOOSI_ICON::VERSION,
            'jooosi-icon/core:activate',
        );

        flush_rewrite_rules();
    }

    public function deactivate(): void
    {
        do_action('jooosi-icon/core:deactivate', $this);
        do_action_deprecated(
            'omni-icon/core:deactivate',
            [$this],
            JOOOSI_ICON::VERSION,
            'jooosi-icon/core:deactivate',
        );

        flush_rewrite_rules();
    }

    private function discover_components(): void
    {
        if (!$this->container instanceof Container) {
            throw new RuntimeException('Container not initialized');
        }
        
        $this->discoveryManager = new DiscoveryManager($this->container);
        $this->discoveryManager->discover();
    }
    
    private function register_discovered_hooks(): void
    {
        if (!$this->discoveryManager instanceof DiscoveryManager) {
            return;
        }
        
        foreach ($this->discoveryManager->getDiscoveries() as $discovery) {
            if ($discovery instanceof HookDiscovery) {
                $discovery->registerHooks();
            }
        }
    }
    
    private function register_discovered_commands(): void
    {
        if (!$this->discoveryManager instanceof DiscoveryManager) {
            return;
        }
        
        foreach ($this->discoveryManager->getDiscoveries() as $discovery) {
            if ($discovery instanceof CommandDiscovery) {
                $discovery->registerCommands();
            }
        }
    }

    private function register_hooks(): void
    {
        add_action('init', $this->init(...));
        add_action('plugins_loaded', $this->plugins_loaded(...));

        register_activation_hook(JOOOSI_ICON::FILE, $this->activate(...));
        register_deactivation_hook(JOOOSI_ICON::FILE, $this->deactivate(...));
    }

    private function check_dependencies(): bool
    {
        /** @var string */
        global $wp_version;

        return version_compare($wp_version, '6.0', '>=') && PHP_VERSION_ID >= 80100;
    }
}
