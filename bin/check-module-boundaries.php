#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * A module may only name modules it declares a dependency on.
 *
 * Dependency direction is knowledge direction. A child may name its parent, because it requires it.
 * A parent must never name its child: it does not require it, it must ship and be sold standalone,
 * and naming a consumer couples the two in documentation even when the code is clean.
 *
 * Exit 1 on violation. Run from a module root.
 */

$root = $argv[1] ?? getcwd();
$composerPath = $root . '/composer.json';
if (!is_file($composerPath)) {
    fwrite(STDERR, "no composer.json at $root\n");
    exit(2);
}
$composer = json_decode((string)file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);

/**
 * Every way a package can be written: package name, repo name, slug, StudlyCase, Magento module name.
 * Comparison is case-insensitive because a slug cannot carry internal capitals -- "activecampaign"
 * is the same module as "ActiveCampaign", and "cart-recovery-graphql" as "CartRecoveryGraphQl".
 */
$aliases = static function (string $package): array {
    if ($package === '') {
        return [];
    }
    $repo = preg_replace('#^.*/#', '', $package);
    $short = preg_replace('#^(magento2-)?module-#', '', $repo);
    $studly = str_replace(' ', '', ucwords(str_replace('-', ' ', $short)));
    return array_unique([$package, $repo, $short, $studly, 'PixelPerfect_' . $studly]);
};

$own = $aliases($composer['name'] ?? '');
// registration.php is authoritative for this module's own Magento name, capitals included.
$registration = $root . '/registration.php';
if (is_file($registration) && preg_match('/[\'"]([A-Za-z]+_[A-Za-z]+)[\'"]/', (string)file_get_contents($registration), $m)) {
    $own[] = $m[1];
}
// so is the psr-4 namespace
foreach (array_keys($composer['autoload']['psr-4'] ?? []) as $ns) {
    $parts = array_values(array_filter(explode('\\', $ns)));
    if (count($parts) >= 2) {
        $own[] = $parts[0] . '_' . $parts[1];
    }
}
/**
 * A dependency's real Magento module name, read from the installed package rather than guessed.
 *
 * The slug and the module name do not have to agree: "magento2-module-eu-vat-exemption" ships
 * "PixelPerfect_EuVat". Guessing from the slug alone rejects a dependency that is properly
 * declared, so ask the package itself when it is installed. Vendor directories are the only
 * place that answer lives; when the package is not installed the guess still applies, which is
 * no worse than before.
 */
$installedNames = static function (string $package) use ($root): array {
    $dir = $root . '/vendor/' . $package;
    $names = [];

    $registration = $dir . '/registration.php';
    if (is_file($registration)
        && preg_match('/[\'"]([A-Za-z]+_[A-Za-z0-9]+)[\'"]/', (string)file_get_contents($registration), $m)
    ) {
        $names[] = $m[1];
    }

    $manifest = $dir . '/composer.json';
    if (is_file($manifest)) {
        $decoded = json_decode((string)file_get_contents($manifest), true);
        foreach (array_keys($decoded['autoload']['psr-4'] ?? []) as $ns) {
            $parts = array_values(array_filter(explode('\\', $ns)));
            if (count($parts) >= 2) {
                $names[] = $parts[0] . '_' . $parts[1];
            }
        }
    }

    return $names;
};

$allowed = $own;
foreach (array_merge(array_keys($composer['require'] ?? []), array_keys($composer['require-dev'] ?? [])) as $dep) {
    $allowed = array_merge($allowed, $aliases($dep), $installedNames($dep));
}
$allowed = array_unique(array_filter($allowed, static fn($a) => $a !== ''));
$allowedLower = array_map('strtolower', $allowed);

/**
 * Only the in-house family is policed. Third-party vendors are declared in composer.json anyway,
 * and their names appear legitimately in prose about the platform.
 */
$familyPattern = '#(?:pixelperfectat/[a-z0-9\-]+|PixelPerfect_[A-Za-z]+|magento2-module-[a-z0-9\-]+)#';

$scan = [];
foreach (new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $f): bool {
            $n = $f->getFilename();
            if ($f->isDir()) {
                return !in_array($n, ['vendor', '.git', 'generated', 'Test', '.phpunit.cache'], true);
            }
            return (bool)preg_match('/\.(php|md|xml)$/', $n);
        }
    )
) as $file) {
    $scan[] = $file->getPathname();
}

$violations = [];
foreach ($scan as $path) {
    $rel = ltrim(str_replace($root, '', $path), '/');
    foreach (file($path) as $i => $line) {
        if (!preg_match_all($familyPattern, $line, $m)) {
            continue;
        }
        foreach (array_unique($m[0]) as $name) {
            if (in_array(strtolower($name), $allowedLower, true)) {
                continue;
            }
            // Tolerate an alias that only differs by the studly/slug spelling of something allowed.
            $violations[] = sprintf('%s:%d  names "%s"', $rel, $i + 1, $name);
        }
    }
}

if ($violations === []) {
    echo "module-boundaries: OK — names only itself and its declared dependencies\n";
    exit(0);
}

fwrite(STDERR, "module-boundaries: FAILED\n\n");
fwrite(STDERR, "A module may only name modules it declares a dependency on.\n");
fwrite(STDERR, "A parent must never name a child: it does not require it, and it must stand alone.\n\n");
foreach (array_unique($violations) as $v) {
    fwrite(STDERR, "  $v\n");
}
fwrite(STDERR, "\nEither add the dependency to composer.json, or remove the reference.\n");
exit(1);
