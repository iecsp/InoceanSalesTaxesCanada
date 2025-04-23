<?php declare(strict_types=1);

namespace Inocean\SalesTaxesCanada\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderRegistry;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CanadaTaxRegistryDecorator extends TaxProviderRegistry
{
    private TaxProviderRegistry $inner;
    private CanadaTaxProvider $canadaTaxProvider;

    public function __construct(
        TaxProviderRegistry $inner,
        CanadaTaxProvider $canadaTaxProvider
    ) {
        $this->inner = $inner;
        $this->canadaTaxProvider = $canadaTaxProvider;
    }

    public function getTaxProvider(SalesChannelContext $context): AbstractTaxProvider
    {
        return $this->canadaTaxProvider;
    }
}

