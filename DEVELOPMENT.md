# Development Guide

This guide covers everything you need to know to develop, customize, and contribute to Jooosi Icon.

## Requirements

- [PHP](https://www.php.net/) 8.1+
- [WordPress](https://wordpress.org/) 6.0+
- [Node.js](https://nodejs.org/)
- [pnpm](https://pnpm.io)
- [Composer](https://getcomposer.org/)

## Getting Started

### 1. Clone the Repository

Clone the repository to your WordPress `wp-content/plugins` directory:

```bash
cd /path/to/wordpress/wp-content/plugins
git clone https://github.com/jooosi-project/jooosi-icon.git
cd jooosi-icon
```

### 2. Install Dependencies

Install PHP dependencies:

```bash
composer install
```

Install JavaScript dependencies:

```bash
pnpm install
```

### 3. Start Development Server

Start the Vite+ development server with hot module replacement:

```bash
pnpm run dev
```

This will start the Vite+ dev server on `http://localhost:5173` and watch for changes.

### 4. Activate the Plugin

1. Go to your WordPress admin panel
2. Navigate to Plugins
3. Find "Jooosi Icon" and click "Activate"

## Architecture

### Overview

Jooosi Icon uses modern PHP and JavaScript architecture patterns:

- **PHP**: Symfony-based dependency injection with attribute-based discovery
- **JavaScript**: Vite+ + React + TypeScript with web components
- **Caching**: Multi-layer caching (memory, filesystem, IndexedDB)
- **SSR**: Server-side rendering for instant icon display

### Core Services

#### IconService
**Location**: `src/Services/IconService.php`

Unified icon management using Chain of Responsibility pattern:
- Routes requests to appropriate icon sources (Local → Bundle/third-party → Iconify)
- Provides unified API for all icon operations
- Manages caching strategy

#### LocalIconService
**Location**: `src/Services/LocalIconService.php`

Manages user-uploaded custom SVG icons:
- SVG upload with sanitization
- Multi-set support via subdirectories
- mtime-based cache invalidation
- CRUD operations via REST API

#### IconSourceService
**Location**: `src/Services/IconSourceService.php`

Manages built-in and third-party file-based icon sources:
- Simple flat directory structure
- Bundled icons use the `jooosi:` prefix (`omni:` remains a compatibility alias)
- Third-party icon sources can be added with `jooosi-icon/service/icon:sources`
- Cached icon listings

`BundleIconService` remains as a deprecated compatibility subclass for existing integrations.

#### IconifyService
**Location**: `src/Services/IconifyService.php`

On-demand Iconify API integration:
- Powered by Symfony UX Icons
- Search across 200,000+ icons
- Cached metadata and search results
- Reflection-based API access

#### Third-party icon sources
Third-party plugins can add an icon source to the registry, collection metadata, and search results with the `jooosi-icon/service/icon:sources` filter:

```php
add_filter('jooosi-icon/service/icon:sources', static function (array $sources): array {
    $icon_dir = __DIR__ . '/icons';
    $sources['acme'] = [
        'name' => 'Acme Icons',
        'path' => $icon_dir,
        'url' => plugins_url('icons', __FILE__),
    ];

    return $sources;
});
```

Each source must provide a slug key, `name`, and `path`; `url` is optional and is used in icon metadata when available. The source is checked after uploaded local icons and before the Iconify registry, and its icons are included in the collections and search endpoints. Source registration is lazy, so a theme can add the filter from `functions.php`; it must run before the first icon use. This lets a third-party icon use the same prefix/name format as other icon sources without modifying plugin files or the uploads directory.

#### AssetsService
**Location**: `src/Services/AssetsService.php`

Manages plugin asset enqueuing:
- Enqueues web components on frontend and admin
- Vite+ integration for development and production

#### ViteService
**Location**: `src/Services/ViteService.php`

Vite build system integration:
- Asset registration and enqueuing
- Manifest parsing for production
- Development mode with HMR support

### Dependency Injection Container

**Location**: `src/Core/Container/`

Jooosi Icon uses Symfony DependencyInjection for PSR-11 compliant dependency injection:

- **Container.php**: Main DI container implementation
- **DependencyResolver.php**: Resolves dependencies and manages service instantiation

**Features**:
- Autowiring for automatic dependency resolution
- Service tagging and aliases
- Compiler passes for optimization
- Singleton and transient services

### Auto-Discovery System

**Location**: `src/Core/Discovery/`

Components are automatically discovered using PHP 8.2+ attributes:

#### Service Discovery
**Attribute**: `#[Service]`

Auto-register services in the DI container:

```php
use JooosiIcon\Core\Discovery\Attributes\Service;

#[Service(singleton: true)]
class MyService {
    // Service implementation
}
```

Options:
- `singleton`: Register as singleton (default: true)
- `public`: Make service publicly accessible (default: false)
- `tags`: Tag the service for compiler passes
- `alias`: Register service alias

#### Hook Discovery
**Attribute**: `#[Hook('hook_name', priority: 10)]`

Auto-register WordPress actions and filters:

```php
use JooosiIcon\Core\Discovery\Attributes\Hook;

class MyHooks {
    #[Hook('init', priority: 10)]
    public function onInit(): void {
        // Action implementation
    }
    
    #[Hook('the_content', priority: 10)]
    public function filterContent(string $content): string {
        // Filter implementation
        return $content;
    }
}
```

#### Controller Discovery
**Attributes**: `#[Controller]` + `#[Route]`

Auto-register REST API endpoints:

```php
use JooosiIcon\Core\Discovery\Attributes\Controller;
use JooosiIcon\Core\Discovery\Attributes\Route;

#[Controller]
class IconController {
    #[Route(
        path: '/icon/item/(?P<prefix>[a-z0-9-]+)/(?P<name>[a-z0-9-]+)',
        methods: ['GET'],
        permission_callback: '__return_true'
    )]
    public function getIcon(array $params): array {
        // Controller implementation
        return ['svg' => '...'];
    }
}
```

### Caching Strategy

Multi-layer caching for optimal performance:

#### 1. Memory Cache (In-Process)
- Fast access for repeated requests within same PHP process
- No serialization overhead
- Cleared on each request

#### 2. Symfony FilesystemAdapter (Persistent Disk)
- Persistent cache across requests
- PSR-6 compliant
- Automatic serialization
- Location: `wp-content/uploads/jooosi-icon/cache/iconify/` (the former `wp-content/uploads/omni-icon/` tree is migrated automatically, with fallback when a rename is unavailable)

#### 3. IndexedDB (Browser-Side)
- Client-side cache for web components
- Offline support
- Reduces server requests
- Automatic cache invalidation

#### Cache TTLs
- Icons: Indefinite (cache-busted via mtime)
- Search results: 5 minutes
- Collections: Indefinite
- Local icon listings: Invalidated on directory change

### Discovery Cache

**Strategy**: FULL mode (production) vs PARTIAL mode (development)

- **FULL mode**: Caches all discovery results
- **PARTIAL mode**: Only caches Composer classmap
- **Excluding paths**: Add paths to `composer.json` under `extra.discovery.exclude` to skip directories from discovery

Example:
```json
{
  "extra": {
        "discovery": {
          "exclude": [
            "src/Integration/Bricks/Elements",
            "src/Integration/Breakdance/Elements",
            "src/Integration/Elementor/Widgets",
            "src/Integration/ACF/Fields"
          ]
        }
  }
}
```

## Directory Structure

```
jooosi-icon/
├── composer.json             # PHP dependencies
├── package.json              # JavaScript dependencies
├── pnpm-lock.yaml            # Lock file for pnpm
├── jooosi-icon.php           # Main plugin file (bootstrap)
├── constant.php              # Plugin constants and paths
├── vite.config.js            # Vite configuration
├── .gitignore                # Git ignore rules
├── .php-version              # PHP version constraint
├── README.md                 # User documentation
├── DEVELOPMENT.md            # This file
│
├── resources/                # Frontend resources
│   ├── admin/                # Admin panel resources
│   │
│   ├── integration/          # Page builder integrations
│   │   └── ...
│   │
│   └── webcomponents/        # Web component source
│       └── ...
│
├── src/                      # PHP source code
│   ├── Admin/               # Admin pages
│   │   └── AdminPage.php
│   │
│   ├── Api/                 # REST API controllers
│   │   ├── Admin/
│   │   │   └── LocalIconController.php  # Upload/delete icons
│   │   └── IconController.php            # Public icon API
│   │
│   ├── Core/                # Core framework
│   │   ├── Container/       # DI container
│   │   │   ├── Container.php
│   │   │   └── DependencyResolver.php
│   │   │
│   │   ├── Database/        # Database & migrations
│   │   │
│   │   ├── Discovery/       # Auto-discovery system
│   │   │   ├── Attributes/  # Discovery attributes
│   │   │   └── ...
│   │   │
│   │   └── Logger/          # Logging
│   │
│   ├── Integration/         # Page builder services
│   │   └── ...
│   │
│   ├── Services/            # Business logic services
│   │   ├── AssetsService.php
│   │   ├── IconSourceService.php
│   │   ├── IconifyService.php
│   │   ├── IconService.php
│   │   ├── LocalIconService.php
│   │   └── ViteService.php
│   │
│   ├── Utils/               # Utility classes
│   │
│   └── Plugin.php           # Main plugin class
│
├── dist/                    # Built assets
│   ├── manifest.json        # Vite manifest
│   └── ...                  # Built JS/CSS files
│
├── svg/                     # Bundled icons]
│   └── ...
│
└── vendor/                  # Composer dependencies
```

## Frontend Architecture

### Web Components

#### JooosiIconRenderer
**Location**: `resources/webcomponents/JooosiIconRenderer.ts`

Main rendering engine for the `<jooosi-icon>` web component:
- State management per element (WeakMap)
- Mutation observer for attribute changes
- Abort controller for request cancellation
- SVG caching per element
- Animation restart on icon change

#### IconRegistry
**Location**: `resources/webcomponents/IconRegistry.ts`

Icon fetching and caching system:
- Request queue with priority support
- Max concurrent requests (4)
- Consumer tracking for shared requests
- IndexedDB persistence
- Memory cache for fast access

#### ErrorObserver
**Location**: `resources/webcomponents/ErrorObserver.ts`

Error state management:
- Lazy-loaded only when errors occur
- Expandable error details
- Visual error indicators
- Graceful fallback

### Web Component Regression Tests

Run `pnpm test:webcomponents` to open the Vitest browser runner. The suite uses
real custom elements, MutationObserver, and IndexedDB in the browser, with mocked
icon API responses. It covers attribute batching and restoration, shared cache
lookups, request cancellation and priority, prerendered content, legacy elements,
error recovery, iframe body replacement, and the fallback without custom elements.
These tests do not require a running WordPress installation.

### React Components (Gutenberg)

**Location**: `resources/integration/gutenberg/blocks/icon-block/components/`

#### Edit.jsx
Main editor component for the icon block:
- Icon picker integration
- Attribute controls
- Live preview
- Toolbar controls

#### Save.jsx
Static block save component:
- SSR support
- Minimal markup
- Web component output

#### IconPickerModal.jsx
Icon browser and search:
- Tab-based interface (Search, Browse)
- Real-time search with debouncing
- Pagination
- Collection browsing
- Keyboard navigation

#### IconItem.jsx
Individual icon display:
- Click handling
- Visual selection state
- Icon rendering

#### hooks.js
Custom React hooks:
- `useIconCollections`: Fetch collections
- `useIconSearch`: Debounced search
- `useDefaultIcons`: Generate default icon grid
- `useIconFiltering`: Filter and paginate
- `useKeyboardNavigation`: Keyboard controls

## Build System

### Vite Configuration

**Location**: `vite.config.js`

#### Plugins

1. **@nabasa/vp-wp**
   - WordPress integration
   - Automatic asset enqueuing
   - Manifest generation

2. **@vitejs/plugin-react**
   - React support with Fast Refresh
   - JSX transformation

3. **vite-plugin-node-polyfills**
   - Node.js polyfills for browser
   - memfs for in-memory filesystem

4. **unplugin-icons**
   - Import icons from Iconify collections
   - Tree-shakeable icon imports

5. **vite-plugin-svgr**
   - Import SVG as React components
   - Inline SVG support

6. **vite-plugin-static-copy**
   - Copy block.json files to dist
   - Preserve file structure

#### Entry Points

- Gutenberg block editor
- Gutenberg iframe (canvas)
- Web component
- ACF integration
- Bricks integration
- Elementor integration
- Breakdance integration
- LiveCanvas integration

#### Output

- **Development**: HMR with source maps
- **Production**: Minified, optimized, versioned assets in `/dist`


## REST API

All endpoints use the `jooosi-icon/v1` namespace.

### Public Endpoints

#### Get Icon Data
```
GET /wp-json/jooosi-icon/v1/icon/item/{prefix}/{name}
```

Example: `/wp-json/jooosi-icon/v1/icon/item/mdi/home`

Response:
```json
{
  "svg": "<svg>...</svg>",
  "name": "home",
  "prefix": "mdi"
}
```

#### Get Icon SVG (Direct)
```
GET /wp-json/jooosi-icon/v1/icon/item/{prefix}/{name}.svg
```

Returns raw SVG with `image/svg+xml` content-type and cache headers (`max-age=31536000, immutable`).

#### Search Icons
```
GET /wp-json/jooosi-icon/v1/icon/search?query=home
```

Returns search results from all sources (local, bundle, Iconify).

#### Get Collections
```
GET /wp-json/jooosi-icon/v1/icon/collections
```

Returns list of available icon sets with metadata.

### Admin Endpoints

#### Upload Icon
```
POST /wp-json/jooosi-icon/v1/admin/local-icon/upload
```

Requires: `manage_options` capability

Parameters:
- `file`: SVG file (multipart/form-data)
- `icon_set`: Optional set name for organizing icons

#### Delete Icon
```
DELETE /wp-json/jooosi-icon/v1/admin/local-icon/{icon_name}
```

Requires: `manage_options` capability

## Resources

- [Symfony UX Icons Documentation](https://symfony.com/bundles/ux-icons/current/index.html)
- [Iconify API Documentation](https://iconify.design/docs/api/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Vite Documentation](https://vitejs.dev/)
- [React Documentation](https://react.dev/)

## Support

For development-related questions or issues:

1. Check this documentation first
2. Search existing GitHub issues
3. Create a new issue with detailed information
4. Join our community discussions

---

Happy coding!
