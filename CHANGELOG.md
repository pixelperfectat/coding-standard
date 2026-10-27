# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- The package is released under the MIT license, matching the public modules that depend on it. It was marked `proprietary` while published on GitHub and Packagist

### Fixed
- `check-xml-class-refs` checked nothing in modules with a `src/` layout: it looked for `etc/` only at the repository root and printed "OK - no etc/ directory". It now reads `etc/` next to the `registration.php` that composer autoloads, falling back to the root
- `check-xml-class-refs` only recognised classes spelled `PixelPerfect\`. It now checks the module's own PSR-4 namespaces and the vendor namespace in either spelling, and accepts digits and underscores in class names
- `check-module-boundaries` rejected a module whose README or CHANGELOG mentioned `pixelperfectat/ci-templates`, which is never a composer dependency. The shared tooling packages (ci-templates, coding-standard) are now always allowed, since they are not modules

## [0.2.0] - 2026-10-23

### Added
- A weekly toolchain drift check: it resolves the newest toolchain that installs together and fails when a tool could move up but only this package's own constraint holds it back. A tool that another tool pins does not fail it (e0c5bb3)

### Changed
- **Breaking:** the toolchain moves to PHPStan 2, bitexpert/phpstan-magento 0.44 and magento-coding-standard 41. phpstan-magento now autoloads Magento's generated extension interfaces, so a module with an extensible entity passes PHPStan without an ignore entry. PHPStan 2 reports findings that 1.x did not; a module opts in by raising its constraint to `^0.2.0` (845dba7)

## [0.1.8] - 2026-09-25

### Fixed
- The module boundaries check rejected a dependency that was properly declared, whenever the package slug and the Magento module name differed; the installed package is now asked for its own name instead of it being guessed from the slug (fdc0a44)
