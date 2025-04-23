<?php declare(strict_types=1);

namespace Inocean\SalesTaxesCanada;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
#use Inocean\SalesTaxesCanada\Migration\Migration20250422000000;

class SalesTaxesCanada extends Plugin
{
	public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
 #       $migration = new Migration20250422000000();
 #       $migration->update($this->container->get(Connection::class));
    }
}

