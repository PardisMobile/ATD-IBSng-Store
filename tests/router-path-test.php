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
    ['/store/admin/','https://example.test/store','/admin/'],
    ['/storefront/admin/','https://example.test/store','/storefront/admin/'],
    ['/store/%2e%2e/config.php','https://example.test/store','/%2e%2e/config.php'],
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


$scriptNameCases = [
    ['/shop/install/', '/shop/router.php', '/shop'],
    ['/install/', '/router.php', ''],
    ['/shop/admin/login.php', '/router.php', '/shop'],
    ['/shop/install/', '/shop/index.php', '/shop'],
    ['/shop/install/', '/shop/router.php?route=install', '/shop'],
    ['/shopper/install/', '/shop/router.php', '/shop'],
];
foreach ($scriptNameCases as [$uri, $scriptName, $expectedBase]) {
    $actualBase = store_detect_base_url($uri, $scriptName, array_keys($routes));
    if ($actualBase !== $expectedBase) {
        fwrite(STDERR, "FAIL: detected base for {$uri} with {$scriptName} => {$actualBase}; expected {$expectedBase}\n");
        exit(1);
    }
}

echo "Router normalization, pre-install mount inference, and allowlist tests passed (" . count($cases) . " normalization cases, " . count($inferenceCases) . " inference cases, " . count($scriptNameCases) . " script-path cases, " . count($routes) . " routes).\n";
