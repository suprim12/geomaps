<?php
/**
 * Boots the Craft project Geo Maps is installed in, so the tests run against real Craft.
 *
 *   php vendor/bin/phpunit -c plugins/geo/phpunit.xml.dist          (from the project root)
 *
 * CRAFT_PROJECT_ROOT points at another project; by default it’s three folders up (project/plugins/geo/tests).
 * The http suite needs GEO_TEST_URL, the site’s base URL.
 *
 * The tests add their own field, entry type and section, and remove them when they finish. Geocoding never reaches
 * a real service: addresses are answered from Fixture::ADDRESSES.
 */
$root = getenv('CRAFT_PROJECT_ROOT') ?: dirname(__DIR__, 3);
require $root . '/bootstrap.php';
require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

spl_autoload_register(function(string $class) {
    $prefix = 'suprim\\geomaps\\tests\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

if (!\suprim\geomaps\Plugin::getInstance()) {
    fwrite(STDERR, "Geo Maps isn’t installed in the project at $root.\n");
    exit(1);
}

\suprim\geomaps\tests\Fixture::setUp();
register_shutdown_function([\suprim\geomaps\tests\Fixture::class, 'tearDown']);
