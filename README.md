# pixelperfectat/coding-standard

Shared coding standard + QA toolchain for PixelPerfect Magento modules. Bump this package
(one place) and every module inherits the change.

## What it provides
- **`PixelPerfect` phpcs ruleset** (`PixelPerfect/ruleset.xml`) — extends `Magento2`, with the
  minimal-comments tuning (no forced `@param`/`@return` docblock noise).
- **Shared phpstan config** (`phpstan.neon`) — level 6 + the `bitexpert/phpstan-magento`
  bootstrap, resolved via `%currentWorkingDirectory%` so it works whether the module is the
  composer root (CI) or a local clone.
- **The pinned QA tools** as dependencies (phpcs, magento-coding-standard, phpstan,
  bitexpert/phpstan-magento, parallel-lint, composer-normalize) — so consuming modules only
  add this one package (+ their own `phpunit`).

## Use in a module
`composer.json`:
```json
"require-dev": { "pixelperfectat/coding-standard": "^0.1.0", "phpunit/phpunit": "^9.6" }
```
`phpcs.xml`:
```xml
<ruleset name="Module"><file>.</file>
  <exclude-pattern>*/Test/*</exclude-pattern><exclude-pattern>*/vendor/*</exclude-pattern>
  <exclude-pattern>*/docs/*</exclude-pattern><exclude-pattern>*/view/*</exclude-pattern>
  <rule ref="PixelPerfect"/>
</ruleset>
```
`phpstan.neon.dist`:
```neon
includes:
    - vendor/pixelperfectat/coding-standard/phpstan.neon
parameters:
    paths: [ ... module dirs ... ]
    # module-specific excludePaths / ignoreErrors here
```

Consumed via a git VCS `repositories` entry (auth `CI_JOB_TOKEN`) until the GitLab Composer
Registry is set up.
