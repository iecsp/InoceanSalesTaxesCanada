<?php declare(strict_types=1);

$candidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

$loader = null;
foreach ($candidates as $file) {
    if (is_file($file)) {
        $loader = require $file;
        break;
    }
}

if ($loader === null) {
    fwrite(STDERR, "Unable to locate Composer autoloader for InoceanSalesTaxesCanada tests.\n");
    exit(1);
}

// Plugins in custom/plugins are loaded at runtime by Shopware's plugin loader
// and do not appear in the host project's autoloader, so register the
// namespaces manually.
$loader->addPsr4('InoceanSalesTaxesCanada\\', [__DIR__ . '/../src']);
$loader->addPsr4('InoceanSalesTaxesCanada\\Tests\\', [__DIR__]);

return $loader;
