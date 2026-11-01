<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Router.php';

$cases = [
    ['/','', '/'],
    ['/index.php','', '/index.php'],
    ['/checkout.php?product_id=7','', '/checkout.php'],
    ['/admin','', '/admin/'],
    ['/admin/ibsng-users.php?search=a','', '/admin/ibsng-users.php'],
    ['/install','', '/install/'],
    ['/install/index.php','', '/install/index.php'],
    ['/store/checkout.php?x=1','https://example.test/store','/checkout.php'],
    ['/store/admin/','https://example.test/store','/admin/'],
    ['/store/install/','https://example.test/store','/install/'],
    ['/storefront/','https://example.test/store','/storefront/'],
    ['/admin/../config.php','', '/admin/../config.php'],
];

foreach ($cases as [$uri, $base, $expected]) {
    $actual = store_normalize_route_path($uri, $base);
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$uri} => {$actual}; expected {$expected}\n");
        exit(1);
    }
}

$routes = store_route_map(dirname(__DIR__));
foreach ($routes as $route => $target) {
    if (!is_file($target)) {
        fwrite(STDERR, "FAIL: allowlisted route {$route} points to missing file {$target}\n");
        exit(1);
    }
}

$inferenceCases = [
    ['/install/', ''],
    ['/shop/install/', '/shop'],
    ['/shop/install/index.php', '/shop'],
    ['/shop/admin/login.php', '/shop'],
    ['/storefront/', ''],
];
foreach ($inferenceCases as [$uri, $expectedBase]) {
    $actualBase = store_infer_base_url($uri, array_keys($routes));
    if ($actualBase !== $expectedBase) {
        fwrite(STDERR, "FAIL: inferred base for {$uri} => {$actualBase}; expected {$expectedBase}\n");
        exit(1);
    }
}

echo "Router normalization, pre-install mount inference, and allowlist tests passed (" . count($cases) . " normalization cases, " . count($inferenceCases) . " inference cases, " . count($routes) . " routes).\n";
