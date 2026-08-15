#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Magento wraps these for a reason: DI-swappability, store and locale scope, testability, and consistent
 * behaviour under cron and consumer contexts where there is no storefront request. Using the native
 * function bypasses all of that, and no other gate notices: it is valid PHP, it passes phpcs, and phpstan
 * has no opinion about it.
 *
 * Test code is exempt -- a test may build a fixture however it likes.
 *
 * Exit 1 on a violation. Run from a module root.
 */

$root = rtrim($argv[1] ?? getcwd(), '/');

/** native function => what to use instead */
$banned = [
    'json_encode'  => 'Magento\Framework\Serialize\SerializerInterface::serialize()',
    'json_decode'  => 'Magento\Framework\Serialize\SerializerInterface::unserialize()',
    'strtotime'    => 'Magento\Framework\Stdlib\DateTime\DateTimeFactory + \DateInterval',
    'uniqid'       => 'Magento\Framework\Math\Random::getRandomString()',
    'mt_rand'      => 'Magento\Framework\Math\Random::getRandomString()',
    'curl_init'    => 'Magento\Framework\HTTP\ClientInterface',
    'file_get_contents' => 'Magento\Framework\Filesystem',
    'file_put_contents' => 'Magento\Framework\Filesystem',
];

$skipDirs = ['vendor', '.git', 'generated', 'Test', '.phpunit.cache'];

$files = [];
if (is_dir($root)) {
    foreach (new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $f) use ($skipDirs): bool {
                return $f->isDir() ? !in_array($f->getFilename(), $skipDirs, true)
                                   : (bool)preg_match('/\.php$/', $f->getFilename());
            }
        )
    ) as $f) {
        $files[] = $f->getPathname();
    }
}

$pattern = '/(?<![\w$>\\\\])(' . implode('|', array_map('preg_quote', array_keys($banned))) . ')\s*\(/';

$bad = [];
foreach ($files as $path) {
    $rel = ltrim(str_replace($root, '', $path), '/');
    foreach (file($path) as $i => $line) {
        // a use-statement or a docblock naming the function is not a call
        $trimmed = ltrim($line);
        if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//') || str_starts_with($trimmed, 'use ')) {
            continue;
        }
        if (preg_match_all($pattern, $line, $m)) {
            foreach (array_unique($m[1]) as $fn) {
                $bad[] = sprintf('%s:%d  %s()  ->  %s', $rel, $i + 1, $fn, $banned[$fn]);
            }
        }
    }
}

if ($bad === []) {
    echo "framework-internals: OK - no native calls where Magento has a contract\n";
    exit(0);
}

fwrite(STDERR, "framework-internals: FAILED\n\n");
fwrite(STDERR, "Magento has a contract for each of these. The native call bypasses DI-swappability,\n");
fwrite(STDERR, "store and locale scope, and testability -- and no other gate flags it.\n\n");
foreach (array_unique($bad) as $b) {
    fwrite(STDERR, "  $b\n");
}
exit(1);
