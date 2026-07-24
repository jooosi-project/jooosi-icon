=== Omni Icon - Modern SVG icon library for WordPress ===
Contributors: suabahasa
Donate link: https://ko-fi.com/Q5Q75XSF7
Tags: icons, iconify, gutenberg, svg, icon block
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.19
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modern SVG icon library for WordPress with support for custom uploads and 200,000+ Iconify icons across block editor, page builders, and themes.

== Description ==

### Omni Icon: Modern icon management solution for WordPress

Omni Icon is a comprehensive icon management solution that seamlessly integrates icons across the WordPress ecosystem. Upload custom icons, use bundled icons, or access 200,000+ icons from Iconify with support for Gutenberg, Elementor, Bricks, Breakdance, and LiveCanvas.

### Features

Omni Icon is packed with features designed to make icon management effortless:

* **Multi-source Icon System**: Upload custom SVG icons, use pre-bundled icons, or access 200,000+ Iconify icons
* **Icon Search & Discovery**: Powerful search across all icon sources with intelligent caching
* **Server-Side Rendering (SSR)**: Icons pre-rendered on server for instant display and optimal performance
* **Smart Caching**: Multi-layer caching (memory, filesystem, IndexedDB) for blazing fast load times
* **Web Component**: Use `<omni-icon>` custom element anywhere in your theme or content
* **Secure**: SVG sanitization prevents XSS attacks on uploaded icons
* **Modern Architecture**: Built with PHP 8.0+ attributes, Symfony DI, and auto-discovery
* **Lightweight**: Small footprint with lazy loading won't slow down your site

