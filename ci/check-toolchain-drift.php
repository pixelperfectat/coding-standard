#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * A pinned toolchain ages silently: a caret on a 0.x version never admits the next minor, so
 * `composer update` stays green while the pinned tool falls a year behind.
 *
 * Resolve the newest toolchain that installs together (every direct constraint loosened to `*`),
 * then ask, for each tool that resolves newer than installed, whether our own constraint is what
 * holds it back. If it is, we are drifting: exit 1. A tool that stays put because another tool
 * pins it (phpcs 4 vs magento-coding-standard) is not ours to move and is not reported.
 *
 * Run from the package root after `composer update`.
 */

$root = rtrim($argv[1] ?? getcwd(), '/');

function composer(string $dir, string ...$args): string
{
    $cmd = 'composer --no-interaction --working-dir=' . escapeshellarg($dir);
    foreach ($args as $arg) {
        $cmd .= ' ' . escapeshellarg($arg);
    }
    return (string)shell_exec($cmd . ' 2>/dev/null');
}

function normalize(string $version): string
{
    return ltrim($version, 'v');
}

$manifest = json_decode((string)file_get_contents($root . '/composer.json'), true);
$self = $manifest['name'];

$installed = [];
foreach (json_decode(composer($root, 'show', '--direct', '--format=json'), true)['installed'] ?? [] as $p) {
    $installed[$p['name']] = normalize($p['version']);
}

$scratch = sys_get_temp_dir() . '/toolchain-drift-' . getmypid();
mkdir($scratch);
$loose = $manifest;
foreach (array_keys($loose['require']) as $name) {
    if ($name !== 'php') {
        $loose['require'][$name] = '*';
    }
}
file_put_contents($scratch . '/composer.json', json_encode($loose, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
composer($scratch, 'update', '--no-install', '--no-scripts', '--no-plugins');
$lock = json_decode((string)@file_get_contents($scratch . '/composer.lock'), true);
shell_exec('rm -rf ' . escapeshellarg($scratch));
if (!is_array($lock)) {
    fwrite(STDERR, "could not resolve the loosened toolchain\n");
    exit(2);
}

$drift = [];
foreach ($lock['packages'] as $p) {
    $name = $p['name'];
    $newest = normalize($p['version']);
    if (!isset($installed[$name]) || $newest === $installed[$name]) {
        continue;
    }
    if (preg_match('/^' . preg_quote($self, '/') . '\s+\S+\s+requires\s/m', composer($root, 'why-not', $name, $newest))) {
        $drift[] = sprintf('%s %s -> %s', $name, $installed[$name], $newest);
    }
}

foreach ($drift as $line) {
    echo "DRIFT: $line -- installable with the rest of the toolchain, held back by our constraint\n";
}
if ($drift !== []) {
    exit(1);
}
echo "toolchain is current\n";
