<p align="center">
  <img src="./jooosi-icon.svg" alt="Jooosi Icon Logo" width="100">
</p>

<h1 align="center">Jooosi Icon</h1>

<p align="center">
  <i>A modern WordPress plugin that seamlessly integrates icons across the WordPress ecosystem with support for multiple page builders, custom icon uploads, and access to 200,000+ icons from Iconify.</i>
</p>

<p align="center">
  <a href="https://downloads.wordpress.org/plugin/omni-icon.zip">
    <picture>
    <img src="https://img.shields.io/wordpress/plugin/v/omni-icon.svg?logo=wordpress&label=version" alt="WordPress plugin version" />
    </picture>
  </a>
  <a href="https://wordpress.org/plugins/omni-icon/advanced/">
    <picture>
    <img src="https://img.shields.io/wordpress/plugin/dt/omni-icon.svg?logo=wordpress" alt="WordPress plugin downloads" />
    </picture>
  </a>
  <a href="https://wordpress.org/plugins/omni-icon/">
    <picture>
    <img src="https://img.shields.io/wordpress/plugin/installs/omni-icon.svg?logo=wordpress" alt="WordPress plugin active installs" />
    </picture>
  </a>
  <a href="https://wordpress.org/support/plugin/omni-icon/reviews/?filter=5/#new-post">
    <picture>
    <img src="https://img.shields.io/wordpress/plugin/stars/omni-icon.svg?logo=wordpress" alt="WordPress plugin rating" />
    </picture>
  </a>
  <br />
  <a href="https://github.com/jooosi-project/jooosi-icon/releases">
    <picture>
    <img src="https://img.shields.io/github/v/release/jooosi-project/jooosi-icon.svg?logo=github" alt="GitHub Release" />
    </picture>
  </a>
  <a href="https://github.com/jooosi-project/jooosi-icon/blob/master/LICENSE">
    <picture>
    <img src="https://img.shields.io/github/license/jooosi-project/jooosi-icon.svg" alt="GPL License" />
    </picture>
  </a>
  <a href="https://github.com/jooosi-project/jooosi-icon/actions">
    <picture>
    <img src="https://img.shields.io/github/actions/workflow/status/jooosi-project/jooosi-icon/deploy.yaml" alt="Build Status" />
    </picture>
  </a>
  <br />
  <a aria-label="GitHub Sponsors" href="https://github.com/sponsors/suasgn">
    <picture>
      <img alt="GitHub Sponsors button" src="https://img.shields.io/github/sponsors/suasgn?logo=github">
    </picture>
  </a>
  <a aria-label="Support me on Ko-fi" href="https://ko-fi.com/Q5Q75XSF7">
    <picture>
      <img alt="ko-fi button" src="https://img.shields.io/badge/Buy_me_a_Coffee-ff5e5b?logo=ko-fi&label=Ko-fi">
    </picture>
  </a>
  <a aria-label="Join Our Facebook community" href="https://wind.press/go/facebook">
    <picture>
      <img alt="facebook group button" src="https://img.shields.io/badge/Join_us-0866ff?logo=facebook&label=Community">
    </picture>
  </a>
</p>

