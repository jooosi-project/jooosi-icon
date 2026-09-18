=== Jooosi Icon - Modern SVG icon library for WordPress ===
Contributors: suasgn, suabahasa
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

### Jooosi Icon: Modern icon management solution for WordPress

Jooosi Icon is a comprehensive icon management solution that seamlessly integrates icons across the WordPress ecosystem. Upload custom icons, use bundled icons, or access 200,000+ icons from Iconify with support for Gutenberg, Elementor, Bricks, Breakdance, and LiveCanvas.

### Features

Jooosi Icon is packed with features designed to make icon management effortless:

* **Multi-source Icon System**: Upload custom SVG icons, use pre-bundled icons, or access 200,000+ Iconify icons
* **Icon Search & Discovery**: Powerful search across all icon sources with intelligent caching
* **Server-Side Rendering (SSR)**: Icons pre-rendered on server for instant display and optimal performance
* **Smart Caching**: Multi-layer caching (memory, filesystem, IndexedDB) for blazing fast load times
* **Web Component**: Use `<jooosi-icon>` custom element anywhere in your theme or content
* **Secure**: SVG sanitization prevents XSS attacks on uploaded icons
* **Modern Architecture**: Built with PHP 8.0+ attributes, Symfony DI, and auto-discovery
* **Lightweight**: Small footprint with lazy loading won't slow down your site

Visit [our GitHub repository](https://github.com/jooosi-project/jooosi-icon) for more information.

### Seamless Integration

Jooosi Icon works perfectly with the most popular visual/page builders:

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
Pre-packaged icons included with the plugin, including sponsor logos and commonly used icons. Use the `jooosi:` prefix; the former `omni:` prefix remains supported for existing content.

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

**Web Component Usage**

Use the `<jooosi-icon>` web component directly in your theme or content:

`<jooosi-icon name="mdi:home"></jooosi-icon>`
`<jooosi-icon name="local:my-logo" width="64" height="64"></jooosi-icon>`
`<jooosi-icon name="fa6-solid:heart" color="#3b82f6"></jooosi-icon>`

Existing `<omni-icon>` markup remains fully supported as a backward-compatible alias.

### Performance & Security

* **Lazy Loading**: Web components loaded on-demand
* **Multi-layer Caching**: Memory → Filesystem → IndexedDB
* **SSR Support**: Icons pre-rendered on server for instant display
* **SVG Sanitization**: All uploaded SVGs sanitized to prevent XSS
* **MIME Type Validation**: Server-side validation of uploaded files

= Love Jooosi Icon? =
- Give a [5-star review](https://wordpress.org/support/plugin/omni-icon/reviews/)
- Join our [Facebook Group](https://www.facebook.com/groups/1142662969627943)
- Sponsor us on [GitHub](https://github.com/sponsors/suasgn) or [Ko-fi](https://ko-fi.com/Q5Q75XSF7)

= Credits =
- Built with [Symfony UX Icons](https://github.com/symfony/ux-icons)
- Powered by [Iconify](https://iconify.design/)
- SVG sanitization by [enshrined/svg-sanitize](https://github.com/darylldoyle/svg-sanitizer)

Affiliate Disclosure: This readme.txt may contain affiliate links. If you decide to make a purchase through these links, we may earn a commission at no extra cost to you.

== Screenshots ==

1. The Jooosi Icon block in the Gutenberg editor
2. Opening the icon picker modal in Gutenberg
3. Searching for icons in the icon picker modal
4. Customizing icon size and color in the block settings
5. Managing uploaded local icons in the Jooosi Icon settings page

== Frequently Asked Questions ==

= What icon sources does Jooosi Icon support? =

Jooosi Icon supports three icon sources:
1. Local Icons - Upload your own custom SVG icons
2. Bundle Icons - Pre-packaged icons included with the plugin
3. Iconify Icons - Access to 200,000+ icons from 150+ collections

= How do I use icons in my theme? =

You can use the `<jooosi-icon>` web component directly in your theme templates:
`<jooosi-icon name="mdi:home"></jooosi-icon>`

The component supports many attributes like width, height, and color for customization.

= Which page builders are supported? =

Jooosi Icon currently supports:
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

= Does Jooosi Icon require an internet connection? =

For local and bundle icons, no internet connection is required. Iconify icons are fetched from the Iconify API and cached locally for optimal performance.

= What is Server-Side Rendering (SSR)? =

SSR means icons are pre-rendered on the server and sent as inline SVG in the HTML. This provides instant display without JavaScript required, improving performance and SEO.

= Can I use Jooosi Icon with any WordPress theme? =

Yes, Jooosi Icon is compatible with any WordPress theme. You can use the web component, Gutenberg block, or page builder integrations with any theme.

= What 3rd Party services used? =

Jooosi Icon uses the Iconify API to fetch icons from their extensive icon collections.

= Where can I find the source code for compiled/minified files? =

The complete source code, including uncompiled versions of all JavaScript and CSS files in the `dist/` directory, is publicly available on [GitHub](https://github.com/jooosi-project/jooosi-icon/)

== Changelog ==