Visit [our GitHub repository](https://github.com/nabasa-dev/omni-icon) for more information.

### Seamless Integration

Omni Icon works perfectly with the most popular visual/page builders:

* [Gutenberg](https://wordpress.org/gutenberg) / Block Editor — Custom Icon block with live preview
* [Elementor](https://be.elementor.com/visit/?bta=209150&brand=elementor) — Native widget with Elementor controls
* [Bricks](https://bricksbuilder.io/) — Native element with full theme compatibility
* [Breakdance](https://breakdance.com/ref/165/) — Element Studio integration with SSR
* [LiveCanvas](https://livecanvas.com/?ref=4008) — Custom block with panel controls
* [Advanced Custom Fields (ACF)](https://www.advancedcustomfields.com/) — Custom field type for icon selection
* [Etch](https://etchwp.com?aff=bce0d1ab) — Web Component with icon picker
* [Divi 5](https://www.elegantthemes.com/affiliates/idevaffiliate.php?id=47622) — Native module
* More integrations coming soon!

### Icon Sources

**Local Icons (Custom Uploads)**
Upload your own SVG icons and organize them in custom sets. All uploads are sanitized for security.

**Bundle Icons**
Pre-packaged icons included with the plugin, including sponsor logos and commonly used icons.

**Iconify Icons**
Access to 150+ icon collections with 200,000+ icons including:

* Material Design Icons (mdi)
* Font Awesome (fa6-brands, fa6-regular, fa6-solid)
* Bootstrap Icons (bi)
* Hero Icons (heroicons)
* Lucide (lucide)
* And 150+ more collections

Browse available icons at [Iconify](https://icon-sets.iconify.design/)

### Usage

**Gutenberg**:

- In the block editor, add a new "Omni Icon" block
- Click the icon picker to browse or search icons

**Elementor**:

- Add the "Omni Icon" widget
- Click "Browse Icons" to open the icon picker

**Bricks**:

- Add the "Omni Icon" element
- Click "Browse Icons" to open the icon picker

**Breakdance**:

- Add the "Omni Icon" element
- Click "Browse Icons" to open the icon picker

**LiveCanvas**:

- Add the "Omni Icon" element

**Advanced Custom Fields (ACF)**:

- Create or edit an ACF Field Group
- Add a new field and select "Omni Icon" as the field type
- Configure return format (string, array, or HTML)

**Etch**:

- Add the "omni-icon" Element from the menu element on the bottom of screen
- Browse or search icons in the icon picker
- Confirm selection to copy the code to clipboard 
- Paste the copied code into the HTML editor

**Web Component Usage**

Use the `<omni-icon>` web component directly in your theme or content:

`<omni-icon name="mdi:home"></omni-icon>`
`<omni-icon name="local:my-logo" width="64" height="64"></omni-icon>`
`<omni-icon name="fa6-solid:heart" color="#3b82f6"></omni-icon>`

### Performance & Security

* **Lazy Loading**: Web components loaded on-demand
* **Multi-layer Caching**: Memory → Filesystem → IndexedDB
* **SSR Support**: Icons pre-rendered on server for instant display
* **SVG Sanitization**: All uploaded SVGs sanitized to prevent XSS
* **MIME Type Validation**: Server-side validation of uploaded files

= Love Omni Icon? =
- Give a [5-star review](https://wordpress.org/support/plugin/omni-icon/reviews/)
- Join our [Facebook Group](https://www.facebook.com/groups/1142662969627943)
- Sponsor us on [GitHub](https://github.com/sponsors/suasgn) or [Ko-fi](https://ko-fi.com/Q5Q75XSF7)

= Credits =
- Built with [Symfony UX Icons](https://github.com/symfony/ux-icons)
- Powered by [Iconify](https://iconify.design/)
- SVG sanitization by [enshrined/svg-sanitize](https://github.com/darylldoyle/svg-sanitizer)

Affiliate Disclosure: This readme.txt may contain affiliate links. If you decide to make a purchase through these links, we may earn a commission at no extra cost to you.

== Screenshots ==

1. The Omni Icon block in the Gutenberg editor
2. Opening the icon picker modal in Gutenberg
3. Searching for icons in the icon picker modal
4. Customizing icon size and color in the block settings
5. Managing uploaded local icons in the Omni Icon settings page

== Frequently Asked Questions ==

= What icon sources does Omni Icon support? =

Omni Icon supports three icon sources:
1. Local Icons - Upload your own custom SVG icons
2. Bundle Icons - Pre-packaged icons included with the plugin
3. Iconify Icons - Access to 200,000+ icons from 150+ collections

= How do I use icons in my theme? =

You can use the `<omni-icon>` web component directly in your theme templates:
`<omni-icon name="mdi:home"></omni-icon>`

The component supports many attributes like width, height, and color for customization.

= Which page builders are supported? =

Omni Icon currently supports:
- Gutenberg / Block Editor
- Elementor
- Bricks
- Breakdance
- LiveCanvas
- Etch
- And more coming soon!

All integrations include icon picker modals for easy icon selection.

= Are uploaded SVG icons safe? =

Yes! All uploaded SVG files are sanitized using enshrined/svg-sanitize to prevent XSS attacks and security vulnerabilities.

= Does Omni Icon require an internet connection? =

For local and bundle icons, no internet connection is required. Iconify icons are fetched from the Iconify API and cached locally for optimal performance.

= What is Server-Side Rendering (SSR)? =

SSR means icons are pre-rendered on the server and sent as inline SVG in the HTML. This provides instant display without JavaScript required, improving performance and SEO.

= Can I use Omni Icon with any WordPress theme? =

Yes, Omni Icon is compatible with any WordPress theme. You can use the web component, Gutenberg block, or page builder integrations with any theme.

= What 3rd Party services used? =

Omni Icon uses the Iconify API to fetch icons from their extensive icon collections.

= Where can I find the source code for compiled/minified files? =

The complete source code, including uncompiled versions of all JavaScript and CSS files in the `dist/` directory, is publicly available on [GitHub](https://github.com/nabasa-dev/omni-icon/)

== Changelog ==

= 1.0.19 - 2026-07-24 =

**Fixed**

* Resolve dynamic data tags in the Bricks Omni Icon element's Icon Name setting ([WordPress.org support thread](https://wordpress.org/support/topic/returning-dynamic-icon-name-in-bricks-builder/))

= 1.0.18 - 2026-06-05 =

**Fixed**

* CSS stylesheets loaded as JS in builder integrations [#9](https://github.com/nabasa-dev/omni-icon/issues/9)

= 1.0.17 - 2026-03-25 =

**Changed**

* Migrate the build system to [`@nabasa/vp-wp`](https://github.com/nabasa-dev/vp-wp) for improved performance and better WordPress integration

= 1.0.16 - 2026-03-18 =

**Added**

* [Divi 5](https://www.elegantthemes.com/affiliates/idevaffiliate.php?id=47622) integration

**Fixed**

* Release build Symfony DI ParameterBag scoping issue

= 1.0.15 - 2026-01-30 =

**Changed**

* Update readme file

= 1.0.14 - 2026-01-14 =

**Added**

* [Etch](https://etchwp.com?aff=bce0d1ab) integration

**Changed**

* Increase webcomponent concurrent request limit from 4 to 16 for faster icon loading

**Fixed**

* Missing assets entrypoint in Vite config for ACF integration

= 1.0.13 - 2026-01-13 =

**Fixed**

* Redis compatibility issue

= 1.0.12 - 2026-01-12 =

**Fixed**

* PHP 8.1 compatibility issues

= 1.0.11 - 2026-01-12 =

**Added**

* Blueprint for WordPress.org plugin repository

= 1.0.9 - 2026-01-12 =

**Changed**

* Update readme file

= 1.0.7 - 2026-01-12 =

**Fixed**

* [ACF] Discovery compatibility issue

= 1.0.4 - 2026-01-09 =

**Added**

* ACF (Advanced Custom Fields) integration [#4](https://github.com/nabasa-dev/omni-icon/issues/4)

**Fixed**

* Modal footer visibility on laptop screens - Confirm/Cancel buttons now visible without requiring full-screen mode [#3](https://github.com/nabasa-dev/omni-icon/issues/3)
* WordPress.org plugin repository compliance fixes

= 1.0.3 - 2026-01-08 =

**Fixed**

* WordPress.org plugin repository compliance fixes

= 1.0.2 - 2025-12-31 =

**Added**

* Multiple and drag-and-drop icon upload support - upload multiple SVG files at once
* Multi-select icons with keyboard shortcuts (Ctrl/Cmd+Click, Shift+Click) and mouse drag area selection for bulk operations

= 1.0.1 - 2025-12-31 =

**Fixed**

* WordPress.org plugin repository compliance fixes

= 1.0.0 - 2025-12-31 =

**Added**

* 🐣 Initial release.

[See changelog for all versions.](https://github.com/nabasa-dev/omni-icon/blob/main/CHANGELOG.md)