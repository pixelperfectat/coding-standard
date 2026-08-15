#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Every class a module names in its XML must actually exist.
 *
 * Magento resolves observers, plugins and DI arguments when the event or type is first needed -- at
 * runtime, in production, not at install or compile time. A class deleted while its wiring stays behind
 * therefore passes lint, phpcs, phpstan, phpunit and setup:di:compile, and fails on the one code path
 * that touches it. Exactly that shipped a checkout that aborted on every order.
 *
 * Exit 1 on a dangling reference. Run from a module root.
 */

$root = rtrim($argv[1] ?? getcwd(), '/');
if (!is_file("$root/composer.json")) {
    fwrite(STDERR, "no composer.json at $root\n");
    exit(2);
}
if (!is_dir("$root/etc")) {
    echo "xml-class-refs: OK - no etc/ directory\n";
    exit(0);
}

$composer = json_decode((string)file_get_contents("$root/composer.json"), true, 512, JSON_THROW_ON_ERROR);
$psr4 = $composer['autoload']['psr-4'] ?? [];

$xmlFiles = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/etc", FilesystemIterator::SKIP_DOTS)) as $f) {
    if (preg_match('/\.xml$/', $f->getFilename())) {
        $xmlFiles[] = $f->getPathname();
    }
}

/** virtualType names are declared in XML, not autoloaded -- they are not missing classes. */
$virtual = [];
foreach ($xmlFiles as $path) {
    if (preg_match_all('#<virtualType[^>]*name="([^"]+)"#', (string)file_get_contents($path), $m)) {
        foreach ($m[1] as $name) {
            $virtual[$name] = true;
        }
    }
}

/** Autoload maps from vendor/, so classes from declared dependencies resolve too. */
$vendorPsr4 = [];
if (is_file("$root/vendor/composer/autoload_psr4.php")) {
    $vendorPsr4 = require "$root/vendor/composer/autoload_psr4.php";
}

$resolves = static function (string $class) use ($root, $psr4, $vendorPsr4): bool {
    foreach ($psr4 as $ns => $dir) {
        $prefix = rtrim($ns, '\\') . '\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }
        $rel = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file(rtrim("$root/$dir", '/') . '/' . $rel)) {
            return true;
        }
    }
    foreach ($vendorPsr4 as $ns => $dirs) {
        if (strncmp($class, $ns, strlen($ns)) !== 0) {
            continue;
        }
        foreach ((array)$dirs as $dir) {
            $rel = str_replace('\\', '/', substr($class, strlen($ns))) . '.php';
            if (is_file("$dir/$rel")) {
                return true;
            }
        }
    }
    return false;
};

$bad = [];
foreach ($xmlFiles as $path) {
    $rel = ltrim(str_replace($root, '', $path), '/');
    foreach (file($path) as $i => $line) {
        if (!preg_match_all('#(?:instance|class|type)="(PixelPerfect\\\\[A-Za-z\\\\]+)"|<item[^>]*xsi:type="object"[^>]*>\s*(PixelPerfect\\\\[A-Za-z\\\\]+)#', $line, $m)) {
            continue;
        }
        $names = array_filter(array_merge($m[1], $m[2]));
        foreach (array_unique($names) as $class) {
            if (isset($virtual[$class]) || $resolves($class)) {
                continue;
            }
            $bad[] = sprintf('%s:%d  %s', $rel, $i + 1, $class);
        }
    }
}

if ($bad === []) {
    echo "xml-class-refs: OK - every class named in etc/ resolves\n";
    exit(0);
}

fwrite(STDERR, "xml-class-refs: FAILED\n\n");
fwrite(STDERR, "These classes are wired in XML but do not exist. Magento resolves them at runtime,\n");
fwrite(STDERR, "so this fails on a live request rather than here -- a checkout, a cron, an event.\n\n");
foreach (array_unique($bad) as $b) {
    fwrite(STDERR, "  $b\n");
}
fwrite(STDERR, "\nEither restore the class or remove the wiring.\n");
exit(1);