> [!NOTE]
>
> Jooosi Icon is an open-source WordPress plugin by [Jooosi](https://jooo.si). Consider sponsoring us to support continued development.

## Intro

Add beautiful icons to your WordPress site with seamless integration across Gutenberg, Elementor, Bricks, Breakdance, LiveCanvas, or anywhere with the `<jooosi-icon>` web component.

### Features

- ✅ **Multi-source Icon System**: Upload custom icons, use bundled icons, or access 200,000+ Iconify icons
- 🎨 **Icon Search & Discovery**: Powerful search across all icon sources with intelligent caching
- ⚡️ **Server-Side Rendering (SSR)**: Icons pre-rendered on server for instant display
- 🚀 **Smart Caching**: Multi-layer caching (memory, filesystem, IndexedDB) for optimal performance
- 📦 **Web Component**: Use `<jooosi-icon>` custom element anywhere in your theme or content
- 🔒 **Secure**: SVG sanitization prevents XSS attacks on uploaded icons
- 🏗️ **Modern Architecture**: Built with PHP 8.0+ attributes, Symfony DI, and auto-discovery

### Integrations

Seamless integration with the most popular visual/page builders and custom field plugins:

* [Gutenberg](https://wordpress.org/gutenberg/) / Block Editor — Custom Icon block with live preview
* [Elementor](https://be.elementor.com/visit/?bta=209150&brand=elementor) — Native widget with Elementor controls
* [Bricks](https://bricksbuilder.io/?ref=windpress) — Native element with full theme compatibility
* [Breakdance](https://breakdance.com/ref/165/) — Element Studio integration with SSR
* [LiveCanvas](https://livecanvas.com/?ref=4008) — Custom block with panel controls
* [Advanced Custom Fields (ACF)](https://www.advancedcustomfields.com/) — Custom field type with multiple return formats
* [Etch](https://etchwp.com?aff=bce0d1ab) — Web Component with icon picker
* [Divi 5](https://www.elegantthemes.com/affiliates/idevaffiliate.php?id=47622) — Native module

## Icon Sources

### Local Icons (Custom Uploads)

Upload your own SVG icons via admin page or manually place them in the storage directory:

- Format: `local:icon-name` or `custom-set:icon-name`
- Organized in sets via subdirectories
- SVG sanitization for security
- Manual upload (optional): Place SVG files in the storage directory

**Storage**: `wp-content/uploads/jooosi-icon/local/` (the former `wp-content/uploads/omni-icon/` directory is migrated automatically, with fallback when a rename is unavailable)

### Third-party Icons

Third-party plugins can register an icon source with the `jooosi-icon/service/icon:sources` filter. These sources are checked after uploaded local icons and before Iconify icons.

For a directory of SVG files, use the same directory structure as the built-in bundled source:

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

With `icons/logo.svg` in that directory, the icon is available as `acme:logo` in the icon picker and anywhere Jooosi Icon renders icons. The filter is evaluated lazily on the first registry, collection, or search request, so it can be registered from a theme's `functions.php`; register it before the first icon use.

### Bundle Icons

Pre-packaged icons included with the plugin:

- Prefix: `jooosi:icon-name` (`omni:icon-name` remains supported for existing content)
- Sponsored icons

**Storage**: `/svg` directory in plugin folder

### Iconify Icons

Access to 150+ icon collections with 200,000+ icons:

- Material Design Icons (mdi)
- Font Awesome (fa, fa6-brands, fa6-regular, fa6-solid)
- Bootstrap Icons (bi)
- Hero Icons (heroicons)
- Lucide (lucide)
- And 150+ more collections

Visit [Iconify](https://icon-sets.iconify.design/) to browse available icons.

## Usage

### Web Component

Use the `<jooosi-icon>` web component directly in your theme or content:

```html
<jooosi-icon name="mdi:home"></jooosi-icon>
<jooosi-icon name="local:my-logo" width="64" height="64"></jooosi-icon>
<jooosi-icon name="jooosi:windpress" color="#3b82f6"></jooosi-icon>
<jooosi-icon name="fa6-solid:heart"></jooosi-icon>
```

Existing `<omni-icon>` markup remains fully supported as a backward-compatible alias.

**Features**:
- Server-side rendering for instant display
- Lazy loading with smart caching
- Attribute reactivity (changes update in real-time)
- Error handling with visual indicators

### Page Builders

**Gutenberg**:
- In the block editor, add a new "Jooosi Icon" block
- Click the icon picker to browse or search icons

**Elementor**:
- Add the "Jooosi Icon" widget
- Click "Browse Icons" to open the icon picker

**Bricks**:
- Add the "Jooosi Icon" element
- Click "Browse Icons" to open the icon picker

**Breakdance**:
- Add the "Jooosi Icon" element
- Click "Browse Icons" to open the icon picker

**LiveCanvas**:
- Add the "Jooosi Icon" element

**Advanced Custom Fields (ACF)**:
- Create or edit an ACF Field Group
- Add a new field and select "Jooosi Icon" as the field type
- Configure return format (string, array, or HTML)

**Etch**:
- Add the "jooosi-icon" element from the element menu at the bottom of the screen
- Browse or search icons in the icon picker
- Confirm selection to copy the code to clipboard
- Paste the copied code into the HTML editor

## Performance

- ✅ **Lazy Loading**: Web components loaded on-demand
- ✅ **Multi-layer Caching**: Memory → Filesystem → IndexedDB
- ✅ **SSR Support**: Icons pre-rendered on server for instant display
- ✅ **Smart Invalidation**: mtime-based cache invalidation

## Security

All SVGs are validate for the MIME type and sanitized to prevent XSS (enshrined/svg-sanitize)

## Development

Want to contribute or customize the plugin? Check out our [DEVELOPMENT.md](./DEVELOPMENT.md) guide for detailed information about:

- Setting up your development environment
- Understanding the architecture
- Contributing guidelines

## Sponsors

If you like this project, please consider supporting us by becoming a sponsor. Your sponsorship helps us maintain and improve **all our free WordPress plugins**, not just Jooosi Icon.

### Sponsorship Benefits

As a sponsor, you'll receive benefits across our entire plugin ecosystem:

- 🎨 **Your product/brand icon SVG bundled** in Jooosi Icon releases (via `jooosi:your-brand` prefix)
- 📝 **Your logo and link featured** in the README of **all our current and future free plugins**
- ⭐ **Recognition** in the admin area sponsor section across **all our plugins**
- 💼 **Direct exposure** to thousands of WordPress developers and designers using our plugin ecosystem
- 🌟 **Unified sponsor listing** - one sponsorship covers your presence in our entire plugin family

Your icons will be permanently accessible to all Jooosi Icon users through the `jooosi:` prefix, and your brand will gain visibility across our growing collection of WordPress tools.

**Supporting one plugin means supporting all our open-source efforts!**

### Become a Sponsor

- [GitHub Sponsors](https://github.com/sponsors/suasgn)
- [Ko-fi](https://ko-fi.com/Q5Q75XSF7)

Thank you to our amazing sponsors who support all our plugin development! 🥰🫰🫶

<!-- Sponsor logos will be displayed here -->

<p align="center">
  <a href="https://wind.press" title="WindPress - The Tailwind CSS integration plugin for WordPress"><kbd><img src="./svg/windpress.svg" width="80" height="80" alt="WindPress"/></kbd></a>
  <a href="https://livecanvas.com" title="LiveCanvas - The Professional Page Builder for WordPress"><kbd><img src="https://livecanvas.com/wp-content/uploads/2022/06/favicon_big.png" width="80" height="80" alt="LiveCanvas"/></kbd></a>
</p>

<!-- --- -->

<!-- *Interested in sponsoring? Contact us to discuss custom sponsorship packages tailored to your needs.* -->

## Credits

- Built with [Symfony UX Icons](https://github.com/symfony/ux-icons)
- Powered by [Iconify](https://iconify.design/)
- SVG sanitization by [enshrined/svg-sanitize](https://github.com/darylldoyle/svg-sanitizer)

## Support

For issues, questions, or feature requests, please open an issue on GitHub.
