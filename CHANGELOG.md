# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Add the `jooosi-icon/service/icon:sources` filter for third-party icon sources.

### Changed
- Update WordPress compatibility to WordPress 7.1.
- Rebrand the plugin, PHP namespace, assets, integrations, and web component to Jooosi Icon.
- Move canonical uploaded-icon storage to `wp-content/uploads/jooosi-icon/` with automatic migration and a legacy-directory fallback.

### Deprecated
- Forward pre-rebrand filters and actions through WordPress deprecated-hook APIs.
- Keep the former `omni:` icon prefix and `<omni-icon>` markup as compatibility aliases.

### Fixed
- Fix Gutenberg icon rendering and CSS loading in the editor canvas [#5](https://github.com/jooosi-project/jooosi-icon/issues/5) [#8](https://github.com/jooosi-project/jooosi-icon/discussions/8)

## [1.0.19] - 2026-07-24

### Fixed
- Resolve dynamic data tags in the Bricks Jooosi Icon element's Icon Name setting ([WordPress.org support thread](https://wordpress.org/support/topic/returning-dynamic-icon-name-in-bricks-builder/))

## [1.0.18] - 2026-06-05

### Fixed
- CSS stylesheets loaded as JS in builder integrations [#9](https://github.com/jooosi-project/jooosi-icon/issues/9)

## [1.0.17] - 2026-03-25

### Changed
- Migrate the build system to [`@nabasa/vp-wp`](https://github.com/nabasa-dev/vp-wp) for improved performance and better WordPress integration

## [1.0.16] - 2026-03-18

### Added
- [Divi 5](https://www.elegantthemes.com/affiliates/idevaffiliate.php?id=47622) integration

### Fixed
- Release build Symfony DI ParameterBag scoping issue

## [1.0.15] - 2026-01-30

### Changed
- Update readme file

## [1.0.14] - 2026-01-14

### Added
- [Etch](https://etchwp.com?aff=bce0d1ab) integration

### Changed
- Increase webcomponent concurrent request limit from 4 to 16 for faster icon loading

### Fixed
- Missing assets entrypoint in Vite config for ACF integration

## [1.0.13] - 2026-01-13

### Fixed
- Redis compatibility issue

## [1.0.12] - 2026-01-12

### Fixed
- PHP 8.1 compatibility issues

## [1.0.11] - 2026-01-12

### Added
- Blueprint for WordPress.org plugin repository

## [1.0.9] - 2026-01-12

### Changed
- Update readme file

## [1.0.7] - 2026-01-12

### Fixed
- [ACF] Discovery compatibility issue

## [1.0.4] - 2026-01-09

### Added
- ACF (Advanced Custom Fields) integration [#4](https://github.com/jooosi-project/jooosi-icon/issues/4)

### Fixed
- Modal footer visibility on laptop screens - Confirm/Cancel buttons now visible without requiring full-screen mode [#3](https://github.com/jooosi-project/jooosi-icon/issues/3)
- WordPress.org plugin repository compliance fixes

## [1.0.3] - 2026-01-08

### Fixed
- WordPress.org plugin repository compliance fixes

## [1.0.2] - 2025-12-31

### Added
- Multiple and drag-and-drop icon upload support - upload multiple SVG files at once
- Multi-select icons with keyboard shortcuts (Ctrl/Cmd+Click, Shift+Click) and mouse drag area selection for bulk operations

## [1.0.1] - 2025-12-31

### Fixed
- WordPress.org plugin repository compliance fixes

## [1.0.0] - 2025-12-31

### Added
- 🐣 Initial release.

[unreleased]: https://github.com/jooosi-project/jooosi-icon/compare/1.0.19...HEAD
[1.0.19]: https://github.com/jooosi-project/jooosi-icon/compare/1.0.18...1.0.19
[1.0.18]: https://github.com/jooosi-project/jooosi-icon/compare/1.0.17...1.0.18
[1.0.17]: https://github.com/jooosi-project/jooosi-icon/compare/1.0.16...1.0.17
[1.0.16]: https://github.com/jooosi-project/jooosi-icon/compare/1.0.15...1.0.16
[1.0.15]: https://github.com/jooosi-project/jooosi-icon/compare/1.0.14...1.0.15
[1.0.14]: https://github.com/jooosi-project/jooosi-icon/compare/1.0.13...1.0.14
[1.0.13]: https://github.com/jooosi-project/jooosi-icon/compare/1.0.12...1.0.13
[1.0.12]: https://github.com/jooosi-project/jooosi-icon/compare/1.0.11...1.0.12
[1.0.11]: https://github.com/jooosi-project/jooosi-icon/compare/1.0.10...1.0.11
[1.0.9]: https://github.com/jooosi-project/jooosi-icon/compare/1.0.8...1.0.9
[1.0.7]: https://github.com/jooosi-project/jooosi-icon/compare/1.0.6...1.0.7
[1.0.4]: https://github.com/jooosi-project/jooosi-icon/compare/1.0.3...1.0.4
[1.0.3]: https://github.com/jooosi-project/jooosi-icon/compare/1.0.2...1.0.3
[1.0.2]: https://github.com/jooosi-project/jooosi-icon/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/jooosi-project/jooosi-icon/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/jooosi-project/jooosi-icon/compare/main...1.0.0
